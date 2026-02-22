<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ArchiveOldData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:archive {--days=730 : Number of days to retain data (default: 2 years)} {--dry-run : Simulate the archiving process without making changes} {--chunk=1000 : Number of records to process per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive old visits, plan_visits, and registers to archive tables';

    /**
     * Tables to archive with their configuration.
     */
    private array $tables = [
        [
            'source' => 'visits',
            'target' => 'visits_archives',
            'dateColumn' => 'tanggal_visit',
        ],
        [
            'source' => 'plan_visits',
            'target' => 'plan_visits_archives',
            'dateColumn' => 'tanggal_visit',
        ],
        [
            'source' => 'registers',
            'target' => 'registers_archives',
            'dateColumn' => 'created_at',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        // Validate days
        if ($days < 30) {
            $this->error('Days must be at least 30 to prevent accidental data loss.');

            return Command::FAILURE;
        }

        // Validate chunk size
        if ($chunkSize < 100 || $chunkSize > 10000) {
            $this->error('Chunk size must be between 100 and 10000.');

            return Command::FAILURE;
        }

        $cutoffDate = Carbon::now()->subDays($days)->startOfDay();

        $this->info('===========================================');
        $this->info('         DATA ARCHIVING PROCESS');
        $this->info('===========================================');
        $this->info("Cutoff date: {$cutoffDate->toDateTimeString()}");
        $this->info("Chunk size: {$chunkSize}");
        $this->info('Mode: '.($dryRun ? 'DRY RUN (no changes)' : 'LIVE'));
        $this->newLine();

        Log::channel('daily')->info('Archive process started', [
            'cutoff_date' => $cutoffDate->toDateTimeString(),
            'days' => $days,
            'dry_run' => $dryRun,
        ]);

        $totalArchived = 0;
        $hasErrors = false;

        foreach ($this->tables as $tableConfig) {
            try {
                $archived = $this->archiveTable(
                    $tableConfig['source'],
                    $tableConfig['target'],
                    $tableConfig['dateColumn'],
                    $cutoffDate,
                    $chunkSize,
                    $dryRun
                );
                $totalArchived += $archived;
            } catch (Throwable $e) {
                $hasErrors = true;
                $this->error("Error archiving {$tableConfig['source']}: {$e->getMessage()}");
                Log::channel('daily')->error('Archive error', [
                    'table' => $tableConfig['source'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();
        $this->info('===========================================');
        $this->info("Total records archived: {$totalArchived}");
        $this->info('===========================================');

        Log::channel('daily')->info('Archive process completed', [
            'total_archived' => $totalArchived,
            'has_errors' => $hasErrors,
        ]);

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Archive records from source table to target table.
     */
    private function archiveTable(
        string $source,
        string $target,
        string $dateColumn,
        Carbon $cutoff,
        int $chunkSize,
        bool $dryRun
    ): int {
        $this->info("Processing: {$source} → {$target}");

        // Validate source table exists
        if (! Schema::hasTable($source)) {
            $this->warn("  Source table '{$source}' does not exist. Skipping.");

            return 0;
        }

        // Validate target table exists
        if (! Schema::hasTable($target)) {
            $this->warn("  Target table '{$target}' does not exist. Skipping.");

            return 0;
        }

        // Validate date column exists
        if (! Schema::hasColumn($source, $dateColumn)) {
            $this->warn("  Column '{$dateColumn}' not found in '{$source}'. Skipping.");

            return 0;
        }

        // Count records to archive
        $totalCount = DB::table($source)
            ->where($dateColumn, '<', $cutoff)
            ->count();

        if ($totalCount === 0) {
            $this->info('  No records to archive.');

            return 0;
        }

        $this->info("  Found {$totalCount} records older than {$cutoff->toDateString()}");

        if ($dryRun) {
            $this->info("  [DRY RUN] Would archive {$totalCount} records.");

            return 0;
        }

        // Create progress bar
        $bar = $this->output->createProgressBar($totalCount);
        $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%');

        $totalArchived = 0;
        $maxIterations = (int) ceil($totalCount / $chunkSize) + 10; // Safety limit
        $iterations = 0;

        while ($iterations < $maxIterations) {
            $iterations++;

            // Get batch of IDs to archive - use ORDER BY for consistency
            $ids = DB::table($source)
                ->where($dateColumn, '<', $cutoff)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id')
                ->toArray();

            if (empty($ids)) {
                break;
            }

            try {
                DB::transaction(function () use ($source, $target, $ids) {
                    // Insert into archive using INSERT INTO ... SELECT
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    DB::statement(
                        "INSERT INTO {$target} SELECT * FROM {$source} WHERE id IN ({$placeholders})",
                        $ids
                    );

                    // Delete from source
                    DB::table($source)->whereIn('id', $ids)->delete();
                });

                $archivedCount = count($ids);
                $totalArchived += $archivedCount;
                $bar->advance($archivedCount);

            } catch (Throwable $e) {
                $bar->finish();
                $this->newLine();
                throw $e;
            }

            // Small pause to reduce database load
            usleep(50000); // 50ms
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Archived {$totalArchived} records successfully.");

        Log::channel('daily')->info("Archived table {$source}", [
            'source' => $source,
            'target' => $target,
            'count' => $totalArchived,
        ]);

        return $totalArchived;
    }
}
