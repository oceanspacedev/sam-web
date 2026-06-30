<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Exports\VisitExporter;
use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
use App\Support\FilamentTabBadgeCounts;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make(),
        ];

        if (Gate::allows('export', Visit::class)) {
            $actions[] = ExportAction::make()
                ->exporter(VisitExporter::class)
                ->label('Export')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down');
        }

        return $actions;
    }

    public function getTabs(): array
    {
        $query = VisitResource::getEloquentQuery();
        $counts = FilamentTabBadgeCounts::visitTypeCounts($query);

        return [
            'all' => Tab::make(),

            'PLANNED' => Tab::make('PLANNED')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipe_visit', 'PLANNED'))
                ->badge($counts['planned'])
                ->badgeColor('primary'),

            'EXTRACALL' => Tab::make('EXTRACALL')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipe_visit', 'EXTRACALL'))
                ->badge($counts['extracall'])
                ->badgeColor('info'),
        ];
    }
}
