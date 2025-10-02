<?php

namespace App\Filament\Exports;

use App\Models\Outlet;
use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OutletExporter extends Exporter
{
    protected static ?string $model = Outlet::class;

    public static function getColumns(): array
    {
        $baseUrl = 'http://grosir.mediaselularindonesia.com/storage/';

        return [
            ExportColumn::make('badanusaha.name')->label('Badan Usaha'),
            ExportColumn::make('divisi.name')->label('Divisi'),
            ExportColumn::make('region.name')->label('Region'),
            ExportColumn::make('cluster.name')->label('Cluster'),
            ExportColumn::make('kode_outlet')->label('Kode Outlet'),
            ExportColumn::make('nama_outlet')->label('Nama Outlet'),
            ExportColumn::make('alamat_outlet')->label('Alamat Outlet'),
            ExportColumn::make('distric')->label('Distrik'),
            ExportColumn::make('status_outlet')->label('Status Outlet'),
            ExportColumn::make('radius')->label('Radius'),
            ExportColumn::make('limit')->label('Limit'),
            ExportColumn::make('latlong')->label('Latlong'),
            ExportColumn::make('nama_pemilik_outlet')->label('Nama Pemilik Outlet'),
            ExportColumn::make('nomer_tlp_outlet')->label('Nomor Telepon Outlet'),
            ExportColumn::make('tm')
                ->label('TM')
                ->formatStateUsing(function ($state, $record) {
                    $tm = User::where('divisi_id', $record->divisi_id)
                        ->where('region_id', $record->region_id)
                        ->where('role_id', 2)
                        ->first();

                    return $tm->tm->nama_lengkap ?? 'VACANT';
                }),
            ExportColumn::make('asc')
                ->label('ASC')
                ->formatStateUsing(function ($state, $record) {
                    $asc = User::where('divisi_id', $record->divisi_id)
                        ->where('region_id', $record->region_id)
                        ->where('role_id', 2)
                        ->first();

                    return $asc->nama_lengkap ?? 'VACANT';
                }),
            ExportColumn::make('dsf')
                ->label('DSF')
                ->formatStateUsing(function ($state, $record) {
                    $dsf = User::where('divisi_id', $record->divisi_id)
                        ->where('region_id', $record->region_id)
                        ->where('cluster_id', $record->cluster_id)
                        ->where('role_id', 3)
                        ->first();

                    return $dsf->nama_lengkap ?? 'VACANT';
                }),
            ExportColumn::make('created_at')
                ->formatStateUsing(function ($state) {
                    return \Carbon\Carbon::parse($state)->format('d M Y');
                })
                ->label('Tanggal Registrasi'),
            ExportColumn::make('poto_shop_sign')
                ->formatStateUsing(function ($state) use ($baseUrl) {
                    return $state ? $baseUrl.$state : '-';
                })
                ->label('Foto Shop Sign'),
            ExportColumn::make('poto_depan')
                ->formatStateUsing(function ($state) use ($baseUrl) {
                    return $state ? $baseUrl.$state : '-';
                })
                ->label('Foto Depan'),
            ExportColumn::make('poto_kiri')
                ->formatStateUsing(function ($state) use ($baseUrl) {
                    return $state ? $baseUrl.$state : '-';
                })
                ->label('Foto Kiri'),
            ExportColumn::make('poto_kanan')
                ->formatStateUsing(function ($state) use ($baseUrl) {
                    return $state ? $baseUrl.$state : '-';
                })
                ->label('Foto Kanan'),
            ExportColumn::make('poto_ktp')
                ->formatStateUsing(function ($state) use ($baseUrl) {
                    return $state ? $baseUrl.$state : '-';
                })
                ->label('Foto KTP'),
            ExportColumn::make('video')
                ->formatStateUsing(function ($state) use ($baseUrl) {
                    return $state ? $baseUrl.$state : '-';
                })
                ->label('Video'),
            ExportColumn::make('updated_at')
                ->formatStateUsing(function ($state) {
                    return \Carbon\Carbon::parse($state)->format('d M Y');
                })
                ->label('Diperbaharui pada'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor data outlet Anda telah selesai. Sebanyak '.number_format($export->successful_rows).' '.str('baris')->plural($export->successful_rows).' berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' Namun, '.number_format($failedRowsCount).' '.str('baris')->plural($failedRowsCount).' gagal diekspor.';
        }

        return $body;
    }
}
