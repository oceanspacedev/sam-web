<?php

namespace App\Filament\Resources\PlanVisits\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\PlanVisits\PlanVisitResource;
use Filament\Actions;
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
