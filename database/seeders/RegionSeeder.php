<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Hirarki: BadanUsaha → Division → Region → Cluster
        // Region punya badanusaha_id & divisi_id (konsisten dengan schema)
        // badanusaha_id diambil dari parent divisi

        $regions = [
            // MSIS Division (PT.MSI - badanusaha_id: 1)
            ['id' => 1, 'badanusaha_id' => 1, 'divisi_id' => 1, 'code' => 'SWJ', 'name' => 'SWJ'],
            ['id' => 2, 'badanusaha_id' => 1, 'divisi_id' => 1, 'code' => 'NWJ', 'name' => 'NWJ'],
            ['id' => 3, 'badanusaha_id' => 1, 'divisi_id' => 1, 'code' => 'NCJ', 'name' => 'NCJ'],
            ['id' => 4, 'badanusaha_id' => 1, 'divisi_id' => 1, 'code' => 'SCJ', 'name' => 'SCJ'],
            ['id' => 5, 'badanusaha_id' => 1, 'divisi_id' => 1, 'code' => 'JABO', 'name' => 'JABO'],

            // ORAIMO Division (CV.TOP - badanusaha_id: 2)
            ['id' => 6, 'badanusaha_id' => 2, 'divisi_id' => 2, 'code' => 'SWJ', 'name' => 'SWJ'],
            ['id' => 7, 'badanusaha_id' => 2, 'divisi_id' => 2, 'code' => 'NWJ', 'name' => 'NWJ'],
            ['id' => 8, 'badanusaha_id' => 2, 'divisi_id' => 2, 'code' => 'NCJ', 'name' => 'NCJ'],
            ['id' => 9, 'badanusaha_id' => 2, 'divisi_id' => 2, 'code' => 'SCJ', 'name' => 'SCJ'],
            ['id' => 10, 'badanusaha_id' => 2, 'divisi_id' => 2, 'code' => 'EJ', 'name' => 'EJ'],

            // TECNO Division (CV.TOP - badanusaha_id: 2)
            ['id' => 11, 'badanusaha_id' => 2, 'divisi_id' => 3, 'code' => 'NWJ', 'name' => 'NWJ'],
            ['id' => 12, 'badanusaha_id' => 2, 'divisi_id' => 3, 'code' => 'NCJ', 'name' => 'NCJ'],
            ['id' => 13, 'badanusaha_id' => 2, 'divisi_id' => 3, 'code' => 'SCJ', 'name' => 'SCJ'],

            // REALME Division (CV.TOP - badanusaha_id: 2)
            ['id' => 14, 'badanusaha_id' => 2, 'divisi_id' => 4, 'code' => 'BIGCIREBON', 'name' => 'BIGCIREBON'],
            ['id' => 15, 'badanusaha_id' => 2, 'divisi_id' => 4, 'code' => 'BIGTEGALA', 'name' => 'BIGTEGALA'],
            ['id' => 16, 'badanusaha_id' => 2, 'divisi_id' => 4, 'code' => 'BIGTEGALB', 'name' => 'BIGTEGALB'],
            ['id' => 17, 'badanusaha_id' => 2, 'divisi_id' => 4, 'code' => 'BIGSEMARANG', 'name' => 'BIGSEMARANG'],
        ];

        foreach ($regions as $region) {
            DB::table('regions')->insertOrIgnore($region);
        }

        $this->command->info('Region seeded successfully.');
    }
}
