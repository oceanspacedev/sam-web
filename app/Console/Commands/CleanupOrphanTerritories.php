<?php

namespace App\Console\Commands;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use Illuminate\Console\Command;

class CleanupOrphanTerritories extends Command
{
    protected $signature = 'territories:cleanup-orphans {--dry-run : Simulate cleanup without deleting data}';

    protected $description = 'Cleanup divisions/regions/clusters that no longer have child records';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('==============================================');
        $this->info('  TERRITORY CLEANUP - ORPHANED HIERARCHY      ');
        $this->info('==============================================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
            $this->newLine();
        }

        // Bottom-up cleanup: clusters → regions → divisions → badan usaha
        $removedClusters = $this->cleanupClusters($dryRun);
        $this->newLine();

        $removedRegions = $this->cleanupRegions($dryRun);
        $this->newLine();

        $removedDivisions = $this->cleanupDivisions($dryRun);
        $this->newLine();

        $removedBadanUsaha = $this->cleanupBadanUsaha($dryRun);
        $this->newLine();

        $removedRoles = $this->cleanupRoles($dryRun);
        $this->newLine();

        $this->info('Cleanup completed');
        $this->table(
            ['Scope', 'Removed'],
            [
                ['Clusters without outlets', $removedClusters],
                ['Regions without clusters', $removedRegions],
                ['Divisions without regions', $removedDivisions],
                ['Badan Usaha without divisions', $removedBadanUsaha],
                ['Roles without users', $removedRoles],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN completed - no changes were made');
        } else {
            $this->info('✅ Orphan territory cleanup finished');
        }

        return self::SUCCESS;
    }

    protected function cleanupDivisions(bool $dryRun): int
    {
        $this->info('→ Cleaning divisions with no regions...');

        $divisions = Division::whereNull('deleted_at')
            ->doesntHave('region')
            ->orderBy('id');

        $count = $divisions->count();
        $this->line("  Found {$count} divisions to remove.");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $divisions->chunkById(200, function ($chunk) use ($dryRun, &$bar) {
            /** @var Division $division */
            foreach ($chunk as $division) {
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would delete division: {$division->id} - {$division->name}");
                } else {
                    $division->delete();
                }
                $bar->advance();
            }
        });

        $bar->finish();

        return $count;
    }

    protected function cleanupBadanUsaha(bool $dryRun): int
    {
        $this->info('→ Cleaning badan usaha with no divisions...');

        $badanUsahas = BadanUsaha::whereNull('deleted_at')
            ->doesntHave('divisi')
            ->orderBy('id');

        $count = $badanUsahas->count();
        $this->line("  Found {$count} badan usaha to remove.");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $badanUsahas->chunkById(200, function ($chunk) use ($dryRun, &$bar) {
            /** @var BadanUsaha $badanUsaha */
            foreach ($chunk as $badanUsaha) {
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would delete badan usaha: {$badanUsaha->id} - {$badanUsaha->name}");
                } else {
                    $badanUsaha->delete();
                }
                $bar->advance();
            }
        });

        $bar->finish();

        return $count;
    }

    protected function cleanupRegions(bool $dryRun): int
    {
        $this->info('→ Cleaning regions with no clusters...');

        $regions = Region::whereNull('deleted_at')
            ->doesntHave('cluster')
            ->orderBy('id');

        $count = $regions->count();
        $this->line("  Found {$count} regions to remove.");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $regions->chunkById(200, function ($chunk) use ($dryRun, &$bar) {
            /** @var Region $region */
            foreach ($chunk as $region) {
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would delete region: {$region->id} - {$region->name}");
                } else {
                    $region->delete();
                }
                $bar->advance();
            }
        });

        $bar->finish();

        return $count;
    }

    protected function cleanupClusters(bool $dryRun): int
    {
        $this->info('→ Cleaning clusters with no outlets...');

        $clusters = Cluster::whereNull('deleted_at')
            ->doesntHave('outlet')
            ->orderBy('id');

        $count = $clusters->count();
        $this->line("  Found {$count} clusters to remove.");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $clusters->chunkById(200, function ($chunk) use ($dryRun, &$bar) {
            /** @var Cluster $cluster */
            foreach ($chunk as $cluster) {
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would delete cluster: {$cluster->id} - {$cluster->name}");
                } else {
                    $cluster->delete();
                }
                $bar->advance();
            }
        });

        $bar->finish();

        return $count;
    }

    protected function cleanupRoles(bool $dryRun): int
    {
        $this->info('→ Cleaning roles with no users...');

        $roles = \App\Models\Role::whereNull('deleted_at')
            ->doesntHave('user')
            ->orderBy('id');

        $count = $roles->count();
        $this->line("  Found {$count} roles to remove.");

        if ($count === 0) {
            return 0;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $roles->chunkById(200, function ($chunk) use ($dryRun, &$bar) {
            /** @var \App\Models\Role $role */
            foreach ($chunk as $role) {
                if ($dryRun) {
                    $this->line("\n  [DRY RUN] Would delete role: {$role->id} - {$role->name}");
                } else {
                    $role->delete();
                }
                $bar->advance();
            }
        });

        $bar->finish();

        return $count;
    }
}
