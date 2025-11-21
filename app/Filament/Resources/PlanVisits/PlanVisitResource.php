<?php

namespace App\Filament\Resources\PlanVisits;

use App\Filament\Resources\PlanVisits\Pages\CreatePlanVisit;
use App\Filament\Resources\PlanVisits\Pages\EditPlanVisit;
use App\Filament\Resources\PlanVisits\Pages\ListPlanVisits;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;

class PlanVisitResource extends Resource
{
    protected static ?string $model = PlanVisit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        Select::make('user_id')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->label('Pilih User')
                            ->placeholder('Cari User berdasarkan nama lengkap')
                            ->getSearchResultsUsing(function (string $search) {
                                return User::query()
                                    ->with(['badanUsahas:id,name', 'divisis:id,name'])
                                    ->where('nama_lengkap', 'like', "%{$search}%")
                                    ->orderBy('nama_lengkap')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                        $badanusahaName = $user->badanUsahas->first()->name ?? 'Tidak ada badan usaha';
                                        $divisiName = $user->divisis->first()->name ?? 'Tidak ada divisi';

                                        return [$user->id => "{$user->nama_lengkap} - {$badanusahaName} / {$divisiName}"];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value) {
                                if (! $value) {
                                    return null;
                                }
                                $user = User::with(['badanUsahas:id,name', 'divisis:id,name'])->find($value);
                                if (! $user) {
                                    return null;
                                }
                                $badanusahaName = $user->badanUsahas->first()->name ?? 'Tidak ada badan usaha';
                                $divisiName = $user->divisis->first()->name ?? 'Tidak ada divisi';

                                return "{$user->nama_lengkap} - {$badanusahaName} / {$divisiName}";
                            }),
                        Select::make('outlet_id')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->label('Pilih Outlet')
                            ->placeholder('Cari Outlet berdasarkan nama/kode')
                            ->getSearchResultsUsing(function (string $search) {
                                return Outlet::query()
                                    ->with(['badanusaha:id,name', 'divisi:id,name'])
                                    ->where(function ($q) use ($search) {
                                        $q->where('nama_outlet', 'like', "%{$search}%")
                                            ->orWhere('kode_outlet', 'like', "%{$search}%");
                                    })
                                    ->orderBy('nama_outlet')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($outlet) {
                                        $badanusahaName = $outlet->badanusaha->name ?? 'Tidak ada badan usaha';
                                        $divisiName = $outlet->divisi->name ?? 'Tidak ada divisi';

                                        return [$outlet->id => "[{$outlet->kode_outlet}] {$outlet->nama_outlet} - {$badanusahaName} / {$divisiName}"];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value) {
                                if (! $value) {
                                    return null;
                                }
                                $outlet = Outlet::with(['badanusaha:id,name', 'divisi:id,name'])->find($value);
                                if (! $outlet) {
                                    return null;
                                }
                                $badanusahaName = $outlet->badanusaha->name ?? 'Tidak ada badan usaha';
                                $divisiName = $outlet->divisi->name ?? 'Tidak ada divisi';

                                return "[{$outlet->kode_outlet}] {$outlet->nama_outlet} - {$badanusahaName} / {$divisiName}";
                            })
                            ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                                $divisionId = $state ? Outlet::query()->whereKey($state)->value('divisi_id') : null;

                                $set('outlet_division_id', $divisionId);
                            }),
                    ])
                    ->collapsible()
                    ->columns(2),
                Section::make('Visit Details')
                    ->schema([
                        Hidden::make('outlet_division_id')
                            ->default(fn (?PlanVisit $record) => $record?->outlet?->divisi_id)
                            ->dehydrated(false),
                        Radio::make('schedule_scope')
                            ->label('Jenis Plan')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                            ])
                            ->default('daily')
                            ->inline()
                            ->live()
                            ->helperText('Weekly otomatis mencatat rentang Senin - Sabtu berdasarkan pilihan minggu.'),
                        Select::make('schedule_week_selector')
                            ->label('Pilih Minggu')
                            ->options(fn (): array => static::weeklyScheduleOptions())
                            ->visible(fn (callable $get): bool => $get('schedule_scope') === 'weekly')
                            ->required(fn (callable $get): bool => $get('schedule_scope') === 'weekly')
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $mondayDate = static::weekSelectorMondayDate($state);

                                if ($mondayDate) {
                                    $set('tanggal_visit', $mondayDate);
                                }
                            })
                            ->helperText('Pilih minggu untuk otomatis mengatur rentang Senin - Sabtu.'),
                        DatePicker::make('tanggal_visit')
                            ->native(false)
                            ->required()
                            ->label('Tanggal Visit')
                            ->placeholder('Pilih tanggal visit')
                            ->visible(fn (callable $get): bool => $get('schedule_scope') !== 'weekly')
                            ->helperText('Tanggal kunjungan harian akan dicatat sesuai pilihan.'),
                    ])
                    ->collapsible()
                    ->columns(1),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function weeklyScheduleOptions(int $weeksAhead = 5, ?Carbon $referenceDate = null): array
    {
        $referenceDate ??= now();
        $weeks = [];

        $currentMonday = $referenceDate->copy()->startOfWeek(Carbon::MONDAY);

        for ($i = 0; $i < $weeksAhead; $i++) {
            $weekStart = $currentMonday->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->addDays(5);
            $weekOfYear = $weekStart->weekOfYear;

            $weeks["{$weekOfYear}_{$weekStart->toDateString()}"] = sprintf(
                'Week %d (%s - %s)',
                $weekOfYear,
                $weekStart->format('d M'),
                $weekEnd->format('d M Y')
            );
        }

        return $weeks;
    }

    public static function weekSelectorMondayDate(?string $value): ?string
    {
        if (! $value || ! str_contains($value, '_')) {
            return null;
        }

        $parts = explode('_', $value, 2);

        return $parts[1] ?? null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.nama_lengkap')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('outlet.nama_outlet')
                    ->label('Outlet')
                    ->searchable(),
                TextColumn::make('outlet.kode_outlet')
                    ->label('Kode Outlet')
                    ->searchable(),
                TextColumn::make('schedule_scope')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->colors([
                        'info' => 'weekly',
                        'success' => 'daily',
                    ]),
                TextColumn::make('period_start')
                    ->label('Tanggal / Periode')
                    ->formatStateUsing(function ($state, PlanVisit $record) {
                        if ($record->isWeekly() && $record->period_start && $record->period_end) {
                            $start = Carbon::parse($record->period_start)->format('d M');
                            $end = Carbon::parse($record->period_end)->format('d M Y');

                            return $start.' - '.$end;
                        }

                        return $state ? Carbon::parse($state)->format('d M Y') : '-';
                    }),
                TextColumn::make('realized_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Realized' : 'Pending')
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock'),
                TextColumn::make('created_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('period_start', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                Filter::make('tanggal_visit')
                    ->label('Tanggal Visit')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('period_start', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('period_start', '<=', $date));
                    }),
                Filter::make('user')
                    ->label('User')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->searchable()
                            ->placeholder('Semua User')
                            ->options(
                                fn (): array => User::query()
                                    ->orderBy('nama_lengkap')
                                    ->pluck('nama_lengkap', 'id')
                                    ->toArray()
                            ),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['user_id'] ?? null, fn (Builder $builder, $userId): Builder => $builder->where('user_id', $userId))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function prepareSchedulePayload(array $data): array
    {
        $scheduleScope = $data['schedule_scope'] ?? 'daily';

        $tanggalVisit = $data['tanggal_visit'] ?? null;

        if ($scheduleScope === 'weekly') {
            $weekDate = static::weekSelectorMondayDate($data['schedule_week_selector'] ?? null);

            if ($weekDate) {
                $tanggalVisit = $weekDate;
            }
        }

        $tanggalVisit ??= now()->toDateString();

        $payload = PlanVisit::schedulePayload($tanggalVisit, $scheduleScope);

        unset($data['outlet_division_id'], $data['schedule_week_selector']);

        return array_merge($data, $payload);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return parent::getEloquentQuery();
        }

        $role = $user->role;
        $scopeLevel = $role->organizational_scope_level ?? 'cluster';

        if (! $role || $scopeLevel === 'all') {
            return parent::getEloquentQuery();
        }

        // Get user's organizational assignments from pivot tables
        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
        $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
        $regionIds = $user->regions()->pluck('regions.id')->toArray();
        $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

        $query = parent::getEloquentQuery();

        // Apply filters based on user pivot assignments
        if (! empty($badanUsahaIds)) {
            $query->whereHas('user.badanUsahas', function ($q) use ($badanUsahaIds) {
                $q->whereIn('badan_usahas.id', $badanUsahaIds);
            });
        }

        if (! empty($divisiIds)) {
            $query->whereHas('user.divisis', function ($q) use ($divisiIds) {
                $q->whereIn('divisions.id', $divisiIds);
            });
        }

        if (! empty($regionIds)) {
            $query->whereHas('user.regions', function ($q) use ($regionIds) {
                $q->whereIn('regions.id', $regionIds);
            });
        }

        if (! empty($clusterIds)) {
            $query->whereHas('user.clusters', function ($q) use ($clusterIds) {
                $q->whereIn('clusters.id', $clusterIds);
            });
        }

        return $query;
    }

    public static function getRecordId(): ?string
    {
        return Route::current()->parameter('record');
    }

    public static function resolveRecordRouteBinding(string|int $key, ?Closure $modifyQuery = null): ?Model
    {
        $query = static::getEloquentQuery();

        if ($modifyQuery) {
            $modifyQuery($query);
        }

        return $query->whereKey($key)->first();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanVisits::route('/'),
            'create' => CreatePlanVisit::route('/create'),
            'edit' => EditPlanVisit::route('/{record}/edit'),
        ];
    }
}
