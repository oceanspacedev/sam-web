<?php

namespace Database\Seeders;

use App\Models\Cluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClusterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Only seed if clusters table is empty (for fresh migration)
        $count = DB::table('clusters')->count();
        if ($count > 0) {
            $this->command->info('Clusters already exist (' . $count . ' rows). Skipping...');
            return;
        }

        // Sample clusters for testing - minimal data
        DB::table('clusters')->insert([
            [
                'badanusaha_id' => 1,
                'divisi_id' => 1,
                'region_id' => 1,
                'name' => 'CSW1',
            ],
            [
                'badanusaha_id' => 1,
                'divisi_id' => 1,
                'region_id' => 1,
                'name' => 'CSW2',
            ],
            [
                'badanusaha_id' => 2,
                'divisi_id' => 2,
                'region_id' => 2,
                'name' => 'CWO1',
            ],
            [
                'badanusaha_id' => 2,
                'divisi_id' => 2,
                'region_id' => 2,
                'name' => 'CWO2',
            ],
        ]);

        $this->command->info('Cluster seeded successfully.');
    }
}
