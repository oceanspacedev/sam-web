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
            'AUDIT' => $this->getAuditPermissions($permissions),
            'AR' => $this->getARPermissions($permissions),
            'ASM' => $this->getASMPermissions($permissions),
            'RGM' => $this->getRGMPermissions($permissions),
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
        return [
            // BadanUsaha
            'ViewAny:BadanUsaha', 'View:BadanUsaha', 'Create:BadanUsaha', 'Update:BadanUsaha',
            // Division
            'ViewAny:Division', 'View:Division', 'Create:Division', 'Update:Division',
            // Region
            'ViewAny:Region', 'View:Region', 'Create:Region', 'Update:Region',
            // Cluster
            'ViewAny:Cluster', 'View:Cluster', 'Create:Cluster', 'Update:Cluster',
            // User
            'ViewAny:User', 'View:User', 'Create:User', 'Update:User',
            'Delete:User', 'DeleteAny:User', 'Export:User',
            // Outlet
            'ViewAny:Outlet', 'View:Outlet', 'Create:Outlet', 'Update:Outlet',
            'Delete:Outlet', 'DeleteAny:Outlet', 'Export:Outlet', 'Reset:Outlet', 'ResetLocation:Outlet',
            // Register
            'ViewAny:Register', 'View:Register', 'Update:Register', 'Export:Register',
            // Visit
            'ViewAny:Visit', 'View:Visit', 'Export:Visit',
            // PlanVisit
            'ViewAny:PlanVisit', 'View:PlanVisit', 'Export:PlanVisit',
        ];
    }

    /**
     * AUDIT - Read-only access for audit purposes
     */
    private function getAuditPermissions($permissions): array
    {
        return [
            'ViewAny:User', 'View:User',
            'ViewAny:Outlet', 'View:Outlet',
            'ViewAny:Register', 'View:Register',
            'ViewAny:Visit', 'View:Visit',
            'ViewAny:PlanVisit', 'View:PlanVisit',
            'ViewAny:Division', 'View:Division',
            'ViewAny:Region', 'View:Region',
            'ViewAny:Cluster', 'View:Cluster',
        ];
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
            // Live visit access
            'View:LiveVisit', 'View:UpdateOutlet',
            // Export reports
            'Export:Visit', 'Export:PlanVisit',
        ];
    }

    /**
     * RGM - View team performance
     */
    private function getRGMPermissions($permissions): array
    {
        return [
            // User view
            'ViewAny:User', 'View:User',
            // Visit view
            'ViewAny:Visit', 'View:Visit',
        ];
    }
}
