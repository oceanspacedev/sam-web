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
            // OutletSeeder::class,
            // PlanVisitSeeder::class,
            // NooSeeder::class,
            // VisitSeeder::class,
            ClusterSeeder::class,
            RegionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            DivisionSeeder::class,
            BadanUsahaSeeder::class,
        ]);
    }
}
