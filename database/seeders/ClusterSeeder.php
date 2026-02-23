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
        // Only seed if clusters table is empty
        $count = DB::table('clusters')->count();
        if ($count > 0) {
            $this->command->info('Clusters already exist (' . $count . ' rows). Skipping...');
            return;
        }

        // Hirarki: BadanUsaha → Division → Region → Cluster
        // Setiap cluster punya badanusaha_id, divisi_id, region_id yang konsisten

        DB::table('clusters')->insert([
            // MSIS (PT.MSI) - SWJ Region
            ['badanusaha_id' => 1, 'divisi_id' => 1, 'region_id' => 1, 'name' => 'CSW1'],
            ['badanusaha_id' => 1, 'divisi_id' => 1, 'region_id' => 1, 'name' => 'CSW2'],
            // MSIS - NWJ Region
            ['badanusaha_id' => 1, 'divisi_id' => 1, 'region_id' => 2, 'name' => 'CNW1'],

            // ORAIMO (CV.TOP) - SWJ Region
            ['badanusaha_id' => 2, 'divisi_id' => 2, 'region_id' => 6, 'name' => 'OSW1'],
            ['badanusaha_id' => 2, 'divisi_id' => 2, 'region_id' => 6, 'name' => 'OSW2'],
            // ORAIMO - NWJ Region
            ['badanusaha_id' => 2, 'divisi_id' => 2, 'region_id' => 7, 'name' => 'ONW1'],

            // TECNO (CV.TOP) - NWJ Region
            ['badanusaha_id' => 2, 'divisi_id' => 3, 'region_id' => 11, 'name' => 'TNW1'],

            // REALME (CV.TOP) - BIGCIREBON Region
            ['badanusaha_id' => 2, 'divisi_id' => 4, 'region_id' => 14, 'name' => 'RBIG1'],
        ]);

        $this->command->info('Cluster seeded successfully.');
    }
}
