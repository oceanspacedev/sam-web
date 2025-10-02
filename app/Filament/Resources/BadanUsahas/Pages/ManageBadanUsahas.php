<?php

namespace App\Filament\Resources\BadanUsahas\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\BadanUsahas\BadanUsahaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBadanUsahas extends ManageRecords
{
    protected static string $resource = BadanUsahaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
