<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $permissions = collect($data['permissions'] ?? [])
            ->flatMap(fn ($permission) => $permission)
            ->unique();
        session()->put('permissions_to_sync', $permissions);

        return Arr::only($data, ['name', 'can_access_web', 'organizational_scope_level']);
    }

    protected function afterCreate(): void
    {
        $permissions = session()->pull('permissions_to_sync', collect());
        $this->record->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id')->toArray()
        );
    }
}
