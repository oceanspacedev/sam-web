<?php

namespace App\Filament\Resources\PlanVisitResource\Pages;

use App\Filament\Resources\PlanVisitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlanVisit extends EditRecord
{
    protected static string $resource = PlanVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
