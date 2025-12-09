<?php

namespace App\Filament\Exports;

use App\Models\Register;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;

class RegisterExporter extends BaseExporter
{
    protected static ?string $model = Register::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('kode_outlet')->label('Kode Outlet')->default('-'),
            ExportColumn::make('nama_outlet')->label('Nama Outlet')->default('-'),
            ExportColumn::make('alamat_outlet')->label('Alamat')->default('-'),
            ExportColumn::make('distric')->label('Distrik')->default('-'),
            ExportColumn::make('badanusaha.name')->label('Badan Usaha')->default('-'),
            ExportColumn::make('divisi.name')->label('Divisi')->default('-'),
            ExportColumn::make('region.name')->label('Region')->default('-'),
            ExportColumn::make('cluster.name')->label('Cluster')->default('-'),
            ExportColumn::make('tm.nama_lengkap')->label('TM')->default('-'),
            ExportColumn::make('latlong')->label('Latlong')->default('-'),
            ExportColumn::make('limit')->label('Limit')->default('-'),
            ExportColumn::make('status')->label('Status')->default('-'),
            ExportColumn::make('poto_shop_sign')->label('Foto Shop Sign')->formatStateUsing(fn ($s) => static::storageImageFormula($s)),
            ExportColumn::make('poto_depan')->label('Foto Depan')->formatStateUsing(fn ($s) => static::storageImageFormula($s)),
            ExportColumn::make('poto_kiri')->label('Foto Kiri')->formatStateUsing(fn ($s) => static::storageImageFormula($s)),
            ExportColumn::make('poto_kanan')->label('Foto Kanan')->formatStateUsing(fn ($s) => static::storageImageFormula($s)),
            ExportColumn::make('poto_ktp')->label('Foto KTP')->formatStateUsing(fn ($s) => static::storageImageFormula($s)),
            ExportColumn::make('video')->label('Video')->formatStateUsing(fn ($s) => static::storageImageFormula($s)),
            ExportColumn::make('created_at')->label('Dibuat pada')->formatStateUsing(fn ($s) => static::formatDateTimeValue($s, 'd M Y')),
            ExportColumn::make('updated_at')->label('Diperbarui pada')->formatStateUsing(fn ($s) => static::formatDateTimeValue($s, 'd M Y')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor data register selesai. '.number_format($export->successful_rows).' '.str('baris')->plural($export->successful_rows).' berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' Namun, '.number_format($failedRowsCount).' '.str('baris')->plural($failedRowsCount).' gagal diekspor.';
        }

        return $body;
    }
}
