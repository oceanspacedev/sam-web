<?php

namespace App\Filament\Resources\Registers\Pages;

use App\Filament\Exports\RegisterExporter;
use App\Filament\Resources\Registers\RegisterResource;
use App\Models\Register;
use App\Support\FilamentTabBadgeCounts;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListRegisters extends ListRecords
{
    protected static string $resource = RegisterResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make(),
        ];

        // Check if the user is authorized to export
        if (Gate::allows('export', Register::class)) {
            $actions[] = ExportAction::make()
                ->exporter(RegisterExporter::class)
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->label('Export');
        }

        return $actions;
    }

    public function getTabs(): array
    {
        $query = RegisterResource::getEloquentQuery();
        $counts = FilamentTabBadgeCounts::registerStatusCounts($query);

        return [
            'pending' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'PENDING')->where('type', 'NOO'))
                ->badge($counts['pending_noo'])
                ->badgeColor('warning'),

            'confirmed' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'CONFIRMED')->where('type', 'NOO'))
                ->badge($counts['confirmed_noo'])
                ->badgeColor('info'),

            'approved' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'APPROVED')->where('type', 'NOO'))
                ->badge($counts['approved_noo'])
                ->badgeColor('success'),

            'rejected' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'REJECTED')->where('type', 'NOO'))
                ->badge($counts['rejected_noo'])
                ->badgeColor('danger'),

            'lead' => Tab::make()
                ->label('LEAD')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'PENDING')->where('type', 'LEAD'))
                ->badge($counts['pending_lead'])
                ->badgeColor('primary'),
        ];
    }
}
