<?php

namespace App\Filament\Resources\PlanVisits\Pages;

use App\Filament\Resources\PlanVisits\PlanVisitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanVisit extends EditRecord
{
    protected static string $resource = PlanVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
