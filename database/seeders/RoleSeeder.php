<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Option 1: Delete all existing roles and recreate (for fresh seed)
        // Uncomment below line if you want to recreate all roles
        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // DB::table('role_has_permissions')->truncate();
        // DB::table('roles')->truncate();
        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Option 2: Use updateOrCreate (default - safer)
        // This will update existing roles or create new ones

        // Define role hierarchy based on organizational structure
        $roles = [
            // === TOP MANAGEMENT (Full Access) ===
            [
                'name' => 'SUPER ADMIN',
                'organizational_scope_level' => 'all',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'ADMIN',
                'organizational_scope_level' => 'all',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'AUDIT',
                'organizational_scope_level' => 'all',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],

            // === VALIDATOR ROLE ===
            [
                'name' => 'AR',
                'organizational_scope_level' => 'all',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],

            // === DIVISION LEVEL MANAGEMENT ===
            [
                'name' => 'COO',
                'organizational_scope_level' => 'divisi',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'CSO',
                'organizational_scope_level' => 'divisi',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'RKAM',
                'organizational_scope_level' => 'divisi',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],

            // === REGION LEVEL MANAGEMENT ===
            [
                'name' => 'RGM',
                'organizational_scope_level' => 'region',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'ASM',
                'organizational_scope_level' => 'region',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],

            // === SALES ROLES (Cluster Level) ===
            [
                'name' => 'ASC',
                'organizational_scope_level' => 'cluster',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'DSF',
                'organizational_scope_level' => 'cluster',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'DM',
                'organizational_scope_level' => 'cluster',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'DSF/DM',
                'organizational_scope_level' => 'cluster',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'KAM',
                'organizational_scope_level' => 'cluster',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],

            // === SUPPORT ROLES ===
            [
                'name' => 'AR - BADAN USAHA',
                'organizational_scope_level' => 'badanusaha',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'DATA SUPPORT - MAJU DAN TOP',
                'organizational_scope_level' => 'badanusaha',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'DATA SUPPORT REGION',
                'organizational_scope_level' => 'region',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'DATA SUPPORT - ZTE',
                'organizational_scope_level' => 'divisi',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'DATA SUPPORT - TECNO',
                'organizational_scope_level' => 'divisi',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'DATA SUPPORT - ZTE JATENG JATIM',
                'organizational_scope_level' => 'region',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'BUSDEV',
                'organizational_scope_level' => 'badanusaha',
                'can_access_web' => true,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],

            // === SPECIAL ROLES ===
            [
                'name' => 'CSOFASTEV',
                'organizational_scope_level' => 'all',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'ASM RIAU',
                'organizational_scope_level' => 'region',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
            [
                'name' => 'AKUN DEMO',
                'organizational_scope_level' => 'cluster',
                'can_access_web' => false,
                'can_access_mobile' => false,
                'parent_role_id' => null,
            ],
        ];

        // Insert all roles using firstOrCreate
        $insertedRoles = [];
        foreach ($roles as $roleData) {
            $roleName = $roleData['name'];
            unset($roleData['name']);

            // Use withTrashed() to find soft-deleted roles too
            $role = Role::withTrashed()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role) {
                // Restore if soft-deleted and update
                if ($role->trashed()) {
                    $role->restore();
                }
                $role->update($roleData);
                $insertedRoles[$roleName] = $role;
            } else {
                // Create new role
                $insertedRoles[$roleName] = Role::create(array_merge([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ], $roleData));
            }
        }

        // Set parent-child relationships
        $parentMappings = [
            'RGM' => 'CSO',
            'ASM' => 'RGM',
            'ASC' => 'ASM',
            'DSF' => 'ASM',
            'DM' => 'ASM',
            'DSF/DM' => 'ASM',  // Legacy role name
            'AR - BADAN USAHA' => 'RGM',
            'DATA SUPPORT - MAJU DAN TOP' => 'RGM',
            'DATA SUPPORT REGION' => 'RGM',
            'AKUN DEMO' => 'SUPER ADMIN',
        ];

        foreach ($parentMappings as $childName => $parentName) {
            if (isset($insertedRoles[$childName]) && isset($insertedRoles[$parentName])) {
                $insertedRoles[$childName]->update([
                    'parent_role_id' => $insertedRoles[$parentName]->id,
                ]);
            }
        }

        $this->command->info('Roles seeded successfully.');
        $this->command->info('Total roles: ' . count($insertedRoles));
    }
}
