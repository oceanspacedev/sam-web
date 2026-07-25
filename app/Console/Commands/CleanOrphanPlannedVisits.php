<?php

namespace App\Console\Commands;

use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CleanOrphanPlannedVisits extends Command
{
    protected $signature = 'visits:clean-orphan-planned
        {--dry-run : Tampilkan orphan tanpa mengubah data}
        {--user= : Batasi ke user_id tertentu}
        {--from= : Batasi tanggal_visit dari tanggal ini (Y-m-d)}
        {--to= : Batasi tanggal_visit sampai tanggal ini (Y-m-d)}
        {--chunk=500 : Ukuran chunk update}';

    protected $description = 'Downgrade visit PLANNED yang tidak ter-link ke plan_visits.realized_visit_id menjadi EXTRACALL.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $query = $this->orphanQuery();
        $total = (clone $query)->count();

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Orphan PLANNED ditemukan: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->table(
                ['id', 'user_id', 'visitable_id', 'tanggal_visit', 'tipe_visit'],
                (clone $query)
                    ->orderBy('tanggal_visit')
                    ->orderBy('id')
                    ->limit(50)
                    ->get(['id', 'user_id', 'visitable_id', 'tanggal_visit', 'tipe_visit'])
                    ->map(fn (Visit $visit): array => [
                        $visit->id,
                        $visit->user_id,
                        $visit->visitable_id,
                        optional($visit->tanggal_visit)?->toDateString() ?? (string) $visit->tanggal_visit,
                        $visit->tipe_visit,
                    ])
                    ->all()
            );

            if ($total > 50) {
                $this->line('... menampilkan 50 dari '.$total.' baris.');
            }

            return self::SUCCESS;
        }

        $updated = 0;

        $query->orderBy('id')->chunkById($chunk, function ($visits) use (&$updated): void {
            $ids = $visits->pluck('id')->all();

            $affected = Visit::withoutEvents(function () use ($ids) {
                return Visit::query()
                    ->whereIn('id', $ids)
                    ->where('tipe_visit', 'PLANNED')
                    ->update([
                        'tipe_visit' => 'EXTRACALL',
                        'updated_at' => now(),
                    ]);
            });

            $updated += $affected;
            $this->line("Updated {$affected} visits (total {$updated})");
        });

        $this->info("Selesai. PLANNED → EXTRACALL: {$updated}");

        return self::SUCCESS;
    }

    protected function orphanQuery(): Builder
    {
        $query = Visit::query()
            ->where('tipe_visit', 'PLANNED')
            ->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('plan_visits')
                    ->whereColumn('plan_visits.realized_visit_id', 'visits.id')
                    ->whereNull('plan_visits.deleted_at');
            });

        if ($this->option('user')) {
            $query->where('user_id', (int) $this->option('user'));
        }

        if ($this->option('from')) {
            $query->whereDate('tanggal_visit', '>=', (string) $this->option('from'));
        }

        if ($this->option('to')) {
            $query->whereDate('tanggal_visit', '<=', (string) $this->option('to'));
        }

        return $query;
    }
}
