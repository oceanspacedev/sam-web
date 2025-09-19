<?php

namespace App\Filament\Exports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class UserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('username')->label('Username')->default('-'),
            ExportColumn::make('nama_lengkap')->label('Nama Lengkap')->default('-'),
            ExportColumn::make('role.name')->label('Role')->default('-'),
            ExportColumn::make('divisi.name')->label('Divisi')->default('-'),
            ExportColumn::make('region.name')->label('Region')->default('-'),
            ExportColumn::make('cluster.name')->label('Cluster')->default('-'),
            ExportColumn::make('badanusaha.name')->label('Badan Usaha')->default('-'),
            ExportColumn::make('tm.nama_lengkap')->label('TM')->default('-'),
            ExportColumn::make('id_notif')->label('ID Notif')->default('-'),
            ExportColumn::make('created_at')
                ->label('Dibuat pada')
                ->formatStateUsing(fn ($state) => $state ? date('d M Y', strtotime($state)) : '-'),
            ExportColumn::make('deleted_at')
                ->label('Dihapus pada')
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
