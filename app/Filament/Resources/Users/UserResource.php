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

                                                if ($user->role->name === 'SUPER ADMIN') {
                                                    return Role::orderBy('name')->pluck('name', 'id')->toArray();
                                                }

                                                $descendantIds = \App\Filament\Resources\Roles\RoleResource::getAllDescendantIds($user->role);

                                                return Role::whereIn('roles.id', $descendantIds)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            }),
                                        Select::make('tm_id')
                                            ->label('TM')
                                            ->disabled(fn (callable $get) => ! filled($get('role_id')))
                                            ->options(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return [];
                                                }

                                                $parentRoleId = Role::find($roleId)?->parent_role_id;

                                                if (! $parentRoleId) {
                                                    return [];
                                                }

                                                return User::where('role_id', $parentRoleId)
                                                    ->orderBy('nama_lengkap')
                                                    ->pluck('nama_lengkap', 'id');
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

                                                return $role && in_array($role->organizational_scope_level, ['badanusaha', 'divisi', 'region', 'cluster']);
                                            })
                                            ->required(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['badanusaha', 'divisi', 'region', 'cluster']);
                                            })
                                            ->options(function (callable $get) {
                                                $user = Auth::user();
                                                $role = $user->role;

                                                // If role has 'all' scope, show all
                                                if ($role->organizational_scope_level === 'all') {
                                                    return BadanUsaha::orderBy('name', 'asc')->pluck('name', 'id');
                                                }

                                                // Use pivot table for current user's assignments
                                                return $user->badanUsahas()->orderBy('name', 'asc')->pluck('name', 'badan_usahas.id');
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

                                                return $role && in_array($role->organizational_scope_level, ['divisi', 'region', 'cluster']);
                                            })
                                            ->required(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['divisi', 'region', 'cluster']);
                                            })
                                            ->options(function (callable $get) {
                                                $badanUsahaIds = $get('badanUsahas');
                                                if (empty($badanUsahaIds)) {
                                                    return [];
                                                }

                                                $user = Auth::user();
                                                $query = Division::whereIn('badanusaha_id', $badanUsahaIds);

                                                // Apply user scope filtering
                                                if ($user && $user->role->organizational_scope_level !== 'all') {
                                                    $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                                                    if (! empty($divisiIds)) {
                                                        $query->whereIn('divisions.id', $divisiIds);
                                                    }
                                                }

                                                return $query->orderBy('name', 'asc')->pluck('name', 'id');
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

                                                return $role && in_array($role->organizational_scope_level, ['region', 'cluster'], true);
                                            })
                                            ->required(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['region', 'cluster'], true);
                                            })
                                            ->options(function (callable $get) {
                                                $divisiIds = $get('divisis');
                                                if (empty($divisiIds)) {
                                                    return [];
                                                }

                                                $user = Auth::user();
                                                $query = Region::whereIn('divisi_id', $divisiIds);

                                                // Apply user scope filtering
                                                if ($user && in_array($user->role->organizational_scope_level, ['region', 'cluster'], true)) {
                                                    $regionIds = $user->regions()->pluck('regions.id')->toArray();
                                                    if (! empty($regionIds)) {
                                                        $query->whereIn('regions.id', $regionIds);
                                                    }
                                                }

                                                return $query->orderBy('name', 'asc')->pluck('name', 'id');
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

                                                $user = Auth::user();
                                                $query = Cluster::whereIn('region_id', $regionIds);

                                                // Apply user scope filtering
                                                if ($user && $user->role->organizational_scope_level === 'cluster') {
                                                    $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();
                                                    if (! empty($clusterIds)) {
                                                        $query->whereIn('clusters.id', $clusterIds);
                                                    }
                                                }

                                                return $query->orderBy('name', 'asc')->pluck('name', 'id');
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
                                TextEntry::make('role.name')
                                    ->label('Role')
                                    ->badge()
                                    ->color(function ($record): string {
                                        $scopeLevel = $record->role?->organizational_scope_level ?? 'cluster';

                                        return match ($scopeLevel) {
                                            'all' => 'danger',        // Merah - akses penuh
                                            'badanusaha' => 'warning', // Orange/Kuning - scope luas
                                            'divisi' => 'info',        // Biru - scope sedang
                                            'region' => 'success',     // Hijau - scope lebih spesifik
                                            'cluster' => 'gray',       // Abu-abu - scope paling spesifik
                                            default => 'primary',      // Default fallback
                                        };
                                    }),
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
                    ->color(function ($record): string {
                        $scopeLevel = $record->role?->organizational_scope_level ?? 'cluster';

                        return match ($scopeLevel) {
                            'all' => 'danger',        // Merah - akses penuh
                            'badanusaha' => 'warning', // Orange/Kuning - scope luas
                            'divisi' => 'info',        // Biru - scope sedang
                            'region' => 'success',     // Hijau - scope lebih spesifik
                            'cluster' => 'gray',       // Abu-abu - scope paling spesifik
                            default => 'primary',      // Default fallback
                        };
                    })
                    ->tooltip(function ($record): string {
                        $scopeLevel = $record->role?->organizational_scope_level ?? 'cluster';

                        return match ($scopeLevel) {
                            'all' => 'Akses penuh ke semua data',
                            'badanusaha' => 'Akses berdasarkan Badan Usaha',
                            'divisi' => 'Akses berdasarkan Divisi',
                            'region' => 'Akses berdasarkan Region',
                            'cluster' => 'Akses berdasarkan Cluster',
                            default => 'Scope akses tidak diketahui',
                        };
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
                            ->options(function () {
                                $user = Auth::user();

                                if ($user && $user->role->organizational_scope_level === 'all') {
                                    return BadanUsaha::orderBy('name', 'asc')->pluck('name', 'id')->toArray();
                                }

                                return $user->badanUsahas()->orderBy('name', 'asc')->pluck('name', 'badan_usahas.id')->toArray();
                            })
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
                                if (! $businessEntityId) {
                                    return [];
                                }

                                $user = Auth::user();
                                $query = Division::where('badanusaha_id', $businessEntityId);

                                if ($user && $user->role->organizational_scope_level !== 'all') {
                                    $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                                    if (! empty($divisiIds)) {
                                        $query->whereIn('divisions.id', $divisiIds);
                                    }
                                }

                                return $query->orderBy('name', 'asc')->pluck('name', 'id');
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
                                if (! $divisionId) {
                                    return [];
                                }

                                $user = Auth::user();
                                $query = Region::where('divisi_id', $divisionId);

                                if ($user && in_array($user->role->organizational_scope_level, ['region', 'cluster'], true)) {
                                    $regionIds = $user->regions()->pluck('regions.id')->toArray();
                                    if (! empty($regionIds)) {
                                        $query->whereIn('regions.id', $regionIds);
                                    }
                                }

                                return $query->orderBy('name', 'asc')->pluck('name', 'id');
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
            OutletsRelationManager::class,
            PlanVisitsRelationManager::class,
            VisitsRelationManager::class,
            TeamMembersRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                /** @var User|null $user */
                $user = Auth::user();

                // CRITICAL: Block access if user or role is null
                if (! $user || ! $user->role) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $role = $user->role;
                $scopeLevel = $role->organizational_scope_level;

                // CRITICAL: Block access if scope level is null
                if (! $scopeLevel) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                // If role has 'all' access, no filtering needed
                if ($scopeLevel === 'all') {
                    return;
                }

                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();
                $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                // CRITICAL: If user has no assignments at all, block access
                $hasAnyAssignment = ! empty($badanUsahaIds) || ! empty($divisiIds) || ! empty($regionIds) || ! empty($clusterIds);
                if (! $hasAnyAssignment) {
                    $query->whereRaw('1 = 0');

                    return;
                }

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

                // Apply region/cluster filters based on scope
                if (in_array($scopeLevel, ['region', 'cluster'], true) && ! empty($regionIds)) {
                    $query->whereHas('regions', function ($q) use ($regionIds) {
                        $q->whereIn('regions.id', $regionIds);
                    });
                }

                if ($scopeLevel === 'cluster' && ! empty($clusterIds)) {
                    $query->whereHas('clusters', function ($q) use ($clusterIds) {
                        $q->whereIn('clusters.id', $clusterIds);
                    });
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
