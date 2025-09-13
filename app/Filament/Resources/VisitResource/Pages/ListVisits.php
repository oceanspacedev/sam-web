<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Exports\VisitExporter;
use App\Filament\Resources\VisitResource;
use App\Models\Visit;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // // Check if the user is authorized to export
        if (Gate::allows('export', Visit::class)) {
            // $actions[] = Actions\Action::make('export')
            //     ->color("success")
            //     ->icon('heroicon-o-arrow-up-tray')
            //     ->form([
            //         DatePicker::make('tanggal1')
            //             ->label('Dari')
            //             ->maxDate(now())
            //             ->required(),
            //         DatePicker::make('tanggal2')
            //             ->label('Sampai')
            //             ->maxDate(now())
            //             ->required(),
            //     ])
            //     ->modalWidth('md')
            //     ->modalHeading('Export Data')
            //     ->modalSubheading('Pilih periode untuk export data')
            //     ->modalButton('Export')
            //     ->action(function (array $data) {
            //         // After form is submitted, redirect to the export route
            //         return redirect()->route('visit.export', [
            //             'tanggal1' => $data['tanggal1'],
            //             'tanggal2' => $data['tanggal2'],
            //         ]);
            //     });
            $actions[] = ExportAction::make()
                ->exporter(VisitExporter::class)
                ->label('Export Filament')
                ->color('info')
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

            'EXTRACALL' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipe_visit', 'EXTRACALL'))
                ->badge($this->getStatusBadgeCount($query, 'EXTRACALL'))
                ->badgeColor('warning'),

            'PLANNED' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('tipe_visit', 'PLANNED'))
                ->badge($this->getStatusBadgeCount($query, 'PLANNED'))
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
