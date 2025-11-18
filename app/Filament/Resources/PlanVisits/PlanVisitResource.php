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
                            ->required()
                            ->reactive()
                            ->label('Pilih User')
                            ->placeholder('Cari User berdasarkan nama lengkap')
                            ->getSearchResultsUsing(function (string $search) {
                                return User::query()
                                    ->with(['badanusaha:id,name', 'divisi:id,name'])
                                    ->where('nama_lengkap', 'like', "%{$search}%")
                                    ->orderBy('nama_lengkap')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                        $badanusahaName = $user->badanusaha->name ?? 'Tidak ada badan usaha';
                                        $divisiName = $user->divisi->name ?? 'Tidak ada divisi';

                                        return [$user->id => "{$user->nama_lengkap} - {$badanusahaName} / {$divisiName}"];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value) {
                                if (! $value) {
                                    return null;
                                }
                                $user = User::with(['badanusaha:id,name', 'divisi:id,name'])->find($value);
                                if (! $user) {
                                    return null;
                                }
                                $badanusahaName = $user->badanusaha->name ?? 'Tidak ada badan usaha';
                                $divisiName = $user->divisi->name ?? 'Tidak ada divisi';

                                return "{$user->nama_lengkap} - {$badanusahaName} / {$divisiName}";
                            }),
                        Select::make('outlet_id')
                            ->searchable()
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
                TextColumn::make('outlet.kode_outlet'),
                TextColumn::make('period_start')
                    ->label('Tanggal Visit')
                    ->formatStateUsing(function ($state, PlanVisit $record) {
                        if ($record->isWeekly() && $record->period_start && $record->period_end) {
                            $start = Carbon::parse($record->period_start)->format('d M Y');
                            $end = Carbon::parse($record->period_end)->format('d M Y');

                            return $start.' - '.$end;
                        }

                        return $state ? Carbon::parse($state)->format('d M Y') : '-';
                    }),
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

        if (! $role || $role->filter_type === 'all') {
            return parent::getEloquentQuery();
        }

        $query = parent::getEloquentQuery()
            ->leftJoin('users', 'plan_visits.user_id', '=', 'users.id')
            ->select('plan_visits.*', 'users.id as user_id')
            ->when($role->filter_type === 'badanusaha', function (Builder $builder) use ($user): void {
                $builder->where('users.badanusaha_id', $user->badanusaha_id);
            })
            ->when($role->filter_type === 'divisi', function (Builder $builder) use ($role): void {
                $builder->whereIn('users.divisi_id', $role->filter_data ?? []);
            })
            ->when($role->filter_type === 'region', function (Builder $builder) use ($role): void {
                $builder->whereIn('users.region_id', $role->filter_data ?? []);
            })
            ->when($role->filter_type === 'cluster', function (Builder $builder) use ($role): void {
                $builder->whereIn('users.cluster_id', $role->filter_data ?? []);
            });

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
