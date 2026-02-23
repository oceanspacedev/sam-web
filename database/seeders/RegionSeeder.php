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
        $regions = [
            // SWJ
            ['id' => 1, 'badanusaha_id' => 1, 'divisi_id' => 1, 'name' => 'SWJ'],
            ['id' => 2, 'badanusaha_id' => 2, 'divisi_id' => 2, 'name' => 'SWJ'],
            // NWJ
            ['id' => 3, 'badanusaha_id' => 1, 'divisi_id' => 1, 'name' => 'NWJ'],
            ['id' => 4, 'badanusaha_id' => 2, 'divisi_id' => 2, 'name' => 'NWJ'],
            ['id' => 5, 'badanusaha_id' => 2, 'divisi_id' => 3, 'name' => 'NWJ'],
            // NCJ
            ['id' => 6, 'badanusaha_id' => 1, 'divisi_id' => 1, 'name' => 'NCJ'],
            ['id' => 7, 'badanusaha_id' => 2, 'divisi_id' => 2, 'name' => 'NCJ'],
            ['id' => 8, 'badanusaha_id' => 2, 'divisi_id' => 3, 'name' => 'NCJ'],
            // SCJ
            ['id' => 9, 'badanusaha_id' => 1, 'divisi_id' => 1, 'name' => 'SCJ'],
            ['id' => 10, 'badanusaha_id' => 2, 'divisi_id' => 2, 'name' => 'SCJ'],
            ['id' => 11, 'badanusaha_id' => 2, 'divisi_id' => 3, 'name' => 'SCJ'],
            // EJ
            ['id' => 12, 'badanusaha_id' => 2, 'divisi_id' => 2, 'name' => 'EJ'],
            // BIGCIREBON
            ['id' => 13, 'badanusaha_id' => 2, 'divisi_id' => 4, 'name' => 'BIGCIREBON'],
            ['id' => 14, 'badanusaha_id' => 2, 'divisi_id' => 4, 'name' => 'BIGTEGALA'],
            ['id' => 15, 'badanusaha_id' => 2, 'divisi_id' => 4, 'name' => 'BIGTEGALB'],
            ['id' => 16, 'badanusaha_id' => 2, 'divisi_id' => 4, 'name' => 'BIGSEMARANG'],
            // JABO
            ['id' => 17, 'badanusaha_id' => 1, 'divisi_id' => 1, 'name' => 'JABO'],
        ];

        foreach ($regions as $region) {
            DB::table('regions')->insertOrIgnore($region);
        }

        $this->command->info('Region seeded successfully.');
    }
}
