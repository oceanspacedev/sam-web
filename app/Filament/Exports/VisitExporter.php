<?php

namespace App\Filament\Exports;

use App\Models\Visit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class VisitExporter extends Exporter
{
    protected static ?string $model = Visit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('tanggal_visit')
                ->label('Tanggal')
                ->formatStateUsing(fn ($state) => $state ? date('d M Y', strtotime($state)) : '-'),
            
            ExportColumn::make('user.nama_lengkap')
                ->label('Nama')
                ->default('-'),
            
            ExportColumn::make('user.role.name')
                ->label('Role')
                ->default('-'),
            
            ExportColumn::make('outlet.kode_outlet')
                ->label('Kode Outlet')
                ->default('-'),
            
            ExportColumn::make('outlet.nama_outlet')
                ->label('Outlet')
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
            
            ExportColumn::make('tipe_visit')
                ->label('Tipe')
                ->default('-'),
            
            ExportColumn::make('picture_visit_in')
                ->label('Foto CI')
                ->formatStateUsing(fn ($state) => $state ? "https://grosir.mediaselularindonesia.com/storage/" . $state : '-'),
            
            ExportColumn::make('picture_visit_out')
                ->label('Foto CO')
                ->formatStateUsing(fn ($state) => $state ? "https://grosir.mediaselularindonesia.com/storage/" . $state : '-'),
            
            ExportColumn::make('latlong_in')
                ->label('Lokasi CI')
                ->formatStateUsing(fn ($state) => $state ? "https://www.google.com/maps/place/" . $state : '-'),
            
            ExportColumn::make('latlong_out')
                ->label('Lokasi CO')
                ->formatStateUsing(fn ($state) => $state ? "https://www.google.com/maps/place/" . $state : '-'),
            
            ExportColumn::make('check_in_time')
                ->label('Jam CI')
                ->formatStateUsing(fn ($state) => $state ? date('H:i', strtotime($state)) : '-'),
            
            ExportColumn::make('check_out_time')
                ->label('Jam CO')
                ->formatStateUsing(fn ($state) => $state ? date('H:i', strtotime($state)) : '-'),
            
            ExportColumn::make('durasi_visit')
                ->label('Durasi')
                ->formatStateUsing(fn ($state) => $state ? $state . ' Menit' : '-'),
            
            ExportColumn::make('transaksi')
                ->label('Transaksi')
                ->default('-'),
            
            ExportColumn::make('laporan_visit')
                ->label('Laporan')
                ->default('-'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export visit telah selesai dan ' . number_format($export->successful_rows) . ' ' . str('baris')->plural($export->successful_rows) . ' berhasil diexport.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('baris')->plural($failedRowsCount) . ' gagal diexport.';
        }

        return $body;
    }
}