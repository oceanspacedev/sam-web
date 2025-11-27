<?php

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->normalizePermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->normalizePermissions(reverse: true);
    }

    private function normalizePermissions(bool $reverse = false): void
    {
        $mappings = $reverse
            ? array_flip($this->getMappings())
            : $this->getMappings();

        /** @var Collection<int, Permission> $permissions */
        $permissions = Permission::with(['roles', 'users'])->get();

        foreach ($permissions as $permission) {
            $current = $permission->name;
            $target = $mappings[$current] ?? ($reverse ? $current : $this->transformToPascal($current));

            if ($current === $target) {
                continue;
            }

            /** @var Permission|null $existingTarget */
            $existingTarget = Permission::where('name', $target)
                ->where('guard_name', $permission->guard_name)
                ->first();

            if (! $existingTarget) {
                $permission->forceFill([
                    'name' => $target,
                    'description' => $this->formatDescription($target),
                ])->save();

                continue;
            }

            $existingTarget->roles()->syncWithoutDetaching($permission->roles->pluck('id'));
            $existingTarget->users()->syncWithoutDetaching($permission->users->pluck('id'));

            $permission->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function getMappings(): array
    {
        return [
            'view_any_badan_usaha' => 'ViewAny:BadanUsaha',
            'view_badan_usaha' => 'View:BadanUsaha',
            'create_badan_usaha' => 'Create:BadanUsaha',
            'update_badan_usaha' => 'Update:BadanUsaha',
            'delete_badan_usaha' => 'Delete:BadanUsaha',
            'delete_any_badan_usaha' => 'DeleteAny:BadanUsaha',
            'restore_badan_usaha' => 'Restore:BadanUsaha',
            'restore_any_badan_usaha' => 'RestoreAny:BadanUsaha',
            'force_delete_badan_usaha' => 'ForceDelete:BadanUsaha',
            'force_delete_any_badan_usaha' => 'ForceDeleteAny:BadanUsaha',
            'view_any_cluster' => 'ViewAny:Cluster',
            'view_cluster' => 'View:Cluster',
            'create_cluster' => 'Create:Cluster',
            'update_cluster' => 'Update:Cluster',
            'delete_cluster' => 'Delete:Cluster',
            'delete_any_cluster' => 'DeleteAny:Cluster',
            'restore_cluster' => 'Restore:Cluster',
            'restore_any_cluster' => 'RestoreAny:Cluster',
            'force_delete_cluster' => 'ForceDelete:Cluster',
            'force_delete_any_cluster' => 'ForceDeleteAny:Cluster',
            'view_any_division' => 'ViewAny:Division',
            'view_division' => 'View:Division',
            'create_division' => 'Create:Division',
            'update_division' => 'Update:Division',
            'delete_division' => 'Delete:Division',
            'delete_any_division' => 'DeleteAny:Division',
            'restore_division' => 'Restore:Division',
            'restore_any_division' => 'RestoreAny:Division',
            'force_delete_division' => 'ForceDelete:Division',
            'force_delete_any_division' => 'ForceDeleteAny:Division',
            'view_any_region' => 'ViewAny:Region',
            'view_region' => 'View:Region',
            'create_region' => 'Create:Region',
            'update_region' => 'Update:Region',
            'delete_region' => 'Delete:Region',
            'delete_any_region' => 'DeleteAny:Region',
            'restore_region' => 'Restore:Region',
            'restore_any_region' => 'RestoreAny:Region',
            'force_delete_region' => 'ForceDelete:Region',
            'force_delete_any_region' => 'ForceDeleteAny:Region',
            'view_any_role' => 'ViewAny:Role',
            'view_role' => 'View:Role',
            'create_role' => 'Create:Role',
            'update_role' => 'Update:Role',
            'delete_role' => 'Delete:Role',
            'delete_any_role' => 'DeleteAny:Role',
            'view_any_plan_visit' => 'ViewAny:PlanVisit',
            'view_plan_visit' => 'View:PlanVisit',
            'create_plan_visit' => 'Create:PlanVisit',
            'update_plan_visit' => 'Update:PlanVisit',
            'delete_plan_visit' => 'Delete:PlanVisit',
            'delete_any_plan_visit' => 'DeleteAny:PlanVisit',
            'restore_plan_visit' => 'Restore:PlanVisit',
            'restore_any_plan_visit' => 'RestoreAny:PlanVisit',
            'force_delete_plan_visit' => 'ForceDelete:PlanVisit',
            'force_delete_any_plan_visit' => 'ForceDeleteAny:PlanVisit',
            'export_plan_visit' => 'Export:PlanVisit',
            'view_any_visit' => 'ViewAny:Visit',
            'view_visit' => 'View:Visit',
            'create_visit' => 'Create:Visit',
            'update_visit' => 'Update:Visit',
            'delete_visit' => 'Delete:Visit',
            'delete_any_visit' => 'DeleteAny:Visit',
            'restore_visit' => 'Restore:Visit',
            'restore_any_visit' => 'RestoreAny:Visit',
            'force_delete_visit' => 'ForceDelete:Visit',
            'force_delete_any_visit' => 'ForceDeleteAny:Visit',
            'export_visit' => 'Export:Visit',
            'view_any_user' => 'ViewAny:User',
            'view_user' => 'View:User',
            'create_user' => 'Create:User',
            'update_user' => 'Update:User',
            'delete_user' => 'Delete:User',
            'delete_any_user' => 'DeleteAny:User',
            'restore_user' => 'Restore:User',
            'restore_any_user' => 'RestoreAny:User',
            'force_delete_user' => 'ForceDelete:User',
            'force_delete_any_user' => 'ForceDeleteAny:User',
            'export_user' => 'Export:User',
            'view_any_outlet' => 'ViewAny:Outlet',
            'view_outlet' => 'View:Outlet',
            'create_outlet' => 'Create:Outlet',
            'update_outlet' => 'Update:Outlet',
            'delete_outlet' => 'Delete:Outlet',
            'delete_any_outlet' => 'DeleteAny:Outlet',
            'restore_outlet' => 'Restore:Outlet',
            'restore_any_outlet' => 'RestoreAny:Outlet',
            'force_delete_outlet' => 'ForceDelete:Outlet',
            'force_delete_any_outlet' => 'ForceDeleteAny:Outlet',
            'export_outlet' => 'Export:Outlet',
            'reset_outlet' => 'Reset:Outlet',
            'view_any_register' => 'ViewAny:Register',
            'view_register' => 'View:Register',
            'create_register' => 'Create:Register',
            'update_register' => 'Update:Register',
            'delete_register' => 'Delete:Register',
            'delete_any_register' => 'DeleteAny:Register',
            'restore_register' => 'Restore:Register',
            'restore_any_register' => 'RestoreAny:Register',
            'force_delete_register' => 'ForceDelete:Register',
            'force_delete_any_register' => 'ForceDeleteAny:Register',
            'export_register' => 'Export:Register',
            'approve_register' => 'Approve:Register',
            'confirm_register' => 'Confirm:Register',
            'reject_register' => 'Reject:Register',
            'upgrade_register' => 'Upgrade:Register',
            'view_any_noo' => 'ViewAny:Register',
            'view_noo' => 'View:Register',
            'create_noo' => 'Create:Register',
            'update_noo' => 'Update:Register',
            'delete_noo' => 'Delete:Register',
            'delete_any_noo' => 'DeleteAny:Register',
            'restore_noo' => 'Restore:Register',
            'restore_any_noo' => 'RestoreAny:Register',
            'force_delete_noo' => 'ForceDelete:Register',
            'force_delete_any_noo' => 'ForceDeleteAny:Register',
            'export_noo' => 'Export:Register',
            'confirm_noo' => 'Confirm:Register',
            'approve_noo' => 'Approve:Register',
            'reject_noo' => 'Reject:Register',
            'view_data_overview' => 'View:DataOverview',
            'view_live_visit' => 'View:LiveVisit',
        ];
    }

    private function transformToPascal(string $name): string
    {
        if (str_contains($name, ':')) {
            return $name;
        }

        $parts = explode('_', $name);
        $affix = array_shift($parts);
        $subject = implode(' ', $parts);

        return Str::studly($affix).':'.Str::studly(str_replace(' ', '_', $subject));
    }

    private function formatDescription(string $permission): string
    {
        return ucwords(str_replace(['_', ':'], ' ', $permission));
    }
};
