<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'view_any_badan::usaha',
            'view_badan::usaha',
            'create_badan::usaha',
            'update_badan::usaha',
            'delete_any_badan::usaha',
            'delete_badan::usaha',
            'view_any_cluster',
            'view_cluster',
            'create_cluster',
            'update_cluster',
            'delete_any_cluster',
            'delete_cluster',
            'view_any_division',
            'view_division',
            'create_division',
            'update_division',
            'delete_any_division',
            'delete_division',
            'view_any_region',
            'view_region',
            'create_region',
            'update_region',
            'delete_any_region',
            'delete_region',
            'view_any_role',
            'view_role',
            'create_role',
            'update_role',
            'delete_any_role',
            'delete_role',
            'view_any_plan::visit',
            'view_plan::visit',
            'create_plan::visit',
            'update_plan::visit',
            'delete_any_plan::visit',
            'delete_plan::visit',
            'export_plan::visit',
            'view_any_visit',
            'view_visit',
            'create_visit',
            'update_visit',
            'delete_any_visit',
            'delete_visit',
            'restore_any_visit',
            'restore_visit',
            'force_delete_any_visit',
            'force_delete_visit',
            'export_visit',
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_any_user',
            'delete_user',
            'restore_any_user',
            'restore_user',
            'force_delete_any_user',
            'force_delete_user',
            'export_user',
            'view_any_outlet',
            'view_outlet',
            'create_outlet',
            'update_outlet',
            'delete_any_outlet',
            'delete_outlet',
            'restore_any_outlet',
            'restore_outlet',
            'force_delete_any_outlet',
            'force_delete_outlet',
            'export_outlet',
            'reset_any_outlet',
            'view_any_noo',
            'view_noo',
            'create_noo',
            'update_noo',
            'delete_any_noo',
            'delete_noo',
            'restore_any_noo',
            'restore_noo',
            'force_delete_any_noo',
            'force_delete_noo',
            'export_noo',
            'confirm_noo',
            'approve_noo',
            'reject_noo',
        ];

        $superAdminRole = Role::where('name', 'SUPER ADMIN')->first();

        foreach ($permissions as $permission) {
            $perm = Permission::updateOrCreate(
                ['name' => $permission],
                ['description' => ucwords(str_replace('_', ' ', $permission))]
            );

            $superAdminRole->permissions()->syncWithoutDetaching($perm->id);
        }
    }
}
