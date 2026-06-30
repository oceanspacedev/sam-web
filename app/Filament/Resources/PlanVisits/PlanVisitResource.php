<?php

namespace App\Filament\Resources\PlanVisits;

use App\Filament\Resources\PlanVisits\Pages\CreatePlanVisit;
use App\Filament\Resources\PlanVisits\Pages\EditPlanVisit;
use App\Filament\Resources\PlanVisits\Pages\ListPlanVisits;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\User;
use App\Services\SystemSettingResolver;
use App\Support\FilamentOrganizationalScope;
use App\Support\FilamentTableEagerLoad;
use App\Support\ScopedUserSelectOptions;
use App\Support\VisitTargetSelectOptions;
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
                            ->options(fn (): array => ScopedUserSelectOptions::search(Auth::user(), ''))
                            ->getSearchResultsUsing(fn (string $search): array => ScopedUserSelectOptions::search(Auth::user(), $search))
                            ->getOptionLabelUsing(fn ($value): ?string => ScopedUserSelectOptions::label($value)),
                        // Polymorphic visit target selector
                        Select::make('visitable_type')
                            ->label('Tipe Target')
                            ->options(function () {
                                $options = [
                                    Outlet::class => 'Outlet',
                                ];

                                $allowsRegisterVisit = app(SystemSettingResolver::class)->hasAnyAllowRegisterVisit();

                                if ($allowsRegisterVisit) {
                                    $options[Register::class] = 'Register (LEAD/NOO)';
                                }

                                return $options;
                            })
                            ->required()
                            ->live()
                            ->default(Outlet::class)
                            ->afterStateUpdated(fn (callable $set) => $set('visitable_id', null)),
                        Select::make('visitable_id')
                            ->label('Target')
                            ->required()
                            ->searchable()
                            ->disabled(fn (callable $get): bool => ! $get('user_id'))
                            ->placeholder(fn (callable $get) => ! $get('user_id')
                                ? 'Pilih user terlebih dahulu'
                                : 'Cari target berdasarkan nama/kode')
                            ->getSearchResultsUsing(function (string $search, callable $get) {
                                $selectedUserId = $get('user_id');
                                $selectedUser = $selectedUserId
                                    ? User::query()->select(['id', 'role_id'])->with('role')->find($selectedUserId)
                                    : null;
                                if (! $selectedUser) {
                                    return [];
                                }

                                $type = (string) $get('visitable_type');
                                if ($type === Outlet::class) {
                                    return VisitTargetSelectOptions::searchOutlets($search, $selectedUser);
                                }

                                if ($type !== Register::class) {
                                    return [];
                                }

                                return VisitTargetSelectOptions::searchRegisters(
                                    $search,
                                    $selectedUser,
                                    app(SystemSettingResolver::class),
                                );
                            })
                            ->options(function (callable $get) {
                                $type = (string) $get('visitable_type');
                                $id = $get('visitable_id');
                                $selectedUserId = $get('user_id');
                                $selectedUser = $selectedUserId
                                    ? User::query()->select(['id', 'role_id'])->with('role')->find($selectedUserId)
                                    : null;

                                $options = [];

                                if ($selectedUser && $type === Outlet::class) {
                                    $options = VisitTargetSelectOptions::searchOutlets('', $selectedUser);
                                } elseif ($selectedUser && $type === Register::class) {
                                    $options = VisitTargetSelectOptions::searchRegisters('', $selectedUser, app(SystemSettingResolver::class));
                                }

                                if ($id && $type !== '' && ! array_key_exists($id, $options)) {
                                    $label = VisitTargetSelectOptions::label($type, $id);

                                    if ($label) {
                                        $options[$id] = $label;
                                    }
                                }

                                return $options;
                            })
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                $type = (string) $get('visitable_type');
                                if ($state && $type === Outlet::class) {
                                    $divisionId = Outlet::query()->whereKey($state)->value('divisi_id');
                                    $set('outlet_division_id', $divisionId);
                                } else {
                                    $set('outlet_division_id', null);
                                }
                            }),
                    ])
                    ->collapsible()
                    ->columns(2),
                Section::make('Visit Details')
                    ->schema([
                        Hidden::make('outlet_division_id')
                            ->default(fn (?PlanVisit $record) => $record?->isOutletVisit() ? $record?->visitable?->divisi_id : null)
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
                TextColumn::make('visitable.nama_outlet')
                    ->label('Target')
                    ->badge()
                    ->color(fn ($record) => $record->isOutletVisit() ? 'primary' : 'warning')
                    ->formatStateUsing(fn ($state, $record) => $state ?? '-')
                    ->tooltip(fn ($record) => $record->isOutletVisit() ? 'Outlet' : 'Register')
                    ->searchable(),
                TextColumn::make('visitable.kode_outlet')
                    ->label('Kode Target')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                            ->options(fn (): array => ScopedUserSelectOptions::search(Auth::user(), ''))
                            ->getSearchResultsUsing(fn (string $search): array => ScopedUserSelectOptions::search(Auth::user(), $search))
                            ->getOptionLabelUsing(fn ($value): ?string => ScopedUserSelectOptions::label($value)),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['user_id'] ?? null, fn (Builder $builder, $userId): Builder => $builder->where('user_id', $userId))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
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
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->tap(fn (Builder $query) => FilamentOrganizationalScope::applyViaUserForeignKey($query, $user))
            ->with(FilamentTableEagerLoad::visitableTarget());
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
