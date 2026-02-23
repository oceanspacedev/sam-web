<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clear existing role-permission assignments
        DB::table('role_has_permissions')->delete();

        // Get all permissions as array
        $permissions = DB::table('permissions')->pluck('id', 'name')->toArray();

        // Define permissions for each role
        $rolePermissions = [
            'SUPER ADMIN' => $this->getSuperAdminPermissions($permissions),
            'ADMIN' => $this->getAdminPermissions($permissions),
            'AR' => $this->getARPermissions($permissions),
            'ASM' => $this->getASMPermissions($permissions),
            'SALES' => $this->getSalesPermissions($permissions),
        ];

        // Assign permissions to roles
        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                $this->command->warn("Role {$roleName} not found!");
                continue;
            }

            $permissionIds = [];
            foreach ($permissionNames as $permissionName) {
                if (isset($permissions[$permissionName])) {
                    $permissionIds[] = $permissions[$permissionName];
                }
            }

            if (!empty($permissionIds)) {
                $role->permissions()->sync($permissionIds);
                $this->command->info("Assigned " . count($permissionIds) . " permissions to {$roleName}");
            }
        }

        $this->command->info('Role permissions seeded successfully.');
    }

    /**
     * SUPER ADMIN - Full access to everything
     */
    private function getSuperAdminPermissions($permissions): array
    {
        return array_keys($permissions); // All permissions
    }

    /**
     * ADMIN - Manage master data, users, reports
     */
    private function getAdminPermissions($permissions): array
    {
        $all = array_keys($permissions);
        // Remove some sensitive/admin-only permissions if needed
        return $all;
    }

    /**
     * AR - Validator Register (Approve/Reject/Confirm)
     */
    private function getARPermissions($permissions): array
    {
        return [
            // Register validation permissions
            'ViewAny:Register', 'View:Register', 'Update:Register',
            'Confirm:Register', 'Approve:Register', 'Reject:Register',
            'Export:Register',
            // Outlet access (to view during validation)
            'ViewAny:Outlet', 'View:Outlet', 'Update:Outlet',
            // Visit access
            'ViewAny:Visit', 'View:Visit',
        ];
    }

    /**
     * ASM - Operasional lapangan (monitor visit, manage team)
     */
    private function getASMPermissions($permissions): array
    {
        return [
            // User management for team
            'ViewAny:User', 'View:User', 'Create:User', 'Update:User', 'Delete:User',
            // Outlet management
            'ViewAny:Outlet', 'View:Outlet', 'Reset:Outlet', 'ResetLocation:Outlet',
            // Visit monitoring
            'ViewAny:Visit', 'View:Visit',
            'ViewAny:PlanVisit', 'View:PlanVisit',
            // Export reports
            'Export:Visit', 'Export:PlanVisit',
        ];
    }

    /**
     * SALES - Mobile access for field operations
     */
    private function getSalesPermissions($permissions): array
    {
        return [
            // Outlet access
            'ViewAny:Outlet', 'View:Outlet',
            // Register access
            'ViewAny:Register', 'View:Register', 'Create:Register',
            // Visit access
            'ViewAny:Visit', 'View:Visit', 'Create:Visit', 'Update:Visit',
            // PlanVisit access
            'ViewAny:PlanVisit', 'View:PlanVisit', 'Create:PlanVisit', 'Update:PlanVisit', 'Delete:PlanVisit',
        ];
    }
}
