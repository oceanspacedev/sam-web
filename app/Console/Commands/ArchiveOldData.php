<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ArchiveOldData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:archive {--days=730 : Number of days to retain data} {--dry-run : Simulate the archiving process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive old visits and plan_visits to archive tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = \Carbon\Carbon::now()->subDays($days)->startOfDay();

        $this->info("Archiving data older than: " . $cutoffDate->toDateTimeString());

        $this->archiveTable('visits', 'visits_archives', 'tanggal_visit', $cutoffDate, $dryRun);
        $this->archiveTable('plan_visits', 'plan_visits_archives', 'tanggal_visit', $cutoffDate, $dryRun);
        $this->archiveTable('registers', 'registers_archives', 'created_at', $cutoffDate, $dryRun);

        $this->info('Archiving process completed.');
    }

    private function archiveTable(string $source, string $target, string $dateCol, $cutoff, bool $dryRun)
    {
        $query = \Illuminate\Support\Facades\DB::table($source)->where($dateCol, '<', $cutoff);
        $count = $query->count();

        if ($count === 0) {
            $this->info("No records found to archive in $source.");
            return;
        }

        $this->info("Found $count records in $source older than $cutoff.");

        if ($dryRun) {
            $this->info("Dry run: would archive $count records from $source to $target.");
            return;
        }

        $bar = $this->output->createProgressBar($count);
        $chunkSize = 1000;
        $totalArchived = 0;

        do {
            // Select IDs to archive
            $ids = \Illuminate\Support\Facades\DB::table($source)
                ->where($dateCol, '<', $cutoff)
                ->limit($chunkSize)
                ->pluck('id')
                ->toArray();

            if (empty($ids)) {
                break;
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($source, $target, $ids) {
                // Insert into archive
                // Using raw statement for performance: INSERT INTO ... SELECT ...
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                \Illuminate\Support\Facades\DB::statement(
                    "INSERT INTO $target SELECT * FROM $source WHERE id IN ($placeholders)",
                    $ids
                );

                // Delete from source
                \Illuminate\Support\Facades\DB::table($source)->whereIn('id', $ids)->delete();
            });

            $countArchived = count($ids);
            $totalArchived += $countArchived;
            $bar->advance($countArchived);

            // Small pause to reduce database load
            usleep(50000); // 50ms

        } while (true);

        $bar->finish();
        $this->newLine();
        $this->info("Archived $totalArchived records from $source.");
    }
}
