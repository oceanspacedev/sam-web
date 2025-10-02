<?php

namespace App\Filament\Resources\Divisions\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Divisions\DivisionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDivisions extends ManageRecords
{
    protected static string $resource = DivisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
