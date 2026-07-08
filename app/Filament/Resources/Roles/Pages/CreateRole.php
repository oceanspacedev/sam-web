<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Collection;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected Collection $permissions;

    protected function mutateFormDataBeforeCreate(array $data): array
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

    protected function afterCreate(): void
    {
        $permissionModels = $this->permissions
            ->map(fn (string $permission): Permission => $this->findOrRestorePermission($permission));

        $this->record->syncPermissions($permissionModels);
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
