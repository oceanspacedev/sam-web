<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Exports\VisitExporter;
use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
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

        // // Check if the user is authorized to export
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
        // Ambil query yang sudah difilter berdasarkan role
        $query = VisitResource::getEloquentQuery(); // Panggil getEloquentQuery() dari Resource

        return [
            'all' => Tab::make(),

            'PLANNED' => Tab::make('PLANNED')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipe_visit', 'PLANNED'))
                ->badge($this->getStatusBadgeCount($query, 'PLANNED'))
                ->badgeColor('primary'),

            'EXTRACALL' => Tab::make('EXTRACALL')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipe_visit', 'EXTRACALL'))
                ->badge($this->getStatusBadgeCount($query, 'EXTRACALL'))
                ->badgeColor('info'),
        ];
    }

    // Fungsi untuk menghitung jumlah berdasarkan status dengan filter yang sudah diterapkan
    private function getStatusBadgeCount(Builder $query, ?string $status): int
    {
        // Jika status tidak diberikan (null), hitung semua data
        if ($status === null) {
            return $query->clone()->count(); // Hitung semua data
        }

        return $query->clone()->where('tipe_visit', $status)->count(); // Hitung berdasarkan status
    }
}
