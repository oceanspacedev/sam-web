<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateUserOrganizationalData extends Command
{
    protected $signature = 'users:migrate-organizational-data
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate user organizational data from single foreign keys to pivot tables';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        // Migrate badanusaha_id
        $this->info('Migrating badan usaha assignments...');
        $count = 0;
        DB::table('users')
            ->whereNotNull('badanusaha_id')
            ->orderBy('id')
            ->chunk(500, function ($users) use (&$count, $dryRun) {
                $inserts = [];
                foreach ($users as $user) {
                    // Check if not already migrated
                    $exists = DB::table('user_badan_usaha')
                        ->where('user_id', $user->id)
                        ->where('badanusaha_id', $user->badanusaha_id)
                        ->exists();

                    if (! $exists) {
                        $inserts[] = [
                            'user_id' => $user->id,
                            'badanusaha_id' => $user->badanusaha_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $count++;
                    }
                }
                if (! empty($inserts) && ! $dryRun) {
                    DB::table('user_badan_usaha')->insert($inserts);
                }
            });
        $this->info("Migrated {$count} badan usaha assignments");

        // Migrate divisi_id
        $this->info('Migrating divisi assignments...');
        $count = 0;
        DB::table('users')
            ->whereNotNull('divisi_id')
            ->orderBy('id')
            ->chunk(500, function ($users) use (&$count, $dryRun) {
                $inserts = [];
                foreach ($users as $user) {
                    $exists = DB::table('user_divisi')
                        ->where('user_id', $user->id)
                        ->where('divisi_id', $user->divisi_id)
                        ->exists();

                    if (! $exists) {
                        $inserts[] = [
                            'user_id' => $user->id,
                            'divisi_id' => $user->divisi_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $count++;
                    }
                }
                if (! empty($inserts) && ! $dryRun) {
                    DB::table('user_divisi')->insert($inserts);
                }
            });
        $this->info("Migrated {$count} divisi assignments");

        // Migrate region_id
        $this->info('Migrating region assignments...');
        $count = 0;
        DB::table('users')
            ->whereNotNull('region_id')
            ->orderBy('id')
            ->chunk(500, function ($users) use (&$count, $dryRun) {
                $inserts = [];
                foreach ($users as $user) {
                    $exists = DB::table('user_regions')
                        ->where('user_id', $user->id)
                        ->where('region_id', $user->region_id)
                        ->exists();

                    if (! $exists) {
                        $inserts[] = [
                            'user_id' => $user->id,
                            'region_id' => $user->region_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $count++;
                    }
                }
                if (! empty($inserts) && ! $dryRun) {
                    DB::table('user_regions')->insert($inserts);
                }
            });
        $this->info("Migrated {$count} region assignments");

        // Migrate cluster_id
        $this->info('Migrating cluster assignments...');
        $count = 0;
        DB::table('users')
            ->whereNotNull('cluster_id')
            ->orderBy('id')
            ->chunk(500, function ($users) use (&$count, $dryRun) {
                $inserts = [];
                foreach ($users as $user) {
                    $exists = DB::table('user_clusters')
                        ->where('user_id', $user->id)
                        ->where('cluster_id', $user->cluster_id)
                        ->exists();

                    if (! $exists) {
                        $inserts[] = [
                            'user_id' => $user->id,
                            'cluster_id' => $user->cluster_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $count++;
                    }
                }
                if (! empty($inserts) && ! $dryRun) {
                    DB::table('user_clusters')->insert($inserts);
                }
            });
        $this->info("Migrated {$count} cluster assignments");

        // Migrate cluster_id2
        $this->info('Migrating secondary cluster assignments...');
        $count = 0;
        DB::table('users')
            ->whereNotNull('cluster_id2')
            ->orderBy('id')
            ->chunk(500, function ($users) use (&$count, $dryRun) {
                $inserts = [];
                foreach ($users as $user) {
                    // Only if different from primary cluster
                    if ($user->cluster_id !== $user->cluster_id2) {
                        $exists = DB::table('user_clusters')
                            ->where('user_id', $user->id)
                            ->where('cluster_id', $user->cluster_id2)
                            ->exists();

                        if (! $exists) {
                            $inserts[] = [
                                'user_id' => $user->id,
                                'cluster_id' => $user->cluster_id2,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                            $count++;
                        }
                    }
                }
                if (! empty($inserts) && ! $dryRun) {
                    DB::table('user_clusters')->insert($inserts);
                }
            });
        $this->info("Migrated {$count} secondary cluster assignments");

        $this->info('✅ Migration complete!');

        return Command::SUCCESS;
    }
}
