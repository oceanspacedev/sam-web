<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\OutletsRelationManager;
use App\Filament\Resources\Users\RelationManagers\PlanVisitsRelationManager;
use App\Filament\Resources\Users\RelationManagers\TeamMembersRelationManager;
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
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use STS\FilamentImpersonate\Actions\Impersonate as ImpersonateAction;

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
                                            ->regex('/^[\S]+$/')
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
                                            ->reactive()  // Make reactive to trigger field visibility
                                            ->label('Role')
                                            ->placeholder('Pilih role')
                                            ->options(function (callable $get) {
                                                $user = Auth::user();

                                                if ($user->role->name !== 'SUPER ADMIN') {
                                                    return Role::whereIn('name', ['AR', 'ASC', 'ASM', 'DSF/DM'])
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')->toArray();
                                                }

                                                return Role::orderBy('name')->pluck('name', 'id')->toArray();
                                            }),
                                        Select::make('tm_id')
                                            ->label('TM')
                                            ->options(function (callable $get) {
                                                $roleId = $get('role_id');
                                                $parentRoleId = $roleId ? Role::find($roleId)?->parent_role_id : null;

                                                $query = User::query()->select('id', 'nama_lengkap')->orderBy('nama_lengkap');

                                                if ($parentRoleId) {
                                                    $query->where('role_id', $parentRoleId);
                                                }

                                                return $query->pluck('nama_lengkap', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(fn (callable $get) => (bool) Role::find($get('role_id'))?->parent_role_id)
                                            ->placeholder('Pilih TM berdasarkan hirarki role'),
                                    ]),
                                ]),
                        ])
                            ->columnSpan(['default' => 1, 'xl' => 8]),
                        Section::make('Struktur Organisasi')
                            ->schema([
                                Grid::make(['default' => 1])
                                    ->schema([
                                        Select::make('badanUsahas')
                                            ->label('Badan Usaha')
                                            ->multiple()
                                            ->relationship('badanUsahas', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->placeholder('Pilih badan usaha')
                                            ->visible(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false; // Hide until role is selected
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['badanusaha', 'divisi', 'cluster']);
                                            })
                                            ->required(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['badanusaha', 'divisi', 'cluster']);
                                            })
                                            ->options(function (callable $get) {
                                                $user = Auth::user();
                                                $role = $user->role;

                                                // If role has 'all' scope, show all
                                                if ($role->organizational_scope_level === 'all') {
                                                    return BadanUsaha::pluck('name', 'id');
                                                }

                                                // Use pivot table for current user's assignments
                                                return $user->badanUsahas()->pluck('name', 'id');
                                            })
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $set('divisis', []);
                                                $set('regions', []);
                                                $set('clusters', []);
                                            }),
                                        Select::make('divisis')
                                            ->label('Divisi')
                                            ->multiple()
                                            ->relationship('divisis', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->placeholder('Pilih divisi')
                                            ->visible(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false; // Hide until role is selected
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['divisi', 'cluster']);
                                            })
                                            ->required(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['divisi', 'cluster']);
                                            })
                                            ->options(function (callable $get) {
                                                $badanUsahaIds = $get('badanUsahas');
                                                if (empty($badanUsahaIds)) {
                                                    return [];
                                                }

                                                return Division::whereIn('badanusaha_id', $badanUsahaIds)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $set('regions', []);
                                                $set('clusters', []);
                                            }),
                                        Select::make('regions')
                                            ->label('Region')
                                            ->multiple()
                                            ->relationship('regions', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->placeholder('Pilih region')
                                            ->visible(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false; // Hide until role is selected
                                                }
                                                $role = Role::find($roleId);

                                                return $role && $role->organizational_scope_level === 'cluster';
                                            })
                                            ->required(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && $role->organizational_scope_level === 'cluster';
                                            })
                                            ->options(function (callable $get) {
                                                $divisiIds = $get('divisis');
                                                if (empty($divisiIds)) {
                                                    return [];
                                                }

                                                return Region::whereIn('divisi_id', $divisiIds)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $set('clusters', []);
                                            }),
                                        Select::make('clusters')
                                            ->label('Cluster')
                                            ->multiple()
                                            ->relationship('clusters', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->reactive()
                                            ->placeholder('Pilih cluster')
                                            ->visible(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false; // Hide until role is selected
                                                }
                                                $role = Role::find($roleId);

                                                return $role && $role->organizational_scope_level === 'cluster';
                                            })
                                            ->required(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && $role->organizational_scope_level === 'cluster';
                                            })
                                            ->options(function (callable $get) {
                                                $regionIds = $get('regions');
                                                if (empty($regionIds)) {
                                                    return [];
                                                }

                                                return Cluster::whereIn('region_id', $regionIds)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            }),
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
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])
                    ->schema([
                        Section::make('Informasi User')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                TextEntry::make('nama_lengkap')
                                    ->label('Nama Lengkap')
                                    ->columnSpan(2),
                                TextEntry::make('username')->label('Username'),
                                TextEntry::make('role.name')->label('Role'),
                                TextEntry::make('tm.nama_lengkap')
                                    ->label('TM')
                                    ->columnSpan(2),
                            ]),
                        Section::make('Struktur Organisasi')
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                TextEntry::make('badan_usahas_list')
                                    ->label('Badan Usaha')
                                    ->badge()
                                    ->state(fn ($record) => $record->badanUsahas->pluck('name')->toArray())
                                    ->separator(','),
                                TextEntry::make('divisis_list')
                                    ->label('Divisi')
                                    ->badge()
                                    ->state(fn ($record) => $record->divisis->pluck('name')->toArray())
                                    ->separator(','),
                                TextEntry::make('regions_list')
                                    ->label('Region')
                                    ->badge()
                                    ->state(fn ($record) => $record->regions->pluck('name')->toArray())
                                    ->separator(','),
                                TextEntry::make('clusters_list')
                                    ->label('Cluster')
                                    ->badge()
                                    ->state(fn ($record) => $record->clusters->pluck('name')->toArray())
                                    ->separator(','),
                            ]),
                    ])
                    ->columnSpanFull(),
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
                TextColumn::make('role.name')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'SUPER ADMIN' => 'danger',
                        'APP DEVELOPER' => 'info',
                        default => 'primary',
                    }),
                TextColumn::make('badanUsahas.name')
                    ->label('Badan Usaha')
                    ->badge()
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('divisis.name')
                    ->label('Divisi')
                    ->badge()
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('regions.name')
                    ->label('Region')
                    ->badge()
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clusters.name')
                    ->label('Cluster')
                    ->badge()
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                            $query->whereHas('badanUsahas', function ($q) use ($data) {
                                $q->where('badan_usahas.id', $data['businessEntity']);
                            });
                        }
                        if ($data['division'] ?? null) {
                            $query->whereHas('divisis', function ($q) use ($data) {
                                $q->where('divisions.id', $data['division']);
                            });
                        }
                        if ($data['region'] ?? null) {
                            $query->whereHas('regions', function ($q) use ($data) {
                                $q->where('regions.id', $data['region']);
                            });
                        }

                        return $query;
                    }),

                TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['restore_any_visit', 'force_delete_any_visit'], User::class)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ImpersonateAction::make('impersonate')
                    ->visible(fn (User $record): bool => (bool) $record->role?->can_access_web),
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
            TeamMembersRelationManager::class,
            OutletsRelationManager::class,
            PlanVisitsRelationManager::class,
            VisitsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                /** @var User|null $user */
                $user = Auth::user();
                if (! $user || ! $user->role) {
                    return;
                }

                $role = $user->role;
                $scopeLevel = $role->organizational_scope_level ?? 'cluster';

                // If role has 'all' access, no filtering needed
                if ($scopeLevel === 'all') {
                    return;
                }

                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();
                $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                // Apply filters based on scope level
                if (! empty($badanUsahaIds)) {
                    $query->whereHas('badanUsahas', function ($q) use ($badanUsahaIds) {
                        $q->whereIn('badan_usahas.id', $badanUsahaIds);
                    });
                }

                if (! empty($divisiIds)) {
                    $query->whereHas('divisis', function ($q) use ($divisiIds) {
                        $q->whereIn('divisions.id', $divisiIds);
                    });
                }

                // Apply region/cluster filters only for non-divisi scope
                if ($scopeLevel === 'cluster') {
                    if (! empty($regionIds)) {
                        $query->whereHas('regions', function ($q) use ($regionIds) {
                            $q->whereIn('regions.id', $regionIds);
                        });
                    }

                    if (! empty($clusterIds)) {
                        $query->whereHas('clusters', function ($q) use ($clusterIds) {
                            $q->whereIn('clusters.id', $clusterIds);
                        });
                    }
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
