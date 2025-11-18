<?php

namespace App\Filament\Exports;

use App\Models\PlanVisit;
use Carbon\Carbon;
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

            ExportColumn::make('period_start')
                ->label('Tanggal')
                ->formatStateUsing(function ($state, PlanVisit $record) {
                    if ($record->isWeekly() && $record->period_start && $record->period_end) {
                        $start = Carbon::parse($record->period_start)->format('d M Y');
                        $end = Carbon::parse($record->period_end)->format('d M Y');

                        return $start.' - '.$end;
                    }

                    return $state ? Carbon::parse($state)->format('d M Y') : '-';
                }),
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
