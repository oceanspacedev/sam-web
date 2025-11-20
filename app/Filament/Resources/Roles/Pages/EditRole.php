<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $permissions = collect($data['permissions'] ?? [])
            ->flatMap(fn ($permission) => $permission)
            ->unique();
        session()->put('permissions_to_sync', $permissions);

        return [
            'name' => $data['name'],
            'parent_role_id' => $data['parent_role_id'] ?? null,
            'can_access_web' => $data['can_access_web'],
            'organizational_scope_level' => $data['organizational_scope_level'],
        ];
    }

    protected function afterSave(): void
    {
        $permissions = session()->pull('permissions_to_sync', collect());
        $this->record->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id')->toArray()
        );
    }
}
