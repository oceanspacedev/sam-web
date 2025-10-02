<?php

namespace App\Filament\Resources\Visits\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Visits\VisitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVisit extends EditRecord
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
