<?php

namespace App\Filament\Exports;

use App\Models\Outlet;
use App\Models\Register;
use App\Models\Visit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VisitExporter extends BaseExporter
{
    protected static ?string $model = Visit::class;

    public static function modifyQueryUsing(Builder $query): Builder
    {
        return $query->with([
            'user.role',
            'visitable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Outlet::class => ['badanusaha', 'divisi', 'region', 'cluster'],
                Register::class => ['badanusaha', 'divisi', 'region', 'cluster'],
            ]),
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('tanggal_visit')
                ->label('Tanggal')
                ->formatStateUsing(fn ($state) => static::formatDateTimeValue($state, 'd M Y')),

            ExportColumn::make('user.nama_lengkap')
                ->label('Nama')
                ->default('-'),

            ExportColumn::make('user.role.name')
                ->label('Role')
                ->default('-'),

            ExportColumn::make('visitable_type')
                ->label('Jenis Target')
                ->formatStateUsing(function ($state, Visit $record): string {
                    if ($record->isOutletVisit()) {
                        return 'Outlet';
                    }

                    if (! $record->isRegisterVisit()) {
                        return '-';
                    }

                    $registerType = strtoupper((string) ($record->visitable?->type ?? ''));

                    if ($registerType === '') {
                        return 'Register';
                    }

                    return "Register ({$registerType})";
                }),

            ExportColumn::make('tipe_visit')
                ->label('Tipe')
                ->default('-'),

            ExportColumn::make('visitable_kode_outlet')
                ->label('Kode Outlet')
                ->formatStateUsing(fn ($state, Visit $record): string => $record->visitable?->kode_outlet ?: '-'),

            ExportColumn::make('visitable_nama_outlet')
                ->label('Nama Outlet')
                ->formatStateUsing(fn ($state, Visit $record): string => $record->visitable?->nama_outlet ?: '-'),

            ExportColumn::make('visitable_badan_usaha')
                ->label('Badan Usaha')
                ->formatStateUsing(fn ($state, Visit $record): string => $record->visitable?->badanusaha?->name ?: '-'),

            ExportColumn::make('visitable_divisi')
                ->label('Divisi')
                ->formatStateUsing(fn ($state, Visit $record): string => $record->visitable?->divisi?->name ?: '-'),

            ExportColumn::make('visitable_region')
                ->label('Region')
                ->formatStateUsing(fn ($state, Visit $record): string => $record->visitable?->region?->name ?: '-'),

            ExportColumn::make('visitable_cluster')
                ->label('Cluster')
                ->formatStateUsing(fn ($state, Visit $record): string => $record->visitable?->cluster?->name ?: '-'),

            ExportColumn::make('picture_visit_in')
                ->label('Foto CI')
                ->formatStateUsing(fn ($state) => static::storageImageFormula($state)),

            ExportColumn::make('picture_visit_out')
                ->label('Foto CO')
                ->formatStateUsing(fn ($state) => static::storageImageFormula($state)),

            ExportColumn::make('latlong_in')
                ->label('Lokasi CI')
                ->formatStateUsing(fn ($state) => static::mapLinkFromLatLong($state)),

            ExportColumn::make('latlong_out')
                ->label('Lokasi CO')
                ->formatStateUsing(fn ($state) => static::mapLinkFromLatLong($state)),

            ExportColumn::make('check_in_time')
                ->label('Jam CI')
                ->formatStateUsing(fn ($state) => static::formatDateTimeValue($state, 'H:i')),

            ExportColumn::make('check_out_time')
                ->label('Jam CO')
                ->formatStateUsing(fn ($state) => static::formatDateTimeValue($state, 'H:i')),

            ExportColumn::make('durasi_visit')
                ->label('Durasi')
                ->formatStateUsing(fn ($state) => $state ? $state.' Menit' : '-'),

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
        $body = 'Export visit telah selesai dan '.number_format($export->successful_rows).' '.str('baris')->plural($export->successful_rows).' berhasil diexport.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('baris')->plural($failedRowsCount).' gagal diexport.';
        }

        return $body;
    }
}
