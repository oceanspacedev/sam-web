<?php

namespace App\Filament\Exports;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlanVisitExporter extends BaseExporter
{
    protected static ?string $model = PlanVisit::class;

    public static function modifyQueryUsing(Builder $query): Builder
    {
        return $query->with([
            'user',
            'visitable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Outlet::class => ['badanusaha', 'divisi', 'region', 'cluster'],
                Register::class => ['badanusaha', 'divisi', 'region', 'cluster'],
            ]),
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('user.nama_lengkap')
                ->label('Nama')
                ->default('-'),

            ExportColumn::make('visitable_type')
                ->label('Jenis Target')
                ->formatStateUsing(function ($state, PlanVisit $record): string {
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

            ExportColumn::make('visitable_kode_outlet')
                ->label('Kode Outlet')
                ->formatStateUsing(fn ($state, PlanVisit $record): string => static::textValue($record->visitable?->kode_outlet)),

            ExportColumn::make('visitable_badan_usaha')
                ->label('Badan Usaha')
                ->formatStateUsing(fn ($state, PlanVisit $record): string => $record->visitable?->badanusaha?->name ?: '-'),

            ExportColumn::make('visitable_divisi')
                ->label('Divisi')
                ->formatStateUsing(fn ($state, PlanVisit $record): string => $record->visitable?->divisi?->name ?: '-'),

            ExportColumn::make('visitable_region')
                ->label('Region')
                ->formatStateUsing(fn ($state, PlanVisit $record): string => $record->visitable?->region?->name ?: '-'),

            ExportColumn::make('visitable_cluster')
                ->label('Cluster')
                ->formatStateUsing(fn ($state, PlanVisit $record): string => $record->visitable?->cluster?->name ?: '-'),

            ExportColumn::make('visitable_nama_outlet')
                ->label('Nama Outlet')
                ->formatStateUsing(fn ($state, PlanVisit $record): string => $record->visitable?->nama_outlet ?: '-'),

            ExportColumn::make('schedule_scope')
                ->label('Tipe')
                ->formatStateUsing(fn (string $state): string => ucfirst($state)),

            ExportColumn::make('period_start')
                ->label('Tanggal / Periode')
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
