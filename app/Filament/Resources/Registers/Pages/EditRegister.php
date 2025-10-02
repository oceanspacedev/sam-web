<?php

namespace App\Filament\Resources\Registers\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Registers\RegisterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegister extends EditRecord
{
    protected static string $resource = RegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
