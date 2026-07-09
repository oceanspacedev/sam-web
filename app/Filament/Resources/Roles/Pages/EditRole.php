<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
use App\Models\User;
use App\Support\UserOrganizationalScopeCleanup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected Collection $permissions;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissions = collect($data)
            ->except([
                'name',
                'parent_role_id',
                'can_access_web',
                'can_access_mobile',
                'organizational_scope_level',
                'select_all',
                'guard_name',
            ])
            ->values()
            ->flatten()
            ->filter()
            ->unique();

        return [
            'name' => $data['name'],
            'parent_role_id' => $data['parent_role_id'] ?? null,
            'can_access_web' => $data['can_access_web'],
            'can_access_mobile' => $data['can_access_mobile'],
            'organizational_scope_level' => $data['organizational_scope_level'],
        ];
    }

    protected function afterSave(): void
    {
        $permissionModels = $this->permissions
            ->map(fn (string $permission): Permission => $this->findOrRestorePermission($permission));

        $this->record->syncPermissions($permissionModels);

        // Saat scope level role di-coarsen (mis. cluster -> region), pivot
        // finer-level user lama harus di-prune agar tidak menyempitkan scope
        // di luar yang dimaksud (FilamentOrganizationalScope mengikuti scope_level).
        if ($this->record->wasChanged('organizational_scope_level')) {
            User::where('role_id', $this->record->id)
                ->chunkById(200, function (Collection $users): void {
                    foreach ($users as $user) {
                        UserOrganizationalScopeCleanup::pruneAssignments($user);
                    }
                });
        }
    }

    private function findOrRestorePermission(string $name): Permission
    {
        $permission = Permission::withTrashed()->firstOrNew([
            'name' => $name,
        ]);

        $permission->name = $name;
        $permission->guard_name = $this->record->guard_name ?? config('auth.defaults.guard');

        if ($permission->trashed()) {
            $permission->restore();
        }

        $permission->save();

        return $permission;
    }
}
