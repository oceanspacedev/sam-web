<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get roles by name (dynamic, not hardcoded IDs)
        $superAdminRole = Role::where('name', 'SUPER ADMIN')->first();
        $adminRole = Role::where('name', 'ADMIN')->first();
        $asmRole = Role::where('name', 'ASM')->first();

        if (!$superAdminRole || !$adminRole || !$asmRole) {
            $this->command->error('Required roles not found! Please run RoleSeeder first.');
            return;
        }

        $users = [
            [
                'username' => 'superadmin',
                'nama_lengkap' => 'SUPER ADMINISTRATOR',
                'role_id' => $superAdminRole->id,
                'password' => bcrypt('superadmin123'),
            ],
            [
                'username' => 'admin',
                'nama_lengkap' => 'ADMINISTRATOR',
                'role_id' => $adminRole->id,
                'password' => bcrypt('admin123'),
            ],
            [
                'username' => 'farid',
                'nama_lengkap' => 'RADEN FARID LESMANA',
                'role_id' => $asmRole->id,
                'password' => bcrypt('complete123'),
            ],
            [
                'username' => 'robby',
                'nama_lengkap' => 'ROBBY AGUSTINA',
                'role_id' => $asmRole->id,
                'password' => bcrypt('complete123'),
            ],
            [
                'username' => 'aritonang',
                'nama_lengkap' => 'RHAMA ARITONANG',
                'role_id' => $asmRole->id,
                'password' => bcrypt('complete123'),
            ],
        ];

        $clusterId = DB::table('clusters')->value('id'); // Get first cluster ID

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['username' => $userData['username']],
                [
                    'nama_lengkap' => $userData['nama_lengkap'],
                    'role_id' => $userData['role_id'],
                    'password' => $userData['password'],
                ]
            );

            // Assign to organizational units via pivot tables if cluster exists
            if ($clusterId) {
                $cluster = DB::table('clusters')->where('id', $clusterId)->first();

                if ($cluster) {
                    // Assign to divisi
                    if ($cluster->divisi_id) {
                        DB::table('user_divisi')->insertOrIgnore([
                            'user_id' => $user->id,
                            'divisi_id' => $cluster->divisi_id,
                        ]);
                    }

                    // Assign to region
                    if ($cluster->region_id) {
                        DB::table('user_regions')->insertOrIgnore([
                            'user_id' => $user->id,
                            'region_id' => $cluster->region_id,
                        ]);
                    }

                    // Assign to cluster
                    DB::table('user_clusters')->insertOrIgnore([
                        'user_id' => $user->id,
                        'cluster_id' => $clusterId,
                    ]);
                }
            }
        }

        $this->command->info('Users seeded successfully. Total: ' . User::count());
    }
}
