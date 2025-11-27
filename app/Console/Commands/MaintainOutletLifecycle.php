<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaintainOutletLifecycle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outlets:maintain-lifecycle 
                            {--days=30 : Day threshold for activity checks}
                            {--dry-run : Run all commands in dry-run mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run complete outlet lifecycle maintenance (sequential: validation → cleanup → lifecycle transitions)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $daysOption = (int) $this->option('days');
        $days = $daysOption > 0 ? $daysOption : 30; // Default threshold

        $this->info('=======================================================');
        $this->info('  OUTLET LIFECYCLE MAINTENANCE - Sequential Execution  ');
        $this->info('=======================================================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
            $this->newLine();
        }

        $startTime = microtime(true);

        // ===================================
        // PHASE 1: DATA VALIDATION & CLEANUP
        // ===================================
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║  PHASE 1: Data Validation & Cleanup              ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        // Step 1.1: Reactivate UNMAINTAIN outlets with recent visits
        $reactivationCount = $this->processReactivation($days, $dryRun);
        $this->newLine();

        // Step 1.2: Cleanup UNPRODUCTIVE outlets from main table
        $cleanupCount = $this->processCleanup($days, $dryRun);
        $this->newLine();

        // ===================================
        // PHASE 2: LIFECYCLE FORWARD
        // ===================================
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║  PHASE 2: Lifecycle Forward                      ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        // Step 2.1: Stage 1 - MAINTAIN → UNMAINTAIN
        $stage1Count = $this->processStage1($days, $dryRun);
        $this->newLine();

        // Step 2.2: Stage 2 - UNMAINTAIN → Archive (Weekly only on Monday)
        $today = now()->dayOfWeek; // 0=Sunday, 1=Monday, etc.
        $stage2Count = null;
        if ($today === 1 || $dryRun) { // Monday or dry-run
            $stage2Count = $this->processStage2($days, $dryRun);
        } else {
            $this->comment('→ Step 2.2: Stage 2 - Skipped (runs on Monday only)');
        }
        $this->newLine();

        // ===================================
        // SUMMARY
        // ===================================
        $duration = round(microtime(true) - $startTime, 2);

        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║  MAINTENANCE COMPLETED                            ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Duration', "{$duration}s"],
                ['Mode', $dryRun ? 'DRY RUN' : 'LIVE'],
                ['Reactivation (UNMAINTAIN/UNPRODUCTIVE → MAINTAIN)', $reactivationCount],
                ['Cleanup (UNPRODUCTIVE → archive)', $cleanupCount],
                ['Stage 1 (MAINTAIN → UNMAINTAIN)', $stage1Count],
                ['Stage 2 (UNMAINTAIN → archive)', $stage2Count === null ? 'SKIPPED (runs Monday)' : $stage2Count],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN completed - no actual changes were made');
        } else {
            $this->info('✅ Outlet lifecycle maintenance completed successfully!');
        }

        return self::SUCCESS;
    }

    /**
     * Step 1.1: Reactivate outlets (UNMAINTAIN/UNPRODUCTIVE) that have recent visits
     */
    protected function processReactivation(int $days, bool $dryRun): int
    {
        $this->info('→ Step 1.1: Auto-Reactivation (UNMAINTAIN/UNPRODUCTIVE → MAINTAIN)');
        $this->line("  Checking UNMAINTAIN/UNPRODUCTIVE outlets with visits in last {$days} days...");

        $cutoffDate = now()->subDays($days);

        $outlets = \App\Models\Outlet::whereIn('status_outlet', ['UNMAINTAIN', 'UNPRODUCTIVE'])
            ->whereHas('visit', function ($query) use ($cutoffDate) {
                $query->where('tanggal_visit', '>=', $cutoffDate);
            })
            ->orderBy('id');

        $count = $outlets->count();
        $this->line("  Found {$count} outlets to reactivate.");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $outlets->chunkById(200, function ($outletsChunk) use ($dryRun, &$bar) {
            foreach ($outletsChunk as $outlet) {
                $fromStatus = $outlet->status_outlet;
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would reactivate ({$fromStatus} → MAINTAIN): {$outlet->kode_outlet}");
                } else {
                    $outlet->update(['status_outlet' => 'MAINTAIN']);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        return $count;
    }

    /**
     * Step 1.2: Cleanup UNPRODUCTIVE outlets (Archive & Delete)
     */
    protected function processCleanup(int $days, bool $dryRun): int
    {
        $this->info('→ Step 1.2: Cleanup UNPRODUCTIVE');
        $this->line("  Archiving and removing UNPRODUCTIVE outlets inactive for {$days}+ days...");

        $cutoffDate = now()->subDays($days);

        $outlets = \App\Models\Outlet::where('status_outlet', 'UNPRODUCTIVE')
            ->whereDoesntHave('visit', function ($query) use ($cutoffDate) {
                $query->where('tanggal_visit', '>=', $cutoffDate);
            })
            ->whereDoesntHave('planvisit', function ($query) use ($cutoffDate) {
                $query->whereNull('realized_at')
                    ->where(function ($query) use ($cutoffDate) {
                        $query->where('tanggal_visit', '>=', $cutoffDate)
                            ->orWhere('period_end', '>=', $cutoffDate);
                    });
            })
            ->where(function ($query) use ($cutoffDate) {
                $query->whereNull('updated_at')->orWhere('updated_at', '<', $cutoffDate);
            })
            ->orderBy('id');

        $count = $outlets->count();
        $this->line("  Found {$count} UNPRODUCTIVE outlets to cleanup (skips recently visited/updated).");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $outlets->chunkById(200, function ($outletsChunk) use ($dryRun, &$bar) {
            foreach ($outletsChunk as $outlet) {
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would archive & delete: {$outlet->kode_outlet}");
                } else {
                    $this->archiveAndRemove($outlet);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        return $count;
    }

    /**
     * Step 2.1: Stage 1 - MAINTAIN → UNMAINTAIN
     */
    protected function processStage1(int $days, bool $dryRun): int
    {
        $this->info('→ Step 2.1: Stage 1 (MAINTAIN → UNMAINTAIN)');
        $this->line("  Checking MAINTAIN outlets with no visits in {$days} days...");

        $cutoffDate = now()->subDays($days);

        $outlets = \App\Models\Outlet::where('status_outlet', 'MAINTAIN')
            ->whereDoesntHave('visit', function ($query) use ($cutoffDate) {
                $query->where('tanggal_visit', '>=', $cutoffDate);
            })
            ->whereDoesntHave('planvisit', function ($query) use ($cutoffDate) {
                $query->whereNull('realized_at')
                    ->where(function ($query) use ($cutoffDate) {
                        $query->where('tanggal_visit', '>=', $cutoffDate)
                            ->orWhere('period_end', '>=', $cutoffDate);
                    });
            })
            ->where(function ($query) use ($cutoffDate) {
                $query->whereNull('updated_at')->orWhere('updated_at', '<', $cutoffDate);
            })
            ->orderBy('id');

        $count = $outlets->count();
        $this->line("  Found {$count} inactive outlets to warn.");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $outlets->chunkById(200, function ($outletsChunk) use ($dryRun, &$bar) {
            foreach ($outletsChunk as $outlet) {
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would set UNMAINTAIN: {$outlet->kode_outlet}");
                } else {
                    $outlet->update(['status_outlet' => 'UNMAINTAIN']);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        return $count;
    }

    /**
     * Step 2.2: Stage 2 - UNMAINTAIN → Archive
     */
    protected function processStage2(int $days, bool $dryRun): int
    {
        $this->info('→ Step 2.2: Stage 2 (UNMAINTAIN → Archive)');
        $this->line("  Checking UNMAINTAIN outlets with no activity in {$days} days...");

        $cutoffDate = now()->subDays($days);

        // Criteria: UNMAINTAIN + No Visit + No Update
        $outlets = \App\Models\Outlet::where('status_outlet', 'UNMAINTAIN')
            ->whereDoesntHave('visit', function ($query) use ($cutoffDate) {
                $query->where('tanggal_visit', '>=', $cutoffDate);
            })
            ->whereDoesntHave('planvisit', function ($query) use ($cutoffDate) {
                $query->whereNull('realized_at')
                    ->where(function ($query) use ($cutoffDate) {
                        $query->where('tanggal_visit', '>=', $cutoffDate)
                            ->orWhere('period_end', '>=', $cutoffDate);
                    });
            })
            ->where(function ($query) use ($cutoffDate) {
                $query->where('updated_at', '<', $cutoffDate)
                    ->orWhereNull('updated_at');
            })
            ->orderBy('id');

        $count = $outlets->count();
        $this->line("  Found {$count} outlets to archive.");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $outlets->chunkById(200, function ($outletsChunk) use ($dryRun, &$bar) {
            foreach ($outletsChunk as $outlet) {
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would archive & delete: {$outlet->kode_outlet}");
                } else {
                    $this->archiveAndRemove($outlet);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        return $count;
    }

    /**
     * Helper: Archive outlet data and remove from main table
     */
    protected function archiveAndRemove(\App\Models\Outlet $outlet): void
    {
        // Collect media paths up front to avoid losing references if delete mutates model state
        $mediaFields = ['poto_shop_sign', 'poto_depan', 'poto_kiri', 'poto_kanan', 'poto_ktp', 'video'];
        $mediaPaths = [];

        foreach ($mediaFields as $field) {
            if (! empty($outlet->$field)) {
                $mediaPaths[] = $outlet->$field;
            }
        }

        try {
            DB::transaction(function () use ($outlet) {
                // 1. Archive Data (insert to archive table directly)
                DB::table('outlets_archives')->insert([
                    'outlet_id' => $outlet->id,
                    'kode_outlet' => $outlet->kode_outlet,
                    'nama_outlet' => $outlet->nama_outlet,
                    'alamat_outlet' => $outlet->alamat_outlet,
                    'nama_pemilik_outlet' => $outlet->nama_pemilik_outlet,
                    'nomer_tlp_outlet' => $outlet->nomer_tlp_outlet,
                    'badanusaha_id' => $outlet->badanusaha_id,
                    'divisi_id' => $outlet->divisi_id,
                    'region_id' => $outlet->region_id,
                    'cluster_id' => $outlet->cluster_id,
                    'distric' => $outlet->distric,
                    'poto_shop_sign' => $outlet->poto_shop_sign,
                    'poto_depan' => $outlet->poto_depan,
                    'poto_kiri' => $outlet->poto_kiri,
                    'poto_kanan' => $outlet->poto_kanan,
                    'poto_ktp' => $outlet->poto_ktp,
                    'video' => $outlet->video,
                    'limit' => $outlet->limit,
                    'radius' => $outlet->radius,
                    'latlong' => $outlet->latlong,
                    'status_outlet' => $outlet->status_outlet, // Preserve original status
                    'archived_by' => 'SYSTEM',
                    'archived_at' => now(),
                    'archive_reason' => 'Automated Lifecycle Maintenance',
                    'original_created_at' => $outlet->created_at,
                    'original_updated_at' => $outlet->updated_at,
                    'original_deleted_at' => $outlet->deleted_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 2. Soft Delete (DB-level so it can be rolled back if archive fails)
                $outlet->delete();
            });

            // 3. Delete Media Files (outside transaction)
            $this->deleteMediaFiles($mediaPaths);

        } catch (\Exception $e) {
            $this->error("Failed to archive {$outlet->kode_outlet}: {$e->getMessage()}");
        }
    }

    /**
     * Remove media assets for archived outlets.
     */
    protected function deleteMediaFiles(array $mediaPaths): void
    {
        $disk = Storage::disk(\App\Support\StorageDisk::default());

        foreach ($mediaPaths as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
