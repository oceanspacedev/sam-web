<?php

namespace App\Filament\Resources\Registers\Pages;

use App\Filament\Resources\Registers\RegisterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRegister extends EditRecord
{
    protected static string $resource = RegisterResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['ktp_outlet'] = $data['ktp_outlet'] ?? '-';
        $data['poto_ktp'] = $data['poto_ktp'] ?? '-';
        $data['kode_outlet'] = $data['kode_outlet'] ?? null;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
