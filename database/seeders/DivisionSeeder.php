<?php

namespace Database\Seeders;

use App\Models\Division;
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
        // Use insertOrIgnore to avoid duplicate errors
        DB::table('divisions')->insertOrIgnore([
            [
                'id' => 1,
                'badanusaha_id' => 1,
                'name' => 'MSIS',
            ],
            [
                'id' => 2,
                'badanusaha_id' => 2,
                'name' => 'ORAIMO',
            ],
            [
                'id' => 3,
                'badanusaha_id' => 2,
                'name' => 'TECNO',
            ],
            [
                'id' => 4,
                'badanusaha_id' => 2,
                'name' => 'REALME',
            ],
        ]);

        $this->command->info('Division seeded successfully.');
    }
}
