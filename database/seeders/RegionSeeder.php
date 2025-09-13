<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Region::insert(
            [
                [
                    'badanusaha_id' => 1,
                    'divisi_id' => 1,
                    'name' => 'SWJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 2,
                    'name' => 'SWJ',
                ],
                [
                    'badanusaha_id' => 1,
                    'divisi_id' => 1,
                    'name' => 'NWJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 2,
                    'name' => 'NWJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 3,
                    'name' => 'NWJ',
                ],
                [
                    'badanusaha_id' => 1,
                    'divisi_id' => 1,
                    'name' => 'NCJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 2,
                    'name' => 'NCJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 3,
                    'name' => 'NCJ',
                ],
                [
                    'badanusaha_id' => 1,
                    'divisi_id' => 1,
                    'name' => 'SCJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 2,
                    'name' => 'SCJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 3,
                    'name' => 'SCJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 2,
                    'name' => 'EJ',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 4,
                    'name' => 'BIGCIREBON',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 4,
                    'name' => 'BIGTEGALA',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 4,
                    'name' => 'BIGTEGALB',
                ],
                [
                    'badanusaha_id' => 2,
                    'divisi_id' => 4,
                    'name' => 'BIGSEMARANG',
                ],
                [
                    'badanusaha_id' => 3,
                    'divisi_id' => 5,
                    'name' => '-',
                ],
                [
                    'badanusaha_id' => 1,
                    'divisi_id' => 1,
                    'name' => 'JABO',
                ],
            ]
        );
    }
}
