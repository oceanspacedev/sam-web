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
        $roles = [
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
                'name' => 'AR',
                'organizational_scope_level' => 'all',
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
            [
                'name' => 'SALES',
                'organizational_scope_level' => 'cluster',
                'can_access_web' => false,
                'can_access_mobile' => true,
                'parent_role_id' => null,
            ],
        ];

        $insertedRoles = [];
        foreach ($roles as $roleData) {
            $roleName = $roleData['name'];
            unset($roleData['name']);

            $role = Role::withTrashed()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role) {
                if ($role->trashed()) {
                    $role->restore();
                }
                $role->update($roleData);
                $insertedRoles[$roleName] = $role;
            } else {
                $insertedRoles[$roleName] = Role::create(array_merge([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ], $roleData));
            }
        }

        // Set parent-child relationships
        $parentMappings = [
            'SALES' => 'ASM',
            'ASM' => 'ADMIN',
        ];

        foreach ($parentMappings as $childName => $parentName) {
            if (isset($insertedRoles[$childName]) && isset($insertedRoles[$parentName])) {
                $insertedRoles[$childName]->update([
                    'parent_role_id' => $insertedRoles[$parentName]->id,
                ]);
            }
        }

        $this->command->info('Roles seeded successfully. Total: ' . count($insertedRoles));
    }
}
