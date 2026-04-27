<?php

namespace App\Filament\Resources\Registers\Pages;

use App\Filament\Resources\Registers\RegisterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegister extends CreateRecord
{
    protected static string $resource = RegisterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['ktp_outlet'] = $data['ktp_outlet'] ?? '-';
        $data['poto_ktp'] = $data['poto_ktp'] ?? '-';
        $data['kode_outlet'] = $data['kode_outlet'] ?? null;

        return $data;
    }
}
