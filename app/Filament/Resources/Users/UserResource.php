<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\OutletsRelationManager;
use App\Filament\Resources\Users\RelationManagers\PlanVisitsRelationManager;
use App\Filament\Resources\Users\RelationManagers\VisitsRelationManager;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        Group::make([
                            Section::make('Akun & Identitas')
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'md' => 2,
                                    ])->schema([
                                        TextInput::make('username')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('Username')
                                            ->unique(ignoreRecord: true)
                                            ->dehydrateStateUsing(fn ($state) => strtolower($state))
                                            ->placeholder('Masukkan username yang unik')
                                            ->regex('/^[\S]+$/', 'Username tidak boleh mengandung spasi')
                                            ->helperText('Username tidak boleh mengandung spasi'),
                                        TextInput::make('nama_lengkap')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('Nama Lengkap')
                                            ->placeholder('Masukkan nama lengkap')
                                            ->dehydrateStateUsing(fn ($state) => strtoupper($state)),
                                        TextInput::make('password')
                                            ->password()
                                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->maxLength(255)
                                            ->label('Password')
                                            ->placeholder('Masukkan password')
                                            ->required(fn (string $context): bool => $context === 'create')
                                            ->revealable()
                                            ->columnSpan(['default' => 1, 'md' => 2]),
                                    ]),
                                ]),
                            Section::make('Peran & Relasi TM')
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'md' => 2,
                                    ])->schema([
                                        Select::make('role_id')
                                            ->relationship('role', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->label('Role')
                                            ->placeholder('Pilih role')
                                            ->options(function (callable $get) {
                                                $user = auth()->user();

                                                if ($user->role->name !== 'SUPER ADMIN') {
                                                    return Role::whereIn('name', ['AR', 'ASC', 'ASM', 'DSF/DM'])
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')->toArray();
                                                }

                                                return Role::orderBy('name')->pluck('name', 'id')->toArray();
                                            }),
                                        Select::make('tm_id')
                                            ->label('TM')
                                            ->relationship('tm', 'nama_lengkap')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->placeholder('Pilih TM'),
                                    ]),
                                ]),
                        ])
                            ->columnSpan(['default' => 1, 'xl' => 8]),
                        Section::make('Struktur Organisasi')
                            ->schema([
                                Grid::make(['default' => 1])
                                    ->schema([
                                        Select::make('badanusaha_id')
                                            ->label('Badan Usaha')
                                            ->searchable()
                                            ->required()
                                            ->reactive()
                                            ->placeholder('Pilih badan usaha')
                                            ->options(function (callable $get) {
                                                $user = auth()->user();
                                                $role = $user->role;

                                                if ($role->filter_type === 'badanusaha') {
                                                    return BadanUsaha::whereIn('id', $role->filter_data ?? [])->pluck('name', 'id');
                                                }

                                                if ($role->filter_type === 'all') {
                                                    return BadanUsaha::pluck('name', 'id');
                                                }

                                                return BadanUsaha::where('id', $user->badanusaha_id)->pluck('name', 'id');
                                            })
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $set('divisi_id', null);
                                                $set('region_id', null);
                                                $set('cluster_id', null);
                                                $set('cluster_id2', null);
                                            }),
                                        Select::make('divisi_id')
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
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $set('region_id', null);
                                                $set('cluster_id', null);
                                                $set('cluster_id2', null);
                                            }),
                                        Select::make('region_id')
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
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $set('cluster_id', null);
                                                $set('cluster_id2', null);
                                            }),
                                        Select::make('cluster_id')
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
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            }),
                                        Select::make('cluster_id2')
                                            ->label('Cluster 2')
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->placeholder('Pilih cluster 2 (opsional)')
                                            ->options(function (callable $get) {
                                                $regionId = $get('region_id');

                                                if (! $regionId) {
                                                    return [];
                                                }

                                                return Cluster::where('region_id', $regionId)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->hint('Opsional, pilih cluster kedua bila diperlukan.'),
                                    ]),
                            ])
                            ->columnSpan(['default' => 1, 'xl' => 4]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi User')
                    ->schema([
                        TextEntry::make('nama_lengkap')->label('Nama Lengkap'),
                        TextEntry::make('username')->label('Username'),
                        TextEntry::make('role.name')->label('Role'),
                        TextEntry::make('badanusaha.name')->label('Badan Usaha'),
                        TextEntry::make('divisi.name')->label('Divisi'),
                        TextEntry::make('region.name')->label('Region'),
                        TextEntry::make('cluster.name')->label('Cluster'),
                        TextEntry::make('tm.nama_lengkap')->label('TM'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('username')
                //     ->searchable(),
                TextColumn::make('role.name'),
                TextColumn::make('badanusaha.name'),
                TextColumn::make('divisi.name'),
                TextColumn::make('region.name'),
                TextColumn::make('cluster.name'),
                TextColumn::make('tm.nama_lengkap')
                    ->label('TM'),
                TextColumn::make('created_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nama_lengkap', 'asc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->filters([
                Filter::make('region')
                    ->schema([
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

                TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['restore_any_visit', 'force_delete_any_visit'], User::class)),
            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth(Width::Large)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OutletsRelationManager::class,
            PlanVisitsRelationManager::class,
            VisitsRelationManager::class,
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
