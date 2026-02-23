<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // 1. Generate permissions first (Shield)
            ShieldSeeder::class,

            // 2. Seed roles (depends on Shield)
            RoleSeeder::class,

            // 3. Assign permissions to roles (depends on Roles)
            RolePermissionSeeder::class,

            // 4. Organizational hierarchy (correct order: BadanUsaha → Division → Region → Cluster)
            BadanUsahaSeeder::class,
            DivisionSeeder::class,
            RegionSeeder::class,
            ClusterSeeder::class,

            // 5. Users (depends on Roles + Clusters)
            UserSeeder::class,

            // Commented out - not needed for basic setup
            // OutletSeeder::class,
            // PlanVisitSeeder::class,
            // NooSeeder::class,
            // VisitSeeder::class,
        ]);
    }
}
