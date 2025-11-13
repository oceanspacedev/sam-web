<?php

namespace App\Filament\Resources\PlanVisits;

use App\Filament\Resources\PlanVisits\Pages\CreatePlanVisit;
use App\Filament\Resources\PlanVisits\Pages\EditPlanVisit;
use App\Filament\Resources\PlanVisits\Pages\ListPlanVisits;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
                            }),
                    ])
                    ->collapsible()
                    ->columns(2),
                Section::make('Visit Details')
                    ->schema([
                        DatePicker::make('tanggal_visit')
                            ->native(false)
                            ->required()
                            ->label('Tanggal Visit')
                            ->placeholder('Pilih tanggal dan waktu visit')
                            ->helperText('Tanggal dan waktu kunjungan akan dicatat di sini'),
                    ])
                    ->collapsible()
                    ->columns(1),
            ]);
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
                TextColumn::make('tanggal_visit')
                    ->label('Tanggal Visit')
                    ->date('d M Y'),
                TextColumn::make('created_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_visit', 'desc')
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
                            ->when($data['from'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('tanggal_visit', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('tanggal_visit', '<=', $date));
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
