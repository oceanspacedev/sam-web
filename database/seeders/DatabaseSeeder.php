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

            // Commented out - data already exists in database
            // BadanUsahaSeeder::class,
            // DivisionSeeder::class,
            // RegionSeeder::class,
            // ClusterSeeder::class,
            // UserSeeder::class,
        ]);
    }
}
