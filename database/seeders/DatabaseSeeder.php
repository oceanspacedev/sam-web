<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // Run ShieldSeeder first to generate permissions
            ShieldSeeder::class,

            // Then seed roles
            RoleSeeder::class,

            // Then assign permissions to roles
            RolePermissionSeeder::class,

            // Other seeders
            ClusterSeeder::class,
            RegionSeeder::class,
            DivisionSeeder::class,
            BadanUsahaSeeder::class,
            UserSeeder::class,

            // Commented out for now
            // OutletSeeder::class,
            // PlanVisitSeeder::class,
            // NooSeeder::class,
            // VisitSeeder::class,
        ]);
    }
}
