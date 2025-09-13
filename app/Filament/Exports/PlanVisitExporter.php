<?php

namespace App\Filament\Exports;

use App\Models\PlanVisit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PlanVisitExporter extends Exporter
{
    protected static ?string $model = PlanVisit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('user.nama_lengkap')
                ->label('Nama')
                ->default('-'),

            ExportColumn::make('outlet.divisi.name')
                ->label('Divisi')
                ->default('-'),

            ExportColumn::make('outlet.region.name')
                ->label('Region')
                ->default('-'),

            ExportColumn::make('outlet.cluster.name')
                ->label('Cluster')
                ->default('-'),

            ExportColumn::make('outlet.nama_outlet')
                ->label('Outlet')
                ->default('-'),

            ExportColumn::make('tanggal_visit')
                ->label('Tanggal')
                ->formatStateUsing(fn ($state) => $state ? date('d M Y', strtotime($state)) : '-'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export plan visit telah selesai dan '.number_format($export->successful_rows).' '.str('baris')->plural($export->successful_rows).' berhasil diexport.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('baris')->plural($failedRowsCount).' gagal diexport.';
        }

        return $body;
    }
}
