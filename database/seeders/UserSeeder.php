<?php

namespace Database\Seeders;

use App\Models\User;
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
        $users = [
            [
                'username' => 'farid',
                'nama_lengkap' => 'RADEN FARID LESMANA',
                'role_id' => 1, // ASM
                'password' => bcrypt('complete123'),
                // Assign to cluster
                'cluster_id' => 1, // Will be assigned via pivot
            ],
            [
                'username' => 'robby',
                'nama_lengkap' => 'ROBBY AGUSTINA',
                'role_id' => 1, // ASM
                'password' => bcrypt('complete123'),
            ],
            [
                'username' => 'aritonang',
                'nama_lengkap' => 'RHAMA ARITONANG',
                'role_id' => 1, // ASM
                'password' => bcrypt('complete123'),
            ],
            [
                'username' => 'admin',
                'nama_lengkap' => 'ADMINISTRATOR',
                'role_id' => 5, // ADMIN
                'password' => bcrypt('admin123'),
            ],
            [
                'username' => 'superadmin',
                'nama_lengkap' => 'SUPER ADMINISTRATOR',
                'role_id' => 13, // SUPER ADMIN
                'password' => bcrypt('superadmin123'),
            ],
        ];

        foreach ($users as $userData) {
            $clusterId = $userData['cluster_id'] ?? null;
            unset($userData['cluster_id']);

            $user = User::create($userData);

            // Assign to organizational units via pivot tables if cluster_id is set
            if ($clusterId) {
                // Get cluster info to determine badan_usaha, divisi, region
                $cluster = DB::table('clusters')->where('id', $clusterId)->first();

                if ($cluster) {
                    // Assign to badan_usaha
                    if ($cluster->badanusaha_id) {
                        DB::table('user_badan_usaha')->insert([
                            'user_id' => $user->id,
                            'badan_usahas_id' => $cluster->badanusaha_id,
                        ]);
                    }

                    // Assign to divisi
                    if ($cluster->divisi_id) {
                        DB::table('user_divisi')->insert([
                            'user_id' => $user->id,
                            'divisions_id' => $cluster->divisi_id,
                        ]);
                    }

                    // Assign to region
                    if ($cluster->region_id) {
                        DB::table('user_regions')->insert([
                            'user_id' => $user->id,
                            'regions_id' => $cluster->region_id,
                        ]);
                    }

                    // Assign to cluster
                    DB::table('user_clusters')->insert([
                        'user_id' => $user->id,
                        'clusters_id' => $clusterId,
                    ]);
                }
            }
        }

        $this->command->info('Users seeded successfully.');
    }
}
