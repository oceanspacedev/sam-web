<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MaintainOutletLifecycle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Lifecycle: MAINTAIN → UNMAINTAIN → UNPRODUCTIVE → ARCHIVE
     */
    protected $signature = 'outlets:maintain-lifecycle 
                            {--days=30 : Days inactive before MAINTAIN → UNMAINTAIN (min 7)}
                            {--unproductive-days=60 : Days inactive before UNMAINTAIN → UNPRODUCTIVE (min 30)}
                            {--archive-days=90 : Days inactive before UNPRODUCTIVE → ARCHIVE (min 60)}
                            {--dry-run : Run in simulation mode without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Run outlet lifecycle: MAINTAIN → UNMAINTAIN → UNPRODUCTIVE → ARCHIVE';

    /**
     * Counters for summary
     */
    private array $counters = [
        'reactivated' => 0,
        'maintain_to_unmaintain' => 0,
        'unmaintain_to_unproductive' => 0,
        'archived' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $days = max(7, (int) $this->option('days'));
        $unproductiveDays = max(30, (int) $this->option('unproductive-days'));
        $archiveDays = max(60, (int) $this->option('archive-days'));

        // Validate logical order: days < unproductive-days < archive-days
        if ($days >= $unproductiveDays) {
            $this->error('--days must be less than --unproductive-days');

            return self::FAILURE;
        }
        if ($unproductiveDays >= $archiveDays) {
            $this->error('--unproductive-days must be less than --archive-days');

            return self::FAILURE;
        }

        // Validate archive table exists
        if (! Schema::hasTable('outlets_archives')) {
            $this->error('Archive table "outlets_archives" does not exist.');

            return self::FAILURE;
        }

        $this->printHeader($dryRun, $days, $unproductiveDays, $archiveDays);

        $startTime = microtime(true);

        Log::channel('daily')->info('Outlet lifecycle maintenance started', [
            'mode' => $dryRun ? 'dry-run' : 'live',
            'days' => $days,
            'unproductive_days' => $unproductiveDays,
            'archive_days' => $archiveDays,
        ]);

        // ==============================================
        // PHASE 1: REACTIVATION (rescue outlets with recent activity)
        // ==============================================
        $this->printPhaseHeader('1', 'Reactivation (UNMAINTAIN/UNPRODUCTIVE → MAINTAIN)');
        $this->processReactivation($days, $dryRun);

        // ==============================================
        // PHASE 2: MAINTAIN → UNMAINTAIN
        // ==============================================
        $this->printPhaseHeader('2', 'Stage 1: MAINTAIN → UNMAINTAIN');
        $this->processMaintainToUnmaintain($days, $dryRun);

        // ==============================================
        // PHASE 3: UNMAINTAIN → UNPRODUCTIVE
        // ==============================================
        $this->printPhaseHeader('3', 'Stage 2: UNMAINTAIN → UNPRODUCTIVE');
        $this->processUnmaintainToUnproductive($unproductiveDays, $dryRun);

        // ==============================================
        // PHASE 4: UNPRODUCTIVE → ARCHIVE (Weekly on Monday only)
        // ==============================================
        $isMonday = now()->dayOfWeek === Carbon::MONDAY;
        if ($isMonday || $dryRun) {
            $this->printPhaseHeader('4', 'Stage 3: UNPRODUCTIVE → ARCHIVE'.($dryRun && ! $isMonday ? ' (simulated)' : ''));
            $this->processArchiving($archiveDays, $dryRun);
        } else {
            $this->newLine();
            $this->comment('→ Phase 4: Archiving - SKIPPED (runs on Monday only)');
        }

        // ==============================================
        // SUMMARY
        // ==============================================
        $this->printSummary($startTime, $dryRun);

        Log::channel('daily')->info('Outlet lifecycle maintenance completed', $this->counters);

        return $this->counters['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Phase 1: Reactivate UNMAINTAIN/UNPRODUCTIVE outlets with recent visits
     */
    private function processReactivation(int $days, bool $dryRun): void
    {
        $this->info('  Checking UNMAINTAIN/UNPRODUCTIVE outlets with visits in last '.$days.' days...');

        $cutoffDate = now()->subDays($days);

        // Reactivate both UNMAINTAIN and UNPRODUCTIVE if they have recent visits
        $query = Outlet::whereIn('status_outlet', ['UNMAINTAIN', 'UNPRODUCTIVE'])
            ->whereHas('visit', function ($q) use ($cutoffDate) {
                $q->where('tanggal_visit', '>=', $cutoffDate);
            })
            ->orderBy('id');

        $count = $query->count();
        $this->info("  Found {$count} outlets to reactivate.");

        if ($count === 0) {
            return;
        }

        if ($dryRun) {
            $this->showDryRunSample($query, 'reactivate to MAINTAIN');
            $this->counters['reactivated'] = $count;

            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(200, function ($outlets) use (&$bar, $cutoffDate) {
            foreach ($outlets as $outlet) {
                try {
                    DB::transaction(function () use ($outlet, $cutoffDate) {
                        $locked = Outlet::where('id', $outlet->id)->lockForUpdate()->first();
                        if (! $locked || ! in_array($locked->status_outlet, ['UNMAINTAIN', 'UNPRODUCTIVE'])) {
                            return;
                        }

                        // Verify still has recent visit
                        $hasRecentVisit = $locked->visit()
                            ->where('tanggal_visit', '>=', $cutoffDate)
                            ->exists();

                        if ($hasRecentVisit) {
                            $locked->update(['status_outlet' => 'MAINTAIN']);
                            $this->counters['reactivated']++;
                        }
                    });
                } catch (Throwable $e) {
                    $this->handleError('Reactivation', $outlet, $e);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * Phase 2: MAINTAIN → UNMAINTAIN for inactive outlets
     */
    private function processMaintainToUnmaintain(int $days, bool $dryRun): void
    {
        $this->info('  Checking MAINTAIN outlets inactive for '.$days.'+ days...');

        $cutoffDate = now()->subDays($days);

        $query = Outlet::where('status_outlet', 'MAINTAIN')
            ->whereDoesntHave('visit', function ($q) use ($cutoffDate) {
                $q->where('tanggal_visit', '>=', $cutoffDate);
            })
            ->whereDoesntHave('planvisit', function ($q) {
                $q->whereNull('realized_at')->where('period_end', '>=', now());
            })
            ->orderBy('id');

        $count = $query->count();
        $this->info("  Found {$count} outlets to transition.");

        if ($count === 0) {
            return;
        }

        if ($dryRun) {
            $this->showDryRunSample($query, 'set to UNMAINTAIN');
            $this->counters['maintain_to_unmaintain'] = $count;

            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(200, function ($outlets) use (&$bar, $cutoffDate) {
            foreach ($outlets as $outlet) {
                try {
                    DB::transaction(function () use ($outlet, $cutoffDate) {
                        $locked = Outlet::where('id', $outlet->id)->lockForUpdate()->first();
                        if (! $locked || $locked->status_outlet !== 'MAINTAIN') {
                            return;
                        }

                        // Double-check no recent visit
                        $hasRecentVisit = $locked->visit()
                            ->where('tanggal_visit', '>=', $cutoffDate)
                            ->exists();

                        if (! $hasRecentVisit) {
                            $locked->update(['status_outlet' => 'UNMAINTAIN']);
                            $this->counters['maintain_to_unmaintain']++;
                        }
                    });
                } catch (Throwable $e) {
                    $this->handleError('Stage 1', $outlet, $e);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * Phase 3: UNMAINTAIN → UNPRODUCTIVE
     */
    private function processUnmaintainToUnproductive(int $unproductiveDays, bool $dryRun): void
    {
        $this->info('  Checking UNMAINTAIN outlets inactive for '.$unproductiveDays.'+ days...');

        $cutoffDate = now()->subDays($unproductiveDays);

        $query = Outlet::where('status_outlet', 'UNMAINTAIN')
            ->whereDoesntHave('visit', function ($q) use ($cutoffDate) {
                $q->where('tanggal_visit', '>=', $cutoffDate);
            })
            ->whereDoesntHave('planvisit', function ($q) {
                $q->whereNull('realized_at')->where('period_end', '>=', now());
            })
            ->orderBy('id');

        $count = $query->count();
        $this->info("  Found {$count} outlets to transition.");

        if ($count === 0) {
            return;
        }

        if ($dryRun) {
            $this->showDryRunSample($query, 'set to UNPRODUCTIVE');
            $this->counters['unmaintain_to_unproductive'] = $count;

            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(200, function ($outlets) use (&$bar, $cutoffDate) {
            foreach ($outlets as $outlet) {
                try {
                    DB::transaction(function () use ($outlet, $cutoffDate) {
                        $locked = Outlet::where('id', $outlet->id)->lockForUpdate()->first();
                        if (! $locked || $locked->status_outlet !== 'UNMAINTAIN') {
                            return;
                        }

                        $hasRecentVisit = $locked->visit()
                            ->where('tanggal_visit', '>=', $cutoffDate)
                            ->exists();

                        if (! $hasRecentVisit) {
                            $locked->update(['status_outlet' => 'UNPRODUCTIVE']);
                            $this->counters['unmaintain_to_unproductive']++;
                        }
                    });
                } catch (Throwable $e) {
                    $this->handleError('Stage 2', $outlet, $e);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * Phase 4: UNPRODUCTIVE → Archive
     */
    private function processArchiving(int $archiveDays, bool $dryRun): void
    {
        $this->info('  Checking UNPRODUCTIVE outlets inactive for '.$archiveDays.'+ days...');

        $cutoffDate = now()->subDays($archiveDays);

        $query = Outlet::where('status_outlet', 'UNPRODUCTIVE')
            ->whereDoesntHave('visit', function ($q) use ($cutoffDate) {
                $q->where('tanggal_visit', '>=', $cutoffDate);
            })
            ->whereDoesntHave('planvisit', function ($q) {
                $q->whereNull('realized_at')->where('period_end', '>=', now());
            })
            ->orderBy('id');

        $count = $query->count();
        $this->info("  Found {$count} outlets to archive.");

        if ($count === 0) {
            return;
        }

        if ($dryRun) {
            $this->showDryRunSample($query, 'archive');
            $this->counters['archived'] = $count;

            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(100, function ($outlets) use (&$bar, $cutoffDate) {
            foreach ($outlets as $outlet) {
                try {
                    $this->archiveOutlet($outlet, $cutoffDate);
                    $this->counters['archived']++;
                } catch (Throwable $e) {
                    $this->handleError('Archive', $outlet, $e);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * Archive a single outlet with proper locking
     */
    private function archiveOutlet(Outlet $outlet, Carbon $cutoffDate): void
    {
        $mediaFields = ['poto_shop_sign', 'poto_depan', 'poto_kiri', 'poto_kanan', 'poto_ktp', 'video'];
        $mediaPaths = array_filter(array_map(fn ($f) => $outlet->$f, $mediaFields));

        DB::transaction(function () use ($outlet, $cutoffDate) {
            $locked = Outlet::where('id', $outlet->id)->lockForUpdate()->first();

            if (! $locked || $locked->status_outlet !== 'UNPRODUCTIVE') {
                return;
            }

            // Double-check no recent visit
            $hasRecentVisit = $locked->visit()
                ->where('tanggal_visit', '>=', $cutoffDate)
                ->exists();

            if ($hasRecentVisit) {
                return;
            }

            // Check if already archived
            $alreadyArchived = DB::table('outlets_archives')
                ->where('outlet_id', $locked->id)
                ->exists();

            if ($alreadyArchived) {
                $locked->forceDelete();

                return;
            }

            // Archive
            DB::table('outlets_archives')->insert([
                'outlet_id' => $locked->id,
                'kode_outlet' => $locked->kode_outlet,
                'nama_outlet' => $locked->nama_outlet,
                'alamat_outlet' => $locked->alamat_outlet,
                'nama_pemilik_outlet' => $locked->nama_pemilik_outlet,
                'nomer_tlp_outlet' => $locked->nomer_tlp_outlet,
                'badanusaha_id' => $locked->badanusaha_id,
                'divisi_id' => $locked->divisi_id,
                'region_id' => $locked->region_id,
                'cluster_id' => $locked->cluster_id,
                'distric' => $locked->distric,
                'poto_shop_sign' => $locked->poto_shop_sign,
                'poto_depan' => $locked->poto_depan,
                'poto_kiri' => $locked->poto_kiri,
                'poto_kanan' => $locked->poto_kanan,
                'poto_ktp' => $locked->poto_ktp,
                'video' => $locked->video,
                'limit' => $locked->limit,
                'radius' => $locked->radius,
                'latlong' => $locked->latlong,
                'status_outlet' => $locked->status_outlet,
                'archived_by' => 'SYSTEM',
                'archived_at' => now(),
                'archive_reason' => 'Lifecycle: UNPRODUCTIVE for extended period',
                'original_created_at' => $locked->created_at,
                'original_updated_at' => $locked->updated_at,
                'original_deleted_at' => $locked->deleted_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $locked->forceDelete();
        });

        // Delete media files outside transaction
        $this->deleteMediaFiles($mediaPaths);
    }

    /**
     * Delete media files from storage
     */
    private function deleteMediaFiles(array $mediaPaths): void
    {
        if (empty($mediaPaths)) {
            return;
        }

        try {
            $disk = Storage::disk(\App\Support\StorageDisk::default());
            foreach ($mediaPaths as $path) {
                if ($path && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
        } catch (Throwable $e) {
            Log::channel('daily')->warning('Failed to delete media files', [
                'paths' => $mediaPaths,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show dry-run sample
     */
    private function showDryRunSample($query, string $action): void
    {
        $count = $query->count();
        $query->limit(5)->get()->each(function ($outlet) use ($action) {
            $this->line("    [DRY RUN] Would {$action}: {$outlet->kode_outlet}");
        });
        if ($count > 5) {
            $this->comment('    ... and '.($count - 5).' more.');
        }
    }

    /**
     * Handle error
     */
    private function handleError(string $phase, Outlet $outlet, Throwable $e): void
    {
        $this->counters['errors']++;
        Log::channel('daily')->error("{$phase} failed: {$outlet->kode_outlet}", [
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Print header
     */
    private function printHeader(bool $dryRun, int $days, int $unproductiveDays, int $archiveDays): void
    {
        $this->newLine();
        $this->info('╔═══════════════════════════════════════════════════════════════════════╗');
        $this->info('║              OUTLET LIFECYCLE MAINTENANCE                             ║');
        $this->info('║  MAINTAIN → UNMAINTAIN → UNPRODUCTIVE → ARCHIVE                       ║');
        $this->info('╚═══════════════════════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->table(
            ['Setting', 'Value'],
            [
                ['Mode', $dryRun ? '🔍 DRY RUN' : '⚡ LIVE'],
                ['MAINTAIN → UNMAINTAIN', "After {$days} days inactive"],
                ['UNMAINTAIN → UNPRODUCTIVE', "After {$unproductiveDays} days inactive"],
                ['UNPRODUCTIVE → ARCHIVE', "After {$archiveDays} days inactive (Monday only)"],
                ['Run date', now()->format('Y-m-d H:i:s')],
            ]
        );
        $this->newLine();
    }

    /**
     * Print phase header
     */
    private function printPhaseHeader(string $phase, string $title): void
    {
        $this->newLine();
        $this->info('┌─────────────────────────────────────────────────────────────┐');
        $this->info("│  Phase {$phase}: {$title}");
        $this->info('└─────────────────────────────────────────────────────────────┘');
    }

    /**
     * Print summary
     */
    private function printSummary(float $startTime, bool $dryRun): void
    {
        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine(2);
        $this->info('╔═══════════════════════════════════════════════════════════╗');
        $this->info('║              MAINTENANCE SUMMARY                          ║');
        $this->info('╚═══════════════════════════════════════════════════════════╝');

        $this->table(
            ['Transition', 'Count'],
            [
                ['Reactivated → MAINTAIN', $this->counters['reactivated']],
                ['MAINTAIN → UNMAINTAIN', $this->counters['maintain_to_unmaintain']],
                ['UNMAINTAIN → UNPRODUCTIVE', $this->counters['unmaintain_to_unproductive']],
                ['UNPRODUCTIVE → ARCHIVE', $this->counters['archived']],
                ['Errors', $this->counters['errors']],
                ['Duration', "{$duration}s"],
            ]
        );

        if ($this->counters['errors'] > 0) {
            $this->error('⚠️  Completed with errors. Check logs.');
        } elseif ($dryRun) {
            $this->warn('🔍 DRY RUN completed - no changes made.');
        } else {
            $this->info('✅ Maintenance completed successfully!');
        }
    }
}
