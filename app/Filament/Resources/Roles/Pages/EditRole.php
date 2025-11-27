<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
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
            'organizational_scope_level' => $data['organizational_scope_level'],
        ];
    }

    protected function afterSave(): void
    {
        $permissionModels = $this->permissions
            ->map(fn (string $permission): Permission => Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $this->record->guard_name ?? config('auth.defaults.guard'),
            ]));

        $this->record->syncPermissions($permissionModels);
    }
}
