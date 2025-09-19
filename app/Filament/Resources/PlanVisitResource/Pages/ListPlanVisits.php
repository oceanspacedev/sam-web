<?php

namespace App\Filament\Resources\PlanVisitResource\Pages;

use App\Filament\Exports\PlanVisitExporter;
use App\Filament\Resources\PlanVisitResource;
use App\Models\PlanVisit;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListPlanVisits extends ListRecords
{
    protected static string $resource = PlanVisitResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Check if the user is authorized to export
        if (Gate::allows('export', PlanVisit::class)) {
            $actions[] = ExportAction::make()
                ->exporter(PlanVisitExporter::class)
                ->label('Export')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down');
        }

        return $actions;
    }
}
