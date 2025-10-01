<?php

namespace App\Filament\Resources\NooResource\Pages;

use App\Filament\Resources\NooResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNoo extends EditRecord
{
    protected static string $resource = NooResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
