<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanVisitResource\Pages;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Route;

class PlanVisitResource extends Resource
{
    protected static ?string $model = PlanVisit::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
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
                        Forms\Components\Select::make('outlet_id')
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
                Forms\Components\Section::make('Visit Details')
                    ->schema([
                        Forms\Components\DatePicker::make('tanggal_visit')
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
                Tables\Columns\TextColumn::make('user.nama_lengkap')
                    ->label('Nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('outlet.nama_outlet')
                    ->label('Outlet')
                    ->searchable(),
                Tables\Columns\TextColumn::make('outlet.kode_outlet'),
                Tables\Columns\TextColumn::make('tanggal_visit')
                    ->label('Tanggal Visit')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('created_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_visit', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
        $user = auth()->user();
        $role = $user->role;

        if ($role->filter_type === 'all') {
            return parent::getEloquentQuery();
        }

        $query = parent::getEloquentQuery()
            ->leftJoin('users', 'plan_visits.user_id', '=', 'users.id')
            ->select('plan_visits.*', 'users.id as user_id')
            ->when($role->filter_type === 'badanusaha', function ($query) use ($user) {
                $query->where('users.badanusaha_id', $user->badanusaha_id);
            })
            ->when($role->filter_type === 'divisi', function ($query) use ($role) {
                $query->whereIn('users.divisi_id', $role->filter_data ?? []);
            })
            ->when($role->filter_type === 'region', function ($query) use ($role) {
                $query->whereIn('users.region_id', $role->filter_data ?? []);
            })
            ->when($role->filter_type === 'cluster', function ($query) use ($role) {
                $query->whereIn('users.cluster_id', $role->filter_data ?? []);
            });

        return $query;
    }

    public static function getRecordId(): ?string
    {
        return Route::current()->parameter('record');
    }

    public static function resolveRecordRouteBinding(int|string $key): ?PlanVisit
    {
        return self::getEloquentQuery()->first();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlanVisits::route('/'),
            'create' => Pages\CreatePlanVisit::route('/create'),
            'edit' => Pages\EditPlanVisit::route('/{record}/edit'),
        ];
    }
}
