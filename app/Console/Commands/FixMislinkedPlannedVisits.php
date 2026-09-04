<?php

namespace App\Console\Commands;

use App\Models\PlanVisit;
use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMislinkedPlannedVisits extends Command
{
    protected $signature = 'visits:fix-mislinked-plans
        {--dry-run : Do not modify data, just show what would change}
        {--chunk=500 : Chunk size}
        {--from= : Only process realized_at from this date (Y-m-d)}
        {--to= : Only process realized_at up to this date (Y-m-d)}
    ';

    protected $description = 'Reassign realized_visit_id to the plan_visit whose period contains the visit when mislinked.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $query = PlanVisit::query()
            ->whereNotNull('realized_visit_id')
            ->whereNotNull('realized_at')
            ->whereNull('deleted_at');

        if ($this->option('from')) {
            $query->whereDate('realized_at', '>=', (string) $this->option('from'));
        }

        if ($this->option('to')) {
            $query->whereDate('realized_at', '<=', (string) $this->option('to'));
        }

        $total = $query->count();
        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Plan visits with realized_visit_id: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $moved = 0;

        $query->orderBy('id')->chunkById($chunk, function ($plans) use (&$moved, $dryRun) {
            foreach ($plans as $old) {
                $visit = Visit::withTrashed()->find($old->realized_visit_id);

                if (! $visit) {
                    $this->line("Visit {$old->realized_visit_id} not found for plan {$old->id}, skipping.");

                    continue;
                }

                $candidate = PlanVisit::query()
                    ->where('user_id', $visit->user_id)
                    ->where('visitable_type', $visit->visitable_type)
                    ->where('visitable_id', $visit->visitable_id)
                    ->whereNull('realized_visit_id')
                    ->whereNull('deleted_at')
                    ->where('id', '<>', $old->id)
                    ->where('period_start', '<=', $visit->tanggal_visit)
                    ->where('period_end', '>=', $visit->tanggal_visit)
                    ->orderBy('period_start')
                    ->orderBy('id')
                    ->first();

                if (! $candidate) {
                    $this->line("No candidate plan for visit {$visit->id} (plan {$old->id}), skipping.");

                    continue;
                }

                $this->line("Found candidate: move visit {$visit->id} from plan {$old->id} -> plan {$candidate->id}");

                if ($dryRun) {
                    $moved++;

                    continue;
                }

                DB::transaction(function () use ($old, $candidate, $visit, &$moved) {
                    $realizedAt = $old->realized_at ?: $visit->check_in_time ?: now();

                    // assign to candidate
                    $candidate->realized_visit_id = $visit->id;
                    $candidate->realized_at = $realizedAt;
                    $candidate->save();

                    // remove from old
                    $old->realized_visit_id = null;
                    $old->realized_at = null;
                    $old->save();

                    $moved++;
                });
            }
        });

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Completed. Reassigned: {$moved}");

        return self::SUCCESS;
    }
}
