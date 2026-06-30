<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadanUsahaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('badan_usahas')->insertOrIgnore([
            [
                'id' => 1,
                'code' => 'PT.MSI',
                'name' => 'PT.MSI',
            ],
            [
                'id' => 2,
                'code' => 'CV.TOP',
                'name' => 'CV.TOP',
            ],
        ]);

        $this->command->info('BadanUsaha seeded successfully.');
    }
}
