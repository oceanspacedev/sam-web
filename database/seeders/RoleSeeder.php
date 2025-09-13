<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::insert([
            [
                'name' => 'TM',
            ],
            [
                'name' => 'ASC',
            ],
            [
                'name' => 'DSF',
            ],
            [
                'name' => 'AR',
            ],
            [
                'name' => 'ADMIN',
            ],
        ]);
    }
}
