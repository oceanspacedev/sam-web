<?php

namespace App\Filament\Exports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;

class UserExporter extends BaseExporter
{
    protected static ?string $model = User::class;

    public static function modifyQueryUsing(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->with(['role', 'divisis', 'regions', 'clusters', 'badanUsahas', 'tm']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('username')->label('Username')->default('-'),
            ExportColumn::make('nama_lengkap')->label('Nama Lengkap')->default('-'),
            ExportColumn::make('role.name')->label('Role')->default('-'),
            ExportColumn::make('divisis')
                ->label('Divisi')
                ->formatStateUsing(fn ($record) => $record->divisis->pluck('name')->join(', ') ?: '-'),
            ExportColumn::make('regions')
                ->label('Region')
                ->formatStateUsing(fn ($record) => $record->regions->pluck('name')->join(', ') ?: '-'),
            ExportColumn::make('clusters')
                ->label('Cluster')
                ->formatStateUsing(fn ($record) => $record->clusters->pluck('name')->join(', ') ?: '-'),
            ExportColumn::make('badanUsahas')
                ->label('Badan Usaha')
                ->formatStateUsing(fn ($record) => $record->badanUsahas->pluck('name')->join(', ') ?: '-'),
            ExportColumn::make('tm.nama_lengkap')->label('TM')->default('-'),
            ExportColumn::make('created_at')
                ->label('Dibuat pada')
                ->formatStateUsing(fn ($state) => $state ? date('d M Y', strtotime($state)) : '-'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor data user selesai. '.number_format($export->successful_rows).' '.str('baris')->plural($export->successful_rows).' berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' Namun, '.number_format($failedRowsCount).' '.str('baris')->plural($failedRowsCount).' gagal diekspor.';
        }

        return $body;
    }
}
