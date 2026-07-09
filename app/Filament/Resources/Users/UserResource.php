<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\OutletsRelationManager;
use App\Filament\Resources\Users\RelationManagers\PlanVisitsRelationManager;
use App\Filament\Resources\Users\RelationManagers\RegistersRelationManager;
use App\Filament\Resources\Users\RelationManagers\TeamMembersRelationManager;
use App\Filament\Resources\Users\RelationManagers\VisitsRelationManager;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Support\FilamentOrganizationalScope;
use App\Support\FilamentTableEagerLoad;
use App\Support\OrganizationalHierarchyOptions;
use App\Support\WhatsAppNumber;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use STS\FilamentImpersonate\Actions\Impersonate as ImpersonateAction;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-users-03';

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
                                        TextInput::make('whatsapp_number')
                                            ->label('Nomor WhatsApp')
                                            ->placeholder('Contoh: 081234567890')
                                            ->tel()
                                            ->maxLength(20)
                                            ->dehydrateStateUsing(fn ($state): ?string => filled($state) ? WhatsAppNumber::normalize($state) : null)
                                            ->rule(function (?User $record) {
                                                return function (string $attribute, $value, \Closure $fail) use ($record): void {
                                                    if (! filled($value)) {
                                                        return;
                                                    }

                                                    $number = WhatsAppNumber::normalize((string) $value);

                                                    if (! WhatsAppNumber::isValid($number)) {
                                                        $fail('Nomor WhatsApp harus nomor Indonesia aktif, contoh 081234567890.');

                                                        return;
                                                    }

                                                    $exists = User::query()
                                                        ->where('whatsapp_number', $number)
                                                        ->when($record?->id, fn (Builder $query, int $id): Builder => $query->whereKeyNot($id))
                                                        ->exists();

                                                    if ($exists) {
                                                        $fail('Nomor WhatsApp sudah digunakan user lain.');
                                                    }
                                                };
                                            }),
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
                                            ->revealable(),
                                    ]),
                                ]),
                            Section::make('Peran & Relasi TM')
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'md' => 2,
                                    ])->schema([
                                        Select::make('role_id')
                                            ->searchable()
                                            ->required()
                                            ->live()  // Live to trigger field visibility
                                            ->label('Role')
                                            ->placeholder('Pilih role')
                                            ->options(fn (): array => self::searchAssignableRoles(''))
                                            ->getSearchResultsUsing(fn (string $search): array => self::searchAssignableRoles($search))
                                            ->getOptionLabelUsing(fn ($value): ?string => self::assignableRoleLabel($value))
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                $role = $state ? Role::find($state) : null;
                                                $scopeLevel = $role?->organizational_scope_level;

                                                // Reset dependent field states when role changes.
                                                $set('tm_id', null);
                                                $set('badanUsahas', in_array($scopeLevel, ['badanusaha', 'divisi', 'region', 'cluster'], true) ? [] : null);
                                                $set('divisis', in_array($scopeLevel, ['divisi', 'region', 'cluster'], true) ? [] : null);
                                                $set('regions', in_array($scopeLevel, ['region', 'cluster'], true) ? [] : null);
                                                $set('clusters', $scopeLevel === 'cluster' ? [] : null);
                                            }),
                                        Select::make('tm_id')
                                            ->label('TM')
                                            ->disabled(fn (callable $get) => ! filled($get('role_id')))
                                            ->searchable()
                                            ->options(fn (callable $get): array => self::searchTmOptions('', $get('role_id'), $get('tm_id')))
                                            ->getSearchResultsUsing(fn (string $search, callable $get): array => self::searchTmOptions($search, $get('role_id'), $get('tm_id')))
                                            ->getOptionLabelUsing(fn ($value): ?string => self::tmUserLabel($value))
                                            ->required(function (callable $get) {
                                                $role = Role::find($get('role_id'));

                                                return ! empty(self::getAncestorRoleIds($role));
                                            })
                                            ->placeholder('Pilih TM berdasarkan hirarki role'),
                                    ]),
                                ]),
                        ])
                            ->columnSpan(['default' => 1, 'xl' => 8]),
                        Section::make('Struktur Organisasi')
                            ->description('Divisi tanpa region = akses penuh divisi itu. Isi region/cluster hanya untuk akses parsial. Assignment boleh campur antar cabang.')
                            ->schema([
                                Grid::make(['default' => 1])
                                    ->schema([
                                        Select::make('badanUsahas')
                                            ->label('Badan Usaha')
                                            ->multiple()
                                            ->relationship('badanUsahas', 'name', modifyQueryUsing: function (Builder $query): Builder {
                                                $user = Auth::user();

                                                if (! $user) {
                                                    return $query->whereRaw('1 = 0');
                                                }

                                                if ($user->role->organizational_scope_level === 'all') {
                                                    return $query->orderBy('name', 'asc');
                                                }

                                                if (! self::actorCanAssignOrganizationalStructure($user)) {
                                                    return $query->whereRaw('1 = 0');
                                                }

                                                $badanUsahaIds = $user->getOrganizationalIds()['badanusaha'] ?? [];

                                                if (empty($badanUsahaIds)) {
                                                    return $query->whereRaw('1 = 0');
                                                }

                                                return $query
                                                    ->whereIn('badan_usahas.id', $badanUsahaIds)
                                                    ->orderBy('name', 'asc');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->placeholder('Pilih badan usaha')
                                            ->visible(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['badanusaha', 'divisi', 'region', 'cluster']);
                                            })
                                            ->required(false)
                                            ->rule(fn (): \Closure => self::organizationalAssignmentRule('badanusaha'))
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $roleId = $get('role_id');
                                                $role = $roleId ? Role::find($roleId) : null;
                                                $scopeLevel = $role?->organizational_scope_level;

                                                if (! in_array($scopeLevel, ['divisi', 'region', 'cluster'], true)) {
                                                    $set('divisis', null);
                                                    $set('regions', null);
                                                    $set('clusters', null);

                                                    return;
                                                }

                                                $badanUsahaIds = self::normalizeSelection($state);

                                                $currentDivisionIds = self::normalizeSelection($get('divisis'));
                                                $allowedDivisionIds = empty($badanUsahaIds)
                                                    ? []
                                                    : Division::query()
                                                        ->whereIn('badanusaha_id', $badanUsahaIds)
                                                        ->pluck('id')
                                                        ->all();
                                                $nextDivisionIds = self::intersectSelection($currentDivisionIds, $allowedDivisionIds);
                                                $set('divisis', $nextDivisionIds);

                                                if (! in_array($scopeLevel, ['region', 'cluster'], true)) {
                                                    $set('regions', null);
                                                    $set('clusters', null);

                                                    return;
                                                }

                                                $currentRegionIds = self::normalizeSelection($get('regions'));
                                                $allowedRegionIds = empty($nextDivisionIds)
                                                    ? []
                                                    : Region::query()
                                                        ->whereIn('divisi_id', $nextDivisionIds)
                                                        ->pluck('id')
                                                        ->all();
                                                $nextRegionIds = self::intersectSelection($currentRegionIds, $allowedRegionIds);
                                                $set('regions', $nextRegionIds);

                                                if ($scopeLevel !== 'cluster') {
                                                    $set('clusters', null);

                                                    return;
                                                }

                                                $currentClusterIds = self::normalizeSelection($get('clusters'));
                                                $allowedClusterIds = empty($nextRegionIds)
                                                    ? []
                                                    : Cluster::query()
                                                        ->whereIn('region_id', $nextRegionIds)
                                                        ->pluck('id')
                                                        ->all();
                                                $set('clusters', self::intersectSelection($currentClusterIds, $allowedClusterIds));
                                            }),
                                        Select::make('divisis')
                                            ->label('Divisi')
                                            ->multiple()
                                            ->helperText('Tanpa region di bawahnya = akses penuh divisi ini.')
                                            ->relationship('divisis', 'name', modifyQueryUsing: function (Builder $query, callable $get): Builder {
                                                $badanUsahaIds = $get('badanUsahas');

                                                if (empty($badanUsahaIds)) {
                                                    return $query->whereRaw('1 = 0');
                                                }

                                                $badanUsahaIds = is_array($badanUsahaIds) ? $badanUsahaIds : [$badanUsahaIds];

                                                $query->whereIn('badanusaha_id', $badanUsahaIds);

                                                $user = Auth::user();

                                                if ($user && $user->role->organizational_scope_level !== 'all') {
                                                    if (! self::actorCanAssignOrganizationalStructure($user)) {
                                                        return $query->whereRaw('1 = 0');
                                                    }

                                                    $query = self::applyActorDivisionScope($query, $user);
                                                }

                                                return $query->orderBy('name', 'asc');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->placeholder('Pilih divisi')
                                            ->visible(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['divisi', 'region', 'cluster']);
                                            })
                                            ->required(false)
                                            ->rule(fn (): \Closure => self::organizationalAssignmentRule('divisi'))
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $roleId = $get('role_id');
                                                $role = $roleId ? Role::find($roleId) : null;
                                                $scopeLevel = $role?->organizational_scope_level;

                                                $divisionIds = self::normalizeSelection($state);
                                                $divisionAncestors = self::divisionAncestorIds($divisionIds);
                                                $set('badanUsahas', self::mergeSelection(
                                                    self::normalizeSelection($get('badanUsahas')),
                                                    $divisionAncestors['badanUsahas'],
                                                ));

                                                if (! in_array($scopeLevel, ['region', 'cluster'], true)) {
                                                    $set('regions', null);
                                                    $set('clusters', null);

                                                    return;
                                                }

                                                $currentRegionIds = self::normalizeSelection($get('regions'));
                                                $allowedRegionIds = empty($divisionIds)
                                                    ? []
                                                    : Region::query()
                                                        ->whereIn('divisi_id', $divisionIds)
                                                        ->pluck('id')
                                                        ->all();
                                                $nextRegionIds = self::intersectSelection($currentRegionIds, $allowedRegionIds);
                                                $set('regions', $nextRegionIds);

                                                if ($scopeLevel !== 'cluster') {
                                                    $set('clusters', null);

                                                    return;
                                                }

                                                $currentClusterIds = self::normalizeSelection($get('clusters'));
                                                $allowedClusterIds = empty($nextRegionIds)
                                                    ? []
                                                    : Cluster::query()
                                                        ->whereIn('region_id', $nextRegionIds)
                                                        ->pluck('id')
                                                        ->all();
                                                $set('clusters', self::intersectSelection($currentClusterIds, $allowedClusterIds));
                                            }),
                                        Select::make('regions')
                                            ->label('Region')
                                            ->multiple()
                                            ->helperText('Kosongkan untuk full akses divisi terpilih. Isi hanya region yang ingin dibatasi.')
                                            ->relationship('regions', 'name', modifyQueryUsing: function (Builder $query, callable $get): Builder {
                                                $divisiIds = $get('divisis');

                                                if (empty($divisiIds)) {
                                                    return $query->whereRaw('1 = 0');
                                                }

                                                $divisiIds = is_array($divisiIds) ? $divisiIds : [$divisiIds];

                                                $query->whereIn('divisi_id', $divisiIds);

                                                $user = Auth::user();

                                                if ($user && $user->role->organizational_scope_level !== 'all') {
                                                    if (! self::actorCanAssignOrganizationalStructure($user)) {
                                                        return $query->whereRaw('1 = 0');
                                                    }

                                                    $query = self::applyActorRegionScope($query, $user);
                                                }

                                                return $query
                                                    ->with('divisi:id,name')
                                                    ->orderBy('name', 'asc');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->placeholder('Pilih region')
                                            ->visible(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && in_array($role->organizational_scope_level, ['region', 'cluster'], true);
                                            })
                                            ->required(false)
                                            ->rule(fn (): \Closure => self::organizationalAssignmentRule('region'))
                                            ->getOptionLabelFromRecordUsing(function (Region $region): string {
                                                $divisionName = $region->divisi?->name ?? '-';

                                                return "Region: {$region->name} > Divisi: {$divisionName}";
                                            })
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $roleId = $get('role_id');
                                                $role = $roleId ? Role::find($roleId) : null;
                                                $scopeLevel = $role?->organizational_scope_level;

                                                $regionIds = self::normalizeSelection($state);
                                                $regionAncestors = self::regionAncestorIds($regionIds);
                                                $set('divisis', self::mergeSelection(
                                                    self::normalizeSelection($get('divisis')),
                                                    $regionAncestors['divisis'],
                                                ));
                                                $set('badanUsahas', self::mergeSelection(
                                                    self::normalizeSelection($get('badanUsahas')),
                                                    $regionAncestors['badanUsahas'],
                                                ));

                                                if ($scopeLevel !== 'cluster') {
                                                    $set('clusters', null);

                                                    return;
                                                }

                                                $currentClusterIds = self::normalizeSelection($get('clusters'));
                                                $allowedClusterIds = empty($regionIds)
                                                    ? []
                                                    : Cluster::query()
                                                        ->whereIn('region_id', $regionIds)
                                                        ->pluck('id')
                                                        ->all();

                                                $set('clusters', self::intersectSelection($currentClusterIds, $allowedClusterIds));
                                            }),
                                        Select::make('clusters')
                                            ->label('Cluster')
                                            ->multiple()
                                            ->helperText('Kosongkan untuk full akses region terpilih. Isi hanya cluster yang ingin dibatasi.')
                                            ->relationship('clusters', 'name', modifyQueryUsing: function (Builder $query, callable $get): Builder {
                                                $regionIds = $get('regions');

                                                if (empty($regionIds)) {
                                                    return $query->whereRaw('1 = 0');
                                                }

                                                $regionIds = is_array($regionIds) ? $regionIds : [$regionIds];

                                                $query->whereIn('region_id', $regionIds);

                                                $user = Auth::user();

                                                if ($user && $user->role->organizational_scope_level !== 'all') {
                                                    if (! self::actorCanAssignOrganizationalStructure($user)) {
                                                        return $query->whereRaw('1 = 0');
                                                    }

                                                    $query = self::applyActorClusterScope($query, $user);
                                                }

                                                return $query
                                                    ->with([
                                                        'region:id,name,divisi_id',
                                                        'region.divisi:id,name',
                                                    ])
                                                    ->orderBy('name', 'asc');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->placeholder('Pilih cluster')
                                            ->visible(function (callable $get) {
                                                $roleId = $get('role_id');
                                                if (! $roleId) {
                                                    return false;
                                                }
                                                $role = Role::find($roleId);

                                                return $role && $role->organizational_scope_level === 'cluster';
                                            })
                                            ->required(false)
                                            ->rule(fn (): \Closure => self::organizationalAssignmentRule('cluster'))
                                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                                $clusterAncestors = self::clusterAncestorIds(self::normalizeSelection($state));

                                                $set('regions', self::mergeSelection(
                                                    self::normalizeSelection($get('regions')),
                                                    $clusterAncestors['regions'],
                                                ));
                                                $set('divisis', self::mergeSelection(
                                                    self::normalizeSelection($get('divisis')),
                                                    $clusterAncestors['divisis'],
                                                ));
                                                $set('badanUsahas', self::mergeSelection(
                                                    self::normalizeSelection($get('badanUsahas')),
                                                    $clusterAncestors['badanUsahas'],
                                                ));
                                            })
                                            ->getOptionLabelFromRecordUsing(function (Cluster $cluster): string {
                                                $regionName = $cluster->region?->name ?? '-';
                                                $divisionName = $cluster->region?->divisi?->name ?? '-';

                                                return "Cluster: {$cluster->name} > Region: {$regionName} > Divisi: {$divisionName}";
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
                                TextEntry::make('whatsapp_number')
                                    ->label('Nomor WhatsApp')
                                    ->placeholder('-'),
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

    public static function mutateWhatsAppData(array $data, ?User $record = null): array
    {
        if (! array_key_exists('whatsapp_number', $data)) {
            return $data;
        }

        if (blank($data['whatsapp_number'])) {
            $data['whatsapp_number'] = null;
            $data['whatsapp_verified_at'] = null;

            return $data;
        }

        $number = WhatsAppNumber::normalize((string) $data['whatsapp_number']);
        $data['whatsapp_number'] = $number;

        if (! $record || $record->whatsapp_number !== $number || ! $record->whatsapp_verified_at) {
            $data['whatsapp_verified_at'] = now();
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('whatsapp_number')
                    ->label('Nomor WhatsApp')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('role.name')
                    ->searchable()
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
            ->deferLoading()
            ->filters([
                SelectFilter::make('role_id')
                    ->label('Role')
                    ->multiple()
                    ->searchable()
                    ->relationship('role', 'name')
                    ->placeholder('Pilih Role'),
                Filter::make('region')
                    ->schema([
                        Select::make('businessEntity')
                            ->label('Badan Usaha')
                            ->live()
                            ->searchable()
                            ->options(fn (): array => OrganizationalHierarchyOptions::badanUsaha(activeOnly: false))
                            ->getSearchResultsUsing(fn (string $search): array => OrganizationalHierarchyOptions::searchBadanUsaha($search, activeOnly: false))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::badanUsahaLabel($value, activeOnly: false))
                            ->placeholder('Pilih Business Entity')
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $set('division', null);
                                $set('region', null);
                            }),
                        Select::make('division')
                            ->label('Divisi')
                            ->live()
                            ->searchable()
                            ->options(fn (callable $get): array => OrganizationalHierarchyOptions::division($get('businessEntity'), activeOnly: false))
                            ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchDivision($search, $get('businessEntity'), activeOnly: false))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value))
                            ->placeholder('Pilih Division')
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $set('region', null);
                            }),
                        Select::make('region')
                            ->label('Region')
                            ->searchable()
                            ->placeholder('Pilih Region')
                            ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('division'), activeOnly: false))
                            ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('division'), activeOnly: false))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::regionLabel($value))
                            ->live(),
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
                    ->hidden(fn () => ! Gate::any(['RestoreAny:User', 'ForceDeleteAny:User'], User::class)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ImpersonateAction::make('impersonate')
                    ->visible(fn (User $record): bool => (bool) $record->role?->can_access_web),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OutletsRelationManager::class,
            RegistersRelationManager::class,
            PlanVisitsRelationManager::class,
            VisitsRelationManager::class,
            TeamMembersRelationManager::class,
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
            ->tap(fn (Builder $query) => FilamentOrganizationalScope::applyUserScope($query, $user))
            ->with(FilamentTableEagerLoad::userAssignments());
    }

    protected static function assignableRoleQuery(): Builder
    {
        $query = Role::query()->select(['id', 'name']);
        $user = Auth::user();

        if (! $user || ! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role->name === 'SUPER ADMIN') {
            return $query;
        }

        $descendantIds = \App\Filament\Resources\Roles\RoleResource::getAllDescendantIds($user->role);

        if ($descendantIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('roles.id', $descendantIds);
    }

    protected static function searchAssignableRoles(string $search, int $limit = 50): array
    {
        $keyword = trim($search);

        return self::assignableRoleQuery()
            ->when($keyword !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$keyword.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function assignableRoleLabel(int|string|null $id): ?string
    {
        if (! $id) {
            return null;
        }

        return self::assignableRoleQuery()->whereKey($id)->value('name')
            ?? Role::query()->whereKey($id)->value('name');
    }

    protected static function searchTmOptions(string $search, ?int $roleId, ?int $currentTmId, int $limit = 50): array
    {
        if (! $roleId) {
            return [];
        }

        $role = Role::find($roleId);
        if (! $role) {
            return [];
        }

        $ancestorRoleIds = self::getAncestorRoleIds($role);
        if ($ancestorRoleIds === []) {
            return [];
        }

        $keyword = trim($search);

        $options = User::query()
            ->select(['id', 'nama_lengkap', 'role_id'])
            ->with('role:id,name')
            ->whereIn('role_id', $ancestorRoleIds)
            ->when($keyword !== '', fn (Builder $query) => $query->where('nama_lengkap', 'like', '%'.$keyword.'%'))
            ->orderBy('nama_lengkap')
            ->limit($limit)
            ->get()
            ->mapWithKeys(function (User $user): array {
                $roleName = $user->role?->name ?? '-';

                return [$user->id => "{$user->nama_lengkap} ({$roleName})"];
            })
            ->toArray();

        if ($currentTmId && ! array_key_exists($currentTmId, $options)) {
            $currentTm = User::query()
                ->select(['id', 'nama_lengkap', 'role_id'])
                ->with('role:id,name')
                ->find($currentTmId);

            if ($currentTm) {
                $roleName = $currentTm->role?->name ?? '-';
                $options[$currentTm->id] = "{$currentTm->nama_lengkap} ({$roleName})";
            }
        }

        return $options;
    }

    protected static function tmUserLabel(int|string|null $id): ?string
    {
        if (! $id) {
            return null;
        }

        $user = User::query()
            ->select(['id', 'nama_lengkap', 'role_id'])
            ->with('role:id,name')
            ->find($id);

        if (! $user) {
            return null;
        }

        $roleName = $user->role?->name ?? '-';

        return "{$user->nama_lengkap} ({$roleName})";
    }

    /**
     * Get all ancestor role IDs (parent, grandparent, etc.) for the given role.
     */
    protected static function getAncestorRoleIds(?Role $role): array
    {
        $ids = [];
        $depthGuard = 0;

        while ($role && $role->parent_role_id && $depthGuard < 10) {
            $parent = $role->parent ?? Role::find($role->parent_role_id);
            if (! $parent) {
                break;
            }

            if (in_array($parent->id, $ids, true)) {
                break;
            }

            $ids[] = $parent->id;
            $role = $parent;
            $depthGuard++;
        }

        return $ids;
    }

    protected static function normalizeSelection(mixed $state): array
    {
        if ($state === null || $state === '' || $state === []) {
            return [];
        }

        $values = is_array($state) ? $state : [$state];
        $values = array_map(static fn ($value): string => (string) $value, $values);
        $values = array_filter($values, static fn (string $value): bool => $value !== '');

        return array_values(array_unique($values));
    }

    protected static function intersectSelection(array $selected, array $allowed): array
    {
        if ($selected === [] || $allowed === []) {
            return [];
        }

        $allowedLookup = array_fill_keys(array_map(static fn ($id): string => (string) $id, $allowed), true);

        return array_values(array_filter(
            self::normalizeSelection($selected),
            static fn (string $id): bool => isset($allowedLookup[$id]),
        ));
    }

    /**
     * @param  array<int|string>  $current
     * @param  array<int|string>  $incoming
     * @return array<int|string>
     */
    protected static function mergeSelection(array $current, array $incoming): array
    {
        return self::normalizeSelection(array_merge($current, $incoming));
    }

    public static function syncOrganizationalAssignmentsFromState(User $user, array $state): void
    {
        $hasSynced = false;

        if (array_key_exists('badanUsahas', $state)) {
            $user->badanUsahas()->sync(self::normalizeSelection($state['badanUsahas']));
            $hasSynced = true;
        }

        if (array_key_exists('divisis', $state)) {
            $user->divisis()->sync(self::normalizeSelection($state['divisis']));
            $hasSynced = true;
        }

        if (array_key_exists('regions', $state)) {
            $user->regions()->sync(self::normalizeSelection($state['regions']));
            $hasSynced = true;
        }

        if (array_key_exists('clusters', $state)) {
            $user->clusters()->sync(self::normalizeSelection($state['clusters']));
            $hasSynced = true;
        }

        if ($hasSynced) {
            $user->forgetOrganizationalIdsCache();
        }
    }

    /**
     * Ensure ancestors exist for finer grants without removing coarser
     * full grants on other branches (mixed multi-level access).
     */
    public static function pruneInconsistentOrganizationalHierarchy(User $user): void
    {
        $clusterIds = self::normalizeSelection($user->clusters()->pluck('clusters.id')->all());
        $regionIds = self::normalizeSelection($user->regions()->pluck('regions.id')->all());
        $divisionIds = self::normalizeSelection($user->divisis()->pluck('divisions.id')->all());
        $badanUsahaIds = self::normalizeSelection($user->badanUsahas()->pluck('badan_usahas.id')->all());

        if ($clusterIds !== []) {
            $ancestors = self::clusterAncestorIds($clusterIds);
            $regionIds = self::mergeSelection($regionIds, $ancestors['regions']);
            $divisionIds = self::mergeSelection($divisionIds, $ancestors['divisis']);
            $badanUsahaIds = self::mergeSelection($badanUsahaIds, $ancestors['badanUsahas']);
            $user->clusters()->sync($ancestors['clusters']);
        }

        if ($regionIds !== []) {
            $ancestors = self::regionAncestorIds($regionIds);
            $divisionIds = self::mergeSelection($divisionIds, $ancestors['divisis']);
            $badanUsahaIds = self::mergeSelection($badanUsahaIds, $ancestors['badanUsahas']);
            $user->regions()->sync($ancestors['regions']);
        }

        if ($divisionIds !== []) {
            $ancestors = self::divisionAncestorIds($divisionIds);
            $badanUsahaIds = self::mergeSelection($badanUsahaIds, $ancestors['badanUsahas']);
            $user->divisis()->sync($ancestors['divisis']);
        }

        if ($badanUsahaIds !== []) {
            $user->badanUsahas()->sync(self::normalizeSelection($badanUsahaIds));
        }

        $user->forgetOrganizationalIdsCache();
    }

    /**
     * @return array{divisis: array<int|string>, badanUsahas: array<int|string>}
     */
    protected static function divisionAncestorIds(array $divisionIds): array
    {
        if ($divisionIds === []) {
            return [
                'divisis' => [],
                'badanUsahas' => [],
            ];
        }

        $divisions = Division::query()
            ->whereKey($divisionIds)
            ->get(['id', 'badanusaha_id']);

        return [
            'divisis' => $divisions->pluck('id')->map(fn ($id): string => (string) $id)->unique()->values()->all(),
            'badanUsahas' => $divisions->pluck('badanusaha_id')->filter()->map(fn ($id): string => (string) $id)->unique()->values()->all(),
        ];
    }

    /**
     * @return array{regions: array<int|string>, divisis: array<int|string>, badanUsahas: array<int|string>}
     */
    protected static function regionAncestorIds(array $regionIds): array
    {
        if ($regionIds === []) {
            return [
                'regions' => [],
                'divisis' => [],
                'badanUsahas' => [],
            ];
        }

        $regions = Region::query()
            ->whereKey($regionIds)
            ->get(['id', 'divisi_id', 'badanusaha_id']);

        return [
            'regions' => $regions->pluck('id')->map(fn ($id): string => (string) $id)->unique()->values()->all(),
            'divisis' => $regions->pluck('divisi_id')->filter()->map(fn ($id): string => (string) $id)->unique()->values()->all(),
            'badanUsahas' => $regions->pluck('badanusaha_id')->filter()->map(fn ($id): string => (string) $id)->unique()->values()->all(),
        ];
    }

    /**
     * @return array{clusters: array<int|string>, regions: array<int|string>, divisis: array<int|string>, badanUsahas: array<int|string>}
     */
    protected static function clusterAncestorIds(array $clusterIds): array
    {
        if ($clusterIds === []) {
            return [
                'clusters' => [],
                'regions' => [],
                'divisis' => [],
                'badanUsahas' => [],
            ];
        }

        $clusters = Cluster::query()
            ->whereKey($clusterIds)
            ->get(['id', 'region_id', 'divisi_id', 'badanusaha_id']);

        return [
            'clusters' => $clusters->pluck('id')->map(fn ($id): string => (string) $id)->unique()->values()->all(),
            'regions' => $clusters->pluck('region_id')->filter()->map(fn ($id): string => (string) $id)->unique()->values()->all(),
            'divisis' => $clusters->pluck('divisi_id')->filter()->map(fn ($id): string => (string) $id)->unique()->values()->all(),
            'badanUsahas' => $clusters->pluck('badanusaha_id')->filter()->map(fn ($id): string => (string) $id)->unique()->values()->all(),
        ];
    }

    protected static function actorCanAssignOrganizationalStructure(?User $user = null): bool
    {
        $user ??= self::resolveAuthenticatedUser();

        if (! $user instanceof User || ! $user->role) {
            return false;
        }

        if ($user->role->organizational_scope_level === 'all') {
            return true;
        }

        $ids = $user->getOrganizationalIds();

        return ! empty($ids['badanusaha'])
            || ! empty($ids['divisi'])
            || ! empty($ids['region'])
            || ! empty($ids['cluster']);
    }

    protected static function applyActorDivisionScope(Builder $query, User $user): Builder
    {
        $scopeLevel = $user->role->organizational_scope_level;

        if (in_array($scopeLevel, ['all', 'badanusaha'], true)) {
            return $query;
        }

        $divisiIds = $user->getOrganizationalIds()['divisi'] ?? [];

        if (empty($divisiIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('divisions.id', $divisiIds);
    }

    protected static function applyActorRegionScope(Builder $query, User $user): Builder
    {
        $scopeLevel = $user->role->organizational_scope_level;

        if (in_array($scopeLevel, ['all', 'badanusaha', 'divisi'], true)) {
            return $query;
        }

        $regionIds = $user->getOrganizationalIds()['region'] ?? [];

        if (empty($regionIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('regions.id', $regionIds);
    }

    protected static function applyActorClusterScope(Builder $query, User $user): Builder
    {
        $scopeLevel = $user->role->organizational_scope_level;

        if (in_array($scopeLevel, ['all', 'badanusaha', 'divisi', 'region'], true)) {
            return $query;
        }

        $clusterIds = $user->getOrganizationalIds()['cluster'] ?? [];

        if (empty($clusterIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('clusters.id', $clusterIds);
    }

    protected static function organizationalAssignmentRule(string $level): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($level): void {
            $message = self::validateOrganizationalSelectionLevel($level, $value);

            if ($message !== null) {
                $fail($message);
            }
        };
    }

    public static function validateActorOrganizationalAssignments(array $state): void
    {
        $roleId = $state['role_id'] ?? null;
        $role = $roleId ? Role::find($roleId) : null;
        $scopeLevel = $role?->organizational_scope_level;
        $requiresOrganizationalStructure = in_array($scopeLevel, ['badanusaha', 'divisi', 'region', 'cluster'], true);
        $hasOrganizationalSelection = self::normalizeSelection($state['badanUsahas'] ?? null) !== []
            || self::normalizeSelection($state['divisis'] ?? null) !== []
            || self::normalizeSelection($state['regions'] ?? null) !== []
            || self::normalizeSelection($state['clusters'] ?? null) !== [];

        $user = self::resolveAuthenticatedUser();

        if ($user instanceof User
            && $user->role
            && $user->role->organizational_scope_level !== 'all'
            && ! self::actorCanAssignOrganizationalStructure($user)
            && ($requiresOrganizationalStructure || $hasOrganizationalSelection)) {
            throw ValidationException::withMessages([
                'badanUsahas' => 'Akun Anda belum memiliki assignment organisasi.',
                'divisis' => 'Akun Anda belum memiliki assignment organisasi.',
                'regions' => 'Akun Anda belum memiliki assignment organisasi.',
                'clusters' => 'Akun Anda belum memiliki assignment organisasi.',
            ]);
        }

        if (! $requiresOrganizationalStructure) {
            return;
        }

        if (! $hasOrganizationalSelection) {
            $messages = match ($scopeLevel) {
                'badanusaha' => ['badanUsahas' => 'Pilih minimal satu badan usaha.'],
                'divisi' => [
                    'badanUsahas' => 'Pilih minimal satu assignment organisasi (badan usaha atau divisi).',
                    'divisis' => 'Pilih minimal satu assignment organisasi (badan usaha atau divisi).',
                ],
                'region' => [
                    'badanUsahas' => 'Pilih minimal satu assignment organisasi (badan usaha, divisi, atau region).',
                    'divisis' => 'Pilih minimal satu assignment organisasi (badan usaha, divisi, atau region).',
                    'regions' => 'Pilih minimal satu assignment organisasi (badan usaha, divisi, atau region).',
                ],
                default => [
                    'badanUsahas' => 'Pilih minimal satu assignment organisasi.',
                    'divisis' => 'Pilih minimal satu assignment organisasi.',
                    'regions' => 'Pilih minimal satu assignment organisasi.',
                    'clusters' => 'Pilih minimal satu assignment organisasi.',
                ],
            };

            throw ValidationException::withMessages($messages);
        }

        $messages = [];

        foreach ([
            'badanusaha' => 'badanUsahas',
            'divisi' => 'divisis',
            'region' => 'regions',
            'cluster' => 'clusters',
        ] as $level => $field) {
            if (! array_key_exists($field, $state)) {
                continue;
            }

            $message = self::validateOrganizationalSelectionLevel($level, $state[$field]);

            if ($message !== null) {
                $messages[$field] = $message;
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected static function validateOrganizationalSelectionLevel(string $level, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $user = self::resolveAuthenticatedUser();

        if (! $user instanceof User || ! $user->role) {
            return 'Tidak dapat memvalidasi struktur organisasi.';
        }

        if ($user->role->organizational_scope_level === 'all') {
            return null;
        }

        if (! self::actorCanAssignOrganizationalStructure($user)) {
            return 'Akun Anda belum memiliki assignment organisasi.';
        }

        $selected = self::normalizeSelection($value);
        $ids = $user->getOrganizationalIds();

        $allowed = match ($level) {
            'badanusaha' => $ids['badanusaha'] ?? [],
            'divisi' => in_array($user->role->organizational_scope_level, ['divisi', 'region', 'cluster'], true)
                ? ($ids['divisi'] ?? [])
                : [],
            'region' => in_array($user->role->organizational_scope_level, ['region', 'cluster'], true)
                ? ($ids['region'] ?? [])
                : [],
            'cluster' => $user->role->organizational_scope_level === 'cluster'
                ? ($ids['cluster'] ?? [])
                : [],
            default => [],
        };

        if ($allowed === []) {
            return null;
        }

        $allowedLookup = array_fill_keys(array_map(static fn ($id): string => (string) $id, $allowed), true);
        $invalid = array_filter(
            $selected,
            static fn (string $id): bool => ! isset($allowedLookup[$id]),
        );

        if ($invalid !== []) {
            return 'Pilihan struktur organisasi tidak sesuai scope akun Anda.';
        }

        return null;
    }

    public static function revokeUnauthorizedOrganizationalAssignments(User $target): void
    {
        $actor = self::resolveAuthenticatedUser();

        if (! $actor instanceof User || ! $actor->role || $actor->role->organizational_scope_level === 'all') {
            return;
        }

        if (self::actorCanAssignOrganizationalStructure($actor)) {
            return;
        }

        $target->badanUsahas()->detach();
        $target->divisis()->detach();
        $target->regions()->detach();
        $target->clusters()->detach();
        $target->forgetOrganizationalIdsCache();
    }

    protected static function resolveAuthenticatedUser(): ?User
    {
        $id = auth()->id();

        if ($id) {
            $user = User::query()->with('role')->find($id);

            if ($user instanceof User) {
                return $user;
            }
        }

        $user = Auth::user();

        if ($user instanceof User) {
            return $user->loadMissing('role');
        }

        $filamentUser = Filament::auth()->user();

        return $filamentUser instanceof User ? $filamentUser->loadMissing('role') : null;
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
