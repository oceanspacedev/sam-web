<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('divisions')->insertOrIgnore([
            // PT.MSI Divisions
            [
                'id' => 1,
                'badanusaha_id' => 1,
                'code' => 'MSIS',
                'name' => 'MSIS',
            ],
            // CV.TOP Divisions
            [
                'id' => 2,
                'badanusaha_id' => 2,
                'code' => 'ORAIMO',
                'name' => 'ORAIMO',
            ],
            [
                'id' => 3,
                'badanusaha_id' => 2,
                'code' => 'TECNO',
                'name' => 'TECNO',
            ],
            [
                'id' => 4,
                'badanusaha_id' => 2,
                'code' => 'REALME',
                'name' => 'REALME',
            ],
        ]);

        $this->command->info('Division seeded successfully.');
    }
}
