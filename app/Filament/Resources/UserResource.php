<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->required()
                            ->maxLength(255)
                            ->label('Username')
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn ($state) => strtolower($state))
                            ->placeholder('Masukkan username yang unik')
                            ->regex('/^[\S]+$/', 'Username tidak boleh mengandung spasi')
                            ->helperText('Username tidak boleh mengandung spasi'),
                        Forms\Components\TextInput::make('nama_lengkap')
                            ->required()
                            ->maxLength(255)
                            ->label('Nama Lengkap')
                            ->placeholder('Masukkan nama lengkap')
                            ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Organization Information')
                    ->schema([
                        Forms\Components\Select::make('badanusaha_id')
                            ->label('Badan Usaha')
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->placeholder('Pilih badan usaha')
                            ->options(function (callable $get) {
                                $user = auth()->user();
                                $role = $user->role;
                                if ($role->filter_type === 'badanusaha') {
                                    return \App\Models\BadanUsaha::whereIn('id', $role->filter_data ?? [])
                                        ->pluck('name', 'id');
                                } elseif ($role->filter_type === 'all') {
                                    return \App\Models\BadanUsaha::pluck('name', 'id');
                                }

                                return \App\Models\BadanUsaha::where('id', $user->badanusaha_id)
                                    ->pluck('name', 'id');
                            })

                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('divisi_id', null);
                                $set('region_id', null);
                                $set('cluster_id', null);
                                $set('cluster_id2', null);
                            }),
                        Forms\Components\Select::make('divisi_id')
                            ->label('Divisi')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->placeholder('Pilih divisi')
                            ->options(function (callable $get) {
                                $badanusahaId = $get('badanusaha_id');
                                if (! $badanusahaId) {
                                    return [];
                                }

                                return Division::where('badanusaha_id', $badanusahaId)
                                    ->pluck('name', 'id');
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('region_id', null);
                                $set('cluster_id', null);
                                $set('cluster_id2', null);
                            }),
                        Forms\Components\Select::make('region_id')
                            ->label('Region')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->placeholder('Pilih region')
                            ->options(function (callable $get) {
                                $divisiId = $get('divisi_id');
                                if (! $divisiId) {
                                    return [];
                                }

                                return Region::where('divisi_id', $divisiId)
                                    ->pluck('name', 'id');
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('cluster_id', null);
                                $set('cluster_id2', null);
                            }),
                        Forms\Components\Select::make('cluster_id')
                            ->label('Cluster')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->placeholder('Pilih cluster')
                            ->options(function (callable $get) {
                                $regionId = $get('region_id');
                                if (! $regionId) {
                                    return [];
                                }

                                return Cluster::where('region_id', $regionId)
                                    ->pluck('name', 'id');
                            }),

                        Forms\Components\Select::make('cluster_id2')
                            ->label('Cluster 2')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->placeholder('Pilih cluster 2')
                            ->options(function (callable $get) {
                                $clusterId = $get('region_id');
                                if (! $clusterId) {
                                    return [];
                                }

                                return Cluster::where('region_id', $clusterId)
                                    ->pluck('name', 'id');
                            }),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Role & TM')
                    ->schema([
                        Forms\Components\Select::make('role_id')
                            ->relationship('role', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Role')
                            ->placeholder('Pilih role')
                            ->options(function (callable $get) {
                                $user = auth()->user();
                                if ($user->role->name !== 'SUPER ADMIN') {
                                    return \App\Models\Role::whereIn('name', ['AR', 'ASC', 'ASM', 'DSF/DM'])
                                        ->pluck('name', 'id')->toArray();
                                }

                                return \App\Models\Role::pluck('name', 'id')->toArray();
                            }),
                        Forms\Components\Select::make('tm_id')
                            ->label('TM')
                            ->relationship('tm', 'nama_lengkap')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Pilih TM'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Password')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255)
                            ->label('Password')
                            ->placeholder('Masukkan password')
                            ->required(fn (string $context): bool => $context === 'create')
                            ->revealable(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi User')
                    ->schema([
                        Infolists\Components\TextEntry::make('nama_lengkap')->label('Nama Lengkap'),
                        Infolists\Components\TextEntry::make('username')->label('Username'),
                        Infolists\Components\TextEntry::make('role.name')->label('Role'),
                        Infolists\Components\TextEntry::make('badanusaha.name')->label('Badan Usaha'),
                        Infolists\Components\TextEntry::make('divisi.name')->label('Divisi'),
                        Infolists\Components\TextEntry::make('region.name')->label('Region'),
                        Infolists\Components\TextEntry::make('cluster.name')->label('Cluster'),
                        Infolists\Components\TextEntry::make('tm.nama_lengkap')->label('TM'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_lengkap')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('username')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('role.name'),
                Tables\Columns\TextColumn::make('badanusaha.name'),
                Tables\Columns\TextColumn::make('divisi.name'),
                Tables\Columns\TextColumn::make('region.name'),
                Tables\Columns\TextColumn::make('cluster.name'),
                Tables\Columns\TextColumn::make('tm.nama_lengkap')
                    ->label('TM'),
                Tables\Columns\TextColumn::make('created_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nama_lengkap', 'asc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->filters([
                Filter::make('region')
                    ->form([
                        Select::make('businessEntity')
                            ->label('Badan Usaha')
                            ->options(BadanUsaha::orderBy('name', 'asc')->pluck('name', 'id')->toArray())
                            ->reactive()
                            ->searchable()
                            ->placeholder('Pilih Business Entity')
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $set('division', null);
                                $set('region', null);
                            }),
                        Select::make('division')
                            ->label('Divisi')
                            ->options(function (callable $get) {
                                $businessEntityId = $get('businessEntity');
                                if ($businessEntityId) {
                                    return Division::where('badanusaha_id', $businessEntityId)
                                        ->orderBy('name', 'asc')
                                        ->pluck('name', 'id');
                                }

                                return [];
                            })
                            ->reactive()
                            ->searchable()
                            ->placeholder('Pilih Division')
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $set('region', null);
                            }),
                        Select::make('region')
                            ->label('Region')
                            ->searchable()
                            ->placeholder('Pilih Region')
                            ->options(function (callable $get) {
                                $divisionId = $get('division');
                                if ($divisionId) {
                                    return Region::where('divisi_id', $divisionId)
                                        ->orderBy('name', 'asc')
                                        ->pluck('name', 'id');
                                }

                                return [];
                            })
                            ->reactive(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['businessEntity'] ?? null) {
                            $query->where('badanusaha_id', $data['businessEntity']);
                        }
                        if ($data['division'] ?? null) {
                            $query->where('divisi_id', $data['division']);
                        }
                        if ($data['region'] ?? null) {
                            $query->where('region_id', $data['region']);
                        }

                        return $query;
                    }),

                Tables\Filters\TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['restore_any_visit', 'force_delete_any_visit'], User::class)),
            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth(MaxWidth::Large)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UserResource\RelationManagers\OutletsRelationManager::class,
            UserResource\RelationManagers\PlanVisitsRelationManager::class,
            UserResource\RelationManagers\VisitsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $user = auth()->user();
                $role = $user->role;
                switch ($role->filter_type) {
                    case 'badanusaha':
                        $query->whereIn('users.badanusaha_id', $role->filter_data ?? []);
                        break;
                    case 'divisi':
                        $query->whereIn('users.divisi_id', $role->filter_data ?? []);
                        break;
                    case 'region':
                        $query->whereIn('users.region_id', $role->filter_data ?? []);
                        break;
                    case 'cluster':
                        $query->whereIn('users.cluster_id', $role->filter_data ?? []);
                        break;
                    case 'all':
                    default:
                        return;
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }
}
