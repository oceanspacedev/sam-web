<?php

namespace Database\Seeders;

use App\Models\BadanUsaha;
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
        // Use insertOrIgnore to avoid duplicate errors
        DB::table('badan_usahas')->insertOrIgnore([
            [
                'id' => 1,
                'name' => 'PT.MSI',
            ],
            [
                'id' => 2,
                'name' => 'CV.TOP',
            ],
            [
                'id' => 3,
                'name' => '-',
            ],
        ]);

        $this->command->info('BadanUsaha seeded successfully.');
    }
}
