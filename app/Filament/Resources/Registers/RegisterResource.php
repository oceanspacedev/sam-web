<?php

namespace App\Filament\Resources\Registers;

use App\Filament\Resources\Registers\Pages\CreateRegister;
use App\Filament\Resources\Registers\Pages\EditRegister;
use App\Filament\Resources\Registers\Pages\ListRegisters;
use App\Filament\Resources\Registers\Pages\ViewRegister;
use App\Models\Register;
use App\Models\User;
use App\Services\FilenameGeneratorService;
use App\Support\OrganizationalHierarchyOptions;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class RegisterResource extends Resource
{
    protected static ?string $model = Register::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        Group::make([
                            Section::make('Data Outlet')
                                ->schema([
                                    TextInput::make('nama_outlet')
                                        ->required()
                                        ->maxLength(255)
                                        ->reactive()
                                        ->label('Nama Outlet'),
                                    TextInput::make('distric')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Distrik'),
                                    Textarea::make('alamat_outlet')
                                        ->required()
                                        ->columnSpanFull()
                                        ->label('Alamat Outlet'),
                                    TextInput::make('nama_pemilik_outlet')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Nama Pemilik Outlet'),
                                    TextInput::make('nomer_tlp_outlet')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Nomor Telepon Outlet'),
                                    TextInput::make('nomer_wakil_outlet')
                                        ->maxLength(255)
                                        ->label('Nomor Wakil Outlet'),
                                    TextInput::make('ktp_outlet')
                                        ->maxLength(255)
                                        ->label('KTP Pemilik Outlet')
                                        ->visible(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                        ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type'))),
                                    TextInput::make('latlong')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Koordinat Lat/Long'),
                                ])
                                ->columns(2),
                            Section::make('Dokumentasi')
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'md' => 2,
                                    ])->schema([
                                        FileUpload::make('poto_shop_sign')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Tanda Toko')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-photo', $userId);
                                                // Output: rp241204123-a1b2c3-550e8400-e29b-41d4-a716-446655440000.jpg
                                            }),
                                        FileUpload::make('poto_depan')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Depan')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-photo', $userId);
                                            }),
                                        FileUpload::make('poto_kiri')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Kiri')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-photo', $userId);
                                            }),
                                        FileUpload::make('poto_kanan')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Kanan')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-photo', $userId);
                                            }),
                                        FileUpload::make('poto_ktp')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto KTP Pemilik')
                                            ->required(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->visible(fn (Get $get): bool => ! RegisterResource::isLead($get('type')))
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-ktp', $userId);
                                            }),
                                        FileUpload::make('video')
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Video Toko')
                                            ->afterStateHydrated(function (Set $set, $state): void {
                                                if ($state === '-') {
                                                    $set('video', null);
                                                }
                                            })
                                            ->dehydrateStateUsing(function ($state) {
                                                if (blank($state) || $state === '-') {
                                                    return '-';
                                                }

                                                if (is_array($state)) {
                                                    $first = reset($state);

                                                    return filled($first) ? (string) $first : '-';
                                                }

                                                return $state;
                                            })
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'register-video', $userId);
                                            }),
                                    ]),
                                ]),
                            Section::make('Promotor dan Frontliner')
                                ->schema([
                                    TextInput::make('oppo')
                                        ->required()
                                        ->numeric()
                                        ->label('Oppo'),
                                    TextInput::make('vivo')
                                        ->required()
                                        ->numeric()
                                        ->label('Vivo'),
                                    TextInput::make('realme')
                                        ->required()
                                        ->numeric()
                                        ->label('Realme'),
                                    TextInput::make('samsung')
                                        ->required()
                                        ->numeric()
                                        ->label('Samsung'),
                                    TextInput::make('xiaomi')
                                        ->required()
                                        ->numeric()
                                        ->label('Xiaomi'),
                                    TextInput::make('fl')
                                        ->required()
                                        ->numeric()
                                        ->label('FL'),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Group::make([
                            Section::make('Informasi Tambahan')
                                ->schema([
                                    ToggleButtons::make('type')
                                        ->label('Tipe')
                                        ->options([
                                            'LEAD' => 'LEAD',
                                            'NOO' => 'NOO',
                                        ])
                                        ->required()
                                        ->live()
                                        ->inline()
                                        ->colors([
                                            'LEAD' => 'warning',
                                            'NOO' => 'success',
                                        ])
                                        ->default('NOO'),
                                    Select::make('created_by_id')
                                        ->label('Dibuat Oleh')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->relationship('createdBy', 'nama_lengkap')
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, ?int $state): void {
                                            if (! $state) {
                                                $set('tm_id', null);

                                                return;
                                            }

                                            $creator = User::find($state);

                                            if (! $creator) {
                                                $set('tm_id', null);

                                                return;
                                            }

                                            $set('tm_id', optional($creator->tm)->id ?? $creator->id);
                                        }),
                                ])
                                ->columns(1),
                            Section::make('Struktur Organisasi')
                                ->schema([
                                    Grid::make(['default' => 1])
                                        ->schema([
                                            Select::make('badanusaha_id')
                                                ->label('Badan Usaha')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive()
                                                ->placeholder('Pilih badan usaha')
                                                ->options(fn (): array => OrganizationalHierarchyOptions::badanUsaha(activeOnly: false))
                                                ->getSearchResultsUsing(fn (string $search): array => OrganizationalHierarchyOptions::searchBadanUsaha($search, activeOnly: false))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::badanUsahaLabel($value, activeOnly: false))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('divisi_id', null);
                                                    $set('region_id', null);
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('divisi_id')
                                                ->label('Divisi')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::division($get('badanusaha_id'), activeOnly: false))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchDivision($search, $get('badanusaha_id'), activeOnly: false))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('region_id', null);
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('region_id')
                                                ->label('Region')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('divisi_id'), activeOnly: false))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('divisi_id'), activeOnly: false))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::regionLabel($value))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('cluster_id')
                                                ->label('Cluster')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::cluster($get('region_id'), activeOnly: false))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchCluster($search, $get('region_id'), activeOnly: false))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::clusterLabel($value)),
                                        ]),
                                ]),
                            Section::make('TM')
                                ->schema([
                                    Select::make('tm_id')
                                        ->label('Nama TM')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->options(fn (Get $get): array => self::getTmOptions('', $get('created_by_id'), $get('tm_id')))
                                        ->getSearchResultsUsing(fn (string $search, Get $get): array => self::getTmOptions($search, $get('created_by_id'), $get('tm_id')))
                                        ->getOptionLabelUsing(fn ($value): ?string => self::tmLabel($value))
                                        ->live(),
                                ]),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 4]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function isLead(?string $type): bool
    {
        return strtoupper((string) $type) === 'LEAD';
    }

    protected static function registerTypeColor(?string $type): string
    {
        return match (strtoupper((string) $type)) {
            'LEAD' => 'warning',
            'NOO' => 'success',
            default => 'gray',
        };
    }

    protected static function registerTypeLabel(?string $type): string
    {
        return match (strtoupper((string) $type)) {
            'LEAD' => 'LEAD',
            'NOO' => 'NOO',
            default => '-',
        };
    }

    protected static function getTmOptions(string $search, ?int $creatorId, ?int $currentTmId, int $limit = 50): array
    {
        $options = [];
        $keyword = trim($search);

        if ($creatorId) {
            $creator = User::find($creatorId);

            if ($creator) {
                $tm = $creator->tm;

                if ($tm) {
                    $options[$tm->id] = $tm->nama_lengkap;
                } else {
                    $options[$creator->id] = $creator->nama_lengkap;
                }
            }
        }

        $query = User::query()
            ->select(['id', 'nama_lengkap'])
            ->when($keyword !== '', fn (Builder $builder) => $builder->where('nama_lengkap', 'like', '%'.$keyword.'%'))
            ->orderBy('nama_lengkap')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->nama_lengkap])
            ->toArray();

        if ($currentTmId && ! array_key_exists($currentTmId, $options) && ! array_key_exists($currentTmId, $query)) {
            $currentTm = User::query()->select(['id', 'nama_lengkap'])->find($currentTmId);
            if ($currentTm) {
                $options[$currentTm->id] = $currentTm->nama_lengkap;
            }
        }

        return $options + $query;
    }

    protected static function tmLabel(int|string|null $id): ?string
    {
        if (! $id) {
            return null;
        }

        return User::query()->whereKey($id)->value('nama_lengkap');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        Group::make([
                            Section::make('Data Outlet')
                                ->schema([
                                    TextEntry::make('nama_outlet')
                                        ->label('Nama Outlet'),
                                    TextEntry::make('distric')
                                        ->label('Distrik'),
                                    TextEntry::make('alamat_outlet')
                                        ->label('Alamat Outlet')
                                        ->columnSpanFull(),
                                    TextEntry::make('nama_pemilik_outlet')
                                        ->label('Nama Pemilik Outlet'),
                                    TextEntry::make('nomer_tlp_outlet')
                                        ->label('Nomor Telepon Outlet'),
                                    TextEntry::make('nomer_wakil_outlet')
                                        ->label('Nomor Wakil Outlet'),
                                    TextEntry::make('ktp_outlet')
                                        ->label('KTP Pemilik Outlet'),
                                    TextEntry::make('latlong')
                                        ->label('Koordinat Lat/Long')
                                        ->url(fn ($state) => $state ? "https://www.google.com/maps/place/{$state}" : null, shouldOpenInNewTab: true)
                                        ->color('primary'),
                                ])
                                ->columns(2),
                            Section::make('Dokumentasi')
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                        'md' => 2,
                                    ])->schema([
                                        ImageEntry::make('poto_shop_sign')
                                            ->label('Foto Tanda Toko')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        ImageEntry::make('poto_depan')
                                            ->label('Foto Depan')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        ImageEntry::make('poto_kiri')
                                            ->label('Foto Kiri')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        ImageEntry::make('poto_kanan')
                                            ->label('Foto Kanan')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        ImageEntry::make('poto_ktp')
                                            ->label('Foto KTP Pemilik')
                                            ->disk(StorageDisk::default())
                                            ->checkFileExistence(false),
                                        TextEntry::make('video')
                                            ->label('Video Toko')
                                            ->formatStateUsing(fn ($state) => filled($state) && $state !== '-' ? new HtmlString('<a href="'.StorageDisk::url($state).'" target="_blank" class="text-primary-600 hover:underline">Lihat Video</a>') : '-')
                                            ->html(),
                                    ]),
                                ]),
                            Section::make('Promotor dan Frontliner')
                                ->schema([
                                    TextEntry::make('oppo')
                                        ->label('Oppo'),
                                    TextEntry::make('vivo')
                                        ->label('Vivo'),
                                    TextEntry::make('realme')
                                        ->label('Realme'),
                                    TextEntry::make('samsung')
                                        ->label('Samsung'),
                                    TextEntry::make('xiaomi')
                                        ->label('Xiaomi'),
                                    TextEntry::make('fl')
                                        ->label('FL'),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Group::make([
                            Section::make('Informasi Tambahan')
                                ->schema([
                                    TextEntry::make('createdBy.nama_lengkap')
                                        ->label('Dibuat Oleh'),
                                    TextEntry::make('created_at')
                                        ->label('Tanggal Dibuat')
                                        ->date('d M Y'),
                                    TextEntry::make('type')
                                        ->label('Tipe')
                                        ->badge()
                                        ->color(fn (?string $state): string => self::registerTypeColor($state))
                                        ->formatStateUsing(fn (?string $state): string => self::registerTypeLabel($state)),
                                    TextEntry::make('keterangan')
                                        ->label('Keterangan')
                                        ->placeholder('-'),
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'APPROVED' => 'success',
                                            'REJECTED' => 'danger',
                                            'CONFIRMED' => 'info',
                                            default => 'gray',
                                        }),
                                ]),
                            Section::make('Struktur Organisasi')
                                ->schema([
                                    TextEntry::make('badanusaha.name')
                                        ->label('Badan Usaha'),
                                    TextEntry::make('divisi.name')
                                        ->label('Divisi'),
                                    TextEntry::make('divisionSetting.default_register_radius')
                                        ->label('Radius Default')
                                        ->formatStateUsing(fn ($state) => $state ? $state . ' m' : '-')
                                        ->badge()
                                        ->color('primary'),
                                    TextEntry::make('region.name')
                                        ->label('Region'),
                                    TextEntry::make('cluster.name')
                                        ->label('Cluster'),
                                    TextEntry::make('tm.nama_lengkap')
                                        ->label('TM'),
                                ]),
                            Section::make('Outlet Hasil')
                                ->schema([
                                    TextEntry::make('outlet.kode_outlet')
                                        ->label('Kode Outlet')
                                        ->placeholder('-'),
                                    TextEntry::make('outlet.nama_outlet')
                                        ->label('Nama Outlet')
                                        ->placeholder('-'),
                                    TextEntry::make('outlet.status_outlet')
                                        ->label('Status Outlet')
                                        ->badge()
                                        ->color(fn (?string $state): string => match ($state) {
                                            'MAINTAIN' => 'success',
                                            'UNMAINTAIN' => 'warning',
                                            'UNPRODUCTIVE' => 'danger',
                                            default => 'gray',
                                        })
                                        ->placeholder('-'),
                                    TextEntry::make('outlet.deleted_at')
                                        ->label('Status Data')
                                        ->badge()
                                        ->color('danger')
                                        ->formatStateUsing(fn ($state) => $state ? 'Data Outlet Dihapus' : null)
                                        ->visible(fn ($record) => $record->outlet?->deleted_at !== null),
                                    TextEntry::make('outlet.id')
                                        ->label('Lihat Outlet')
                                        ->formatStateUsing(fn ($state) => $state ? 'Buka Detail Outlet' : null)
                                        ->url(fn ($record) => $record->outlet && Gate::allows('ViewAny:Outlet')
                                            ? route('filament.admin.resources.outlets.view', $record->outlet->id)
                                            : null)
                                        ->color('primary')
                                        ->visible(fn ($record) => $record->outlet
                                            && $record->outlet?->deleted_at === null
                                            && Gate::allows('ViewAny:Outlet'))
                                        ->placeholder('-'),
                                ])
                                ->visible(fn ($record) => $record->status === 'APPROVED' && $record->outlet !== null),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 4]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->date('d M Y'),
                TextColumn::make('createdBy.nama_lengkap')
                    ->label('Dibuat Oleh')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Jenis Register')
                    ->badge()
                    ->color(fn (?string $state): string => self::registerTypeColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::registerTypeLabel($state))
                    ->sortable(),
                TextColumn::make('kode_outlet')
                    ->label('Kode Outlet'),
                TextColumn::make('divisi.name')
                    ->label('Divisi')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('badanusaha.name')
                    ->label('Badan Usaha')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                TextColumn::make('alamat_outlet')
                    ->label('Alamat Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nama_pemilik_outlet')
                    ->label('Nama Pemilik Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ktp_outlet')
                    ->label('Nomor KTP Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nomer_tlp_outlet')
                    ->label('Nomor Telepon Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nomer_wakil_outlet')
                    ->label('Nomor Wakil Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distric')
                    ->label('Distrik'),
                TextColumn::make('region.name')
                    ->label('Region')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cluster.name')
                    ->label('Cluster')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_ktp')
                    ->label('Foto KTP')
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('KTP'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_shop_sign')
                    ->label('Foto Tanda Outlet')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_depan')
                    ->label('Foto Depan')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_kanan')
                    ->label('Foto Kanan')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_kiri')
                    ->label('Foto Kiri')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('video')
                    ->label('Video Outlet')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('VIDEO'))
                    ->color('primary')
                    ->url(fn ($state): ?string => filled($state) && $state !== '-' ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('oppo')
                    ->label('Oppo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vivo')
                    ->label('Vivo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('realme')
                    ->label('Realme')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('samsung')
                    ->label('Samsung')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('xiaomi')
                    ->label('Xiaomi')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fl')
                    ->label('Frontliner')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latlong')
                    ->label('Lokasi (LatLong)')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->color('primary')
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('limit')
                    ->label('Limit')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui') // Updated for clarity
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis Register')
                    ->options([
                        'LEAD' => 'LEAD',
                        'NOO' => 'NOO',
                    ]),
                Filter::make('region')
                    ->schema([
                        Select::make('businessEntity')
                            ->label('Badan Usaha')
                            ->reactive()
                            ->searchable()
                            ->preload()
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
                            ->reactive()
                            ->searchable()
                            ->preload()
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
                            ->preload()
                            ->placeholder('Pilih Region')
                            ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('division'), activeOnly: false))
                            ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('division'), activeOnly: false))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::regionLabel($value))
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
                    ->hidden(fn () => ! Gate::any(['RestoreAny:Register', 'ForceDeleteAny:Register'], Register::class)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('update_ktp')
                    ->label('Update KTP')
                    ->icon('heroicon-o-identification')
                    ->color('primary')
                    ->visible(fn ($record) => strtoupper((string) $record->type) === 'LEAD' && Gate::allows('Upgrade:Register'))
                    ->form([
                        TextInput::make('ktp_outlet')
                            ->label('Nomor KTP Outlet')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('poto_ktp')
                            ->label('Foto KTP')
                            ->image()
                            ->disk(StorageDisk::default())
                            ->fetchFileInformation(false)
                            ->required()
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file) {
                                $userId = Auth::id();
                                $filenameGenerator = new FilenameGeneratorService;

                                return $filenameGenerator->generate($file, 'register-ktp', $userId);
                            }),
                    ])
                    ->action(function ($record, $data): void {
                        $record->update([
                            'ktp_outlet' => $data['ktp_outlet'],
                            'poto_ktp' => $data['poto_ktp'],
                            'type' => 'NOO',
                        ]);

                        Notification::make()
                            ->title('KTP Updated')
                            ->success()
                            ->send();
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ($record->status === 'PENDING' || $record->status === 'CONFIRMED') && Gate::allows('Approve:Register', $record) && strtoupper((string) $record->type) !== 'LEAD')
                    ->form([
                        TextInput::make('kode_outlet')
                            ->regex('/^\S+$/')
                            ->helperText('Kode outlet tidak boleh mengandung spasi')
                            ->default(fn ($record) => $record->kode_outlet)
                            ->required()
                            ->rules(function ($record) {
                                return [
                                    function (string $attribute, $value, \Closure $fail) use ($record) {
                                        $exists = \App\Models\Outlet::where('kode_outlet', $value)
                                            ->where('divisi_id', $record->divisi_id)
                                            ->exists();

                                        if ($exists) {
                                            $fail("Kode outlet {$value} sudah digunakan.");
                                        }
                                    },
                                ];
                            }),
                        TextInput::make('limit')
                            ->numeric()
                            ->default(fn ($record) => $record->limit)
                            ->required(),
                    ])
                    ->action(function ($record, $data): void {
                        /** @var User|null $authUser */
                        $authUser = Auth::user();

                        $record->update([
                            'kode_outlet' => $data['kode_outlet'],
                            'limit' => $data['limit'],
                            'confirmed_at' => $record->confirmed_at ?? Carbon::now(),
                            'confirmed_by_id' => $record->confirmed_by_id ?? $authUser?->id,
                            'approved_at' => Carbon::now(),
                            'approved_by_id' => $authUser?->id,
                            'status' => 'APPROVED',
                        ]);

                        Notification::make()
                            ->title($record->nama_outlet.' Approved')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== 'REJECTED' && $record->status !== 'APPROVED' && Gate::allows('Reject:Register', $record) && strtoupper((string) $record->type) !== 'LEAD')
                    ->schema([
                        Textarea::make('alasan')
                            ->required(),
                    ])
                    ->action(function ($record, $data): void {
                        /** @var User|null $authUser */
                        $authUser = Auth::user();

                        $record->update([
                            'rejected_at' => Carbon::now(),
                            'rejected_by_id' => $authUser?->id,
                            'status' => 'REJECTED',
                            'keterangan' => $data['alasan'],
                        ]);
                        Notification::make()
                            ->title($record->nama_outlet.' Rejected')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(function (?ListRegisters $livewire = null): bool {
                            $activeTab = $livewire?->activeTab;

                            return Gate::allows('Approve:Register') && $activeTab === 'confirmed';
                        })
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            /** @var User|null $authUser */
                            $authUser = Auth::user();

                            $approved = 0;
                            $skipped = 0;
                            $skippedNames = [];

                            $records->each(function (Register $record) use (&$approved, &$skipped, &$skippedNames, $authUser): void {
                                if ($record->status !== 'CONFIRMED') {
                                    $skipped++;
                                    $skippedNames[] = $record->nama_outlet ?? 'ID '.$record->id;

                                    return;
                                }

                                $record->update([
                                    'approved_at' => Carbon::now(),
                                    'approved_by_id' => $authUser?->id,
                                    'status' => 'APPROVED',
                                ]);

                                $approved++;
                            });

                            if ($approved > 0) {
                                Notification::make()
                                    ->title("{$approved} register di-approve")
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                $list = implode(', ', array_slice($skippedNames, 0, 3));
                                $more = count($skippedNames) > 3 ? ' dan lainnya' : '';

                                Notification::make()
                                    ->title("{$skipped} data dilewati")
                                    ->body("Status bukan CONFIRMED: {$list}{$more}")
                                    ->danger()
                                    ->send();
                            }
                        }),
                    BulkAction::make('bulk_reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(function (?ListRegisters $livewire = null): bool {
                            $activeTab = $livewire?->activeTab;

                            return Gate::allows('Reject:Register') && in_array($activeTab, ['pending', 'confirmed'], true);
                        })
                        ->form([
                            Textarea::make('alasan')
                                ->label('Alasan')
                                ->required(),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            /** @var User|null $authUser */
                            $authUser = Auth::user();

                            $rejected = 0;
                            $skipped = 0;
                            $skippedNames = [];

                            $records->each(function (Register $record) use (&$rejected, &$skipped, &$skippedNames, $authUser, $data): void {
                                if (in_array($record->status, ['APPROVED', 'REJECTED'], true)) {
                                    $skipped++;
                                    $skippedNames[] = $record->nama_outlet ?? 'ID '.$record->id;

                                    return;
                                }

                                $record->update([
                                    'rejected_at' => Carbon::now(),
                                    'rejected_by_id' => $authUser?->id,
                                    'status' => 'REJECTED',
                                    'keterangan' => $data['alasan'],
                                ]);

                                $rejected++;
                            });

                            if ($rejected > 0) {
                                Notification::make()
                                    ->title("{$rejected} register di-reject")
                                    ->success()
                                    ->send();
                            }

                            if ($skipped > 0) {
                                $list = implode(', ', array_slice($skippedNames, 0, 3));
                                $more = count($skippedNames) > 3 ? ' dan lainnya' : '';

                                Notification::make()
                                    ->title("{$skipped} data dilewati")
                                    ->body("Status sudah APPROVED/REJECTED: {$list}{$more}")
                                    ->danger()
                                    ->send();
                            }
                        }),
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
            //
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

                // Apply filters based on assignments
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('registers.badanusaha_id', $badanUsahaIds);
                }

                if (! empty($divisiIds)) {
                    $query->whereIn('registers.divisi_id', $divisiIds);
                }

                if (! empty($regionIds)) {
                    $query->whereIn('registers.region_id', $regionIds);
                }

                if (! empty($clusterIds)) {
                    $query->whereIn('registers.cluster_id', $clusterIds);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegisters::route('/'),
            'create' => CreateRegister::route('/create'),
            'view' => ViewRegister::route('/{record}'),
            'edit' => EditRegister::route('/{record}/edit'),
        ];
    }
}
