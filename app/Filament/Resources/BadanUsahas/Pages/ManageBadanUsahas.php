<?php

namespace App\Filament\Resources\BadanUsahaResource\Pages;

use App\Filament\Resources\BadanUsahaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBadanUsahas extends ManageRecords
{
    protected static string $resource = BadanUsahaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
