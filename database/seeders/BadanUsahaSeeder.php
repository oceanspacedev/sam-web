<?php

namespace Database\Seeders;

use App\Models\BadanUsaha;
use Illuminate\Database\Seeder;

class BadanUsahaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        BadanUsaha::insert([
            [
                'name' => 'PT.MSI',
            ],
            [
                'name' => 'CV.TOP',
            ],
            [
                'name' => '-',
            ],
        ]);
    }
}
