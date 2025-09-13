<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\Permission;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $permissions = collect($data['permissions'] ?? [])
            ->flatMap(fn ($permission) => $permission)
            ->unique();
        session()->put('permissions_to_sync', $permissions);

        return Arr::only($data, ['name', 'can_access_web', 'filter_type', 'filter_data']);
    }

    protected function afterSave(): void
    {
        $permissions = session()->pull('permissions_to_sync', collect());
        $this->record->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id')->toArray()
        );
    }
}
