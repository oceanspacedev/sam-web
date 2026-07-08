<?php

namespace App\Filament\Resources\Outlets;

use App\Filament\Resources\Outlets\Pages\CreateOutlet;
use App\Filament\Resources\Outlets\Pages\EditOutlet;
use App\Filament\Resources\Outlets\Pages\ListOutlets;
use App\Filament\Resources\Outlets\Pages\ViewOutlet;
use App\Filament\Resources\Outlets\RelationManagers\ChangeArchivesRelationManager;
use App\Models\Outlet;
use App\Services\FilenameGeneratorService;
use App\Support\FilamentOrganizationalScope;
use App\Support\FilamentTableEagerLoad;
use App\Support\OrganizationalHierarchyOptions;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class OutletResource extends Resource
{
    protected static ?string $model = Outlet::class;

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-building-07';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        Group::make([
                            Section::make('Informasi Outlet')
                                ->schema([
                                    TextInput::make('kode_outlet')
                                        ->required()
                                        ->regex('/^\S+$/')
                                        ->helperText('Kode outlet tidak boleh mengandung spasi')
                                        ->rule(function (callable $get) {
                                            return function ($attribute, $value, $fail) use ($get) {
                                                // Cek apakah kode_outlet mengandung LEAD
                                                if (stripos($value, 'LEAD') === 0) {
                                                    $fail('Kode Outlet tidak boleh diawali dengan "LEAD".');

                                                    return;
                                                }

                                                $divisiId = $get('divisi_id');
                                                $outletId = $get('id');

                                                $exists = DB::table('outlets')
                                                    ->where('kode_outlet', $value)
                                                    ->where('divisi_id', $divisiId)
                                                    ->where('id', '!=', $outletId)
                                                    ->whereNull('deleted_at')
                                                    ->exists();

                                                if ($exists) {
                                                    $fail('Kode Outlet sudah digunakan untuk divisi ini.');
                                                }
                                            };
                                        })
                                        ->maxLength(255)
                                        ->label('Kode Outlet')
                                        ->placeholder('Masukkan kode outlet'),
                                    TextInput::make('nama_outlet')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Nama Outlet')
                                        ->placeholder('Masukkan nama outlet'),
                                    TextInput::make('distric')
                                        ->required()
                                        ->maxLength(255)
                                        ->label('Distrik')
                                        ->placeholder('Masukkan distrik outlet'),
                                    TextInput::make('latlong')
                                        ->maxLength(255)
                                        ->label('Latitude/Longitude')
                                        ->placeholder('Masukkan koordinat latitude dan longitude outlet'),
                                    Textarea::make('alamat_outlet')
                                        ->required()
                                        ->columnSpanFull()
                                        ->label('Alamat Outlet')
                                        ->placeholder('Masukkan alamat lengkap outlet'),
                                ])
                                ->columns(2),
                            Section::make('Kontak & Pemilik Outlet')
                                ->schema([
                                    TextInput::make('nama_pemilik_outlet')
                                        ->maxLength(255)
                                        ->label('Nama Pemilik Outlet')
                                        ->placeholder('Masukkan nama pemilik outlet'),
                                    TextInput::make('nomer_tlp_outlet')
                                        ->maxLength(255)
                                        ->label('Nomor Telepon Outlet')
                                        ->placeholder('Masukkan nomor telepon outlet'),
                                ])
                                ->columns(2),
                            Section::make('Foto & Video')
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
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'outlet-photo', $userId);
                                                // Output: op241204123-a1b2c3-550e8400-e29b-41d4-a716-446655440000.jpg
                                            }),
                                        FileUpload::make('poto_depan')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Depan')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'outlet-photo', $userId);
                                            }),
                                        FileUpload::make('poto_kiri')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Kiri')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'outlet-photo', $userId);
                                            }),
                                        FileUpload::make('poto_kanan')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto Kanan')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'outlet-photo', $userId);
                                            }),
                                        FileUpload::make('poto_ktp')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->label('Foto KTP Pemilik')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'outlet-ktp', $userId);
                                            }),
                                        FileUpload::make('video')
                                            ->disk(StorageDisk::default())
                                            ->fetchFileInformation(false)
                                            ->acceptedFileTypes(['video/mp4', 'video/avi', 'video/mkv'])
                                            ->label('Video Toko')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $userId = Auth::id();
                                                $filenameGenerator = new FilenameGeneratorService;

                                                return $filenameGenerator->generate($file, 'outlet-video', $userId);
                                            }),
                                    ]),
                                ]),
                        ])
                            ->columnSpan(['default' => 1, 'xl' => 8]),
                        Group::make([
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
                                                ->options(fn (): array => OrganizationalHierarchyOptions::badanUsaha())
                                                ->getSearchResultsUsing(fn (string $search): array => OrganizationalHierarchyOptions::searchBadanUsaha($search))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::badanUsahaLabel($value))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('divisi_id', null);
                                                    $set('region_id', null);
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('divisi_id')
                                                ->label('Divisi')
                                                ->searchable()
                                                ->required()
                                                ->reactive()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::division($get('badanusaha_id')))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchDivision($search, $get('badanusaha_id')))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('region_id', null);
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('region_id')
                                                ->label('Region')
                                                ->searchable()
                                                ->required()
                                                ->reactive()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('divisi_id')))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('divisi_id')))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::regionLabel($value))
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('cluster_id', null);
                                                }),
                                            Select::make('cluster_id')
                                                ->label('Cluster')
                                                ->searchable()
                                                ->required()
                                                ->reactive()
                                                ->options(fn (callable $get): array => OrganizationalHierarchyOptions::cluster($get('region_id')))
                                                ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchCluster($search, $get('region_id')))
                                                ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::clusterLabel($value)),
                                        ]),
                                ]),
                            Section::make('Status & Limit Outlet')
                                ->schema([
                                    Grid::make([
                                        'default' => 1,
                                    ])->schema([
                                        Select::make('status_outlet')
                                            ->label('Status Outlet')
                                            ->searchable()
                                            ->options([
                                                'MAINTAIN' => 'MAINTAIN',
                                                'UNMAINTAIN' => 'UNMAINTAIN',
                                                'UNPRODUCTIVE' => 'UNPRODUCTIVE',
                                            ])
                                            ->required(),
                                        TextInput::make('limit')
                                            ->required()
                                            ->numeric()
                                            ->label('Limit')
                                            ->default('0')
                                            ->placeholder('Masukkan limit outlet'),
                                        TextInput::make('radius')
                                            ->required()
                                            ->numeric()
                                            ->label('Radius')
                                            ->default('100')
                                            ->helperText('Default 100 meter untuk checkin sales visit')
                                            ->placeholder('Masukkan radius outlet'),
                                    ]),
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
                Grid::make(12)
                    ->schema([
                        Group::make([
                            Section::make('Informasi Outlet')
                                ->schema([
                                    TextEntry::make('kode_outlet')
                                        ->label('Kode Outlet'),
                                    TextEntry::make('nama_outlet')
                                        ->label('Nama Outlet'),
                                    TextEntry::make('distric')
                                        ->label('Distrik'),
                                    TextEntry::make('latlong')
                                        ->label('Latitude/Longitude')
                                        ->url(fn ($state) => $state ? "https://www.google.com/maps/place/{$state}" : null, shouldOpenInNewTab: true)
                                        ->color('primary'),
                                    TextEntry::make('alamat_outlet')
                                        ->label('Alamat Outlet')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                            Section::make('Kontak & Pemilik Outlet')
                                ->schema([
                                    TextEntry::make('nama_pemilik_outlet')
                                        ->label('Nama Pemilik Outlet'),
                                    TextEntry::make('nomer_tlp_outlet')
                                        ->label('Nomor Telepon Outlet'),
                                ])
                                ->columns(2),
                            Section::make('Foto & Video')
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
                                            ->formatStateUsing(function ($state) {
                                                if (! $state || $state === '-') {
                                                    return '-';
                                                }

                                                return new HtmlString('<a href="'.StorageDisk::url($state).'" target="_blank" class="text-primary-600 hover:underline">Lihat Video</a>');
                                            })
                                            ->html(),
                                    ]),
                                ]),
                        ])
                            ->columnSpan(['default' => 12, 'xl' => 8]),
                        Group::make([
                            Section::make('Struktur Organisasi')
                                ->schema([
                                    TextEntry::make('badanusaha.name')
                                        ->label('Badan Usaha'),
                                    TextEntry::make('divisi.name')
                                        ->label('Divisi'),
                                    TextEntry::make('region.name')
                                        ->label('Region'),
                                    TextEntry::make('cluster.name')
                                        ->label('Cluster'),
                                ]),
                            Section::make('Status & Limit Outlet')
                                ->schema([
                                    TextEntry::make('status_outlet')
                                        ->label('Status Outlet')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'MAINTAIN' => 'success',
                                            'UNMAINTAIN' => 'warning',
                                            'UNPRODUCTIVE' => 'danger',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('limit')
                                        ->label('Limit')
                                        ->money('IDR'),
                                    TextEntry::make('radius')
                                        ->label('Radius')
                                        ->suffix(' meter'),
                                ]),
                            Section::make('Riwayat Reset')
                                ->schema([
                                    TextEntry::make('last_reset_at')
                                        ->label('Reset Terakhir')
                                        ->dateTime('d M Y, H:i')
                                        ->placeholder('Belum pernah reset'),
                                    TextEntry::make('reset_count_yearly')
                                        ->label('Jumlah Reset (Tahun Ini)')
                                        ->suffix(' kali')
                                        ->default(0)
                                        ->badge()
                                        ->color(fn ($state): string => match (true) {
                                            $state >= 3 => 'danger',
                                            $state >= 2 => 'warning',
                                            default => 'gray',
                                        }),
                                ])
                                ->columns(2)
                                ->collapsible(),
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
                TextColumn::make('kode_outlet')
                    ->label('Kode Outlet')
                    ->searchable(),
                TextColumn::make('badanusaha.name')
                    ->label('Badan Usaha')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('divisi.name')
                    ->label('Divisi')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region.name')
                    ->label('Region')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cluster.name')
                    ->label('Cluster')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status_outlet')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'MAINTAIN' => 'success',
                        'UNMAINTAIN' => 'warning',
                        'UNPRODUCTIVE' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                TextColumn::make('nama_pemilik_outlet')
                    ->label('Nama Pemilik Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nomer_tlp_outlet')
                    ->label('Nomor Telepon Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distric')
                    ->label('Distrik'),
                TextColumn::make('poto_shop_sign')
                    ->label('Foto Tanda Outlet')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_depan')
                    ->label('Foto Depan')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_kiri')
                    ->label('Foto Kiri')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_kanan')
                    ->label('Foto Kanan')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_ktp')
                    ->label('Foto KTP')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('FOTO KTP'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('video')
                    ->label('Video Outlet')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('VIDEO'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('limit')
                    ->label('Limit'),
                TextColumn::make('radius')
                    ->label('Radius')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latlong')
                    ->label('Lokasi (LatLong)')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString('LOKASI'))
                    ->url(fn (?string $state): ?string => filled($state) ? 'https://www.google.com/maps/place/'.$state : null, shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('kode_outlet', 'asc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                SelectFilter::make('status_outlet')
                    ->label('Status Outlet')
                    ->options([
                        'MAINTAIN' => 'MAINTAIN',
                        'UNMAINTAIN' => 'UNMAINTAIN',
                        'UNPRODUCTIVE' => 'UNPRODUCTIVE',
                    ]),
                Filter::make('region')
                    ->schema([
                        Select::make('businessEntity')
                            ->label('Badan Usaha')
                            ->reactive()
                            ->searchable()
                            ->options(fn (): array => OrganizationalHierarchyOptions::badanUsaha())
                            ->getSearchResultsUsing(fn (string $search): array => OrganizationalHierarchyOptions::searchBadanUsaha($search))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::badanUsahaLabel($value))
                            ->placeholder('Pilih Business Entity')
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $set('division', null);
                                $set('region', null);
                            }),
                        Select::make('division')
                            ->label('Divisi')
                            ->reactive()
                            ->searchable()
                            ->options(fn (callable $get): array => OrganizationalHierarchyOptions::division($get('businessEntity')))
                            ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchDivision($search, $get('businessEntity')))
                            ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value))
                            ->placeholder('Pilih Division')
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $set('region', null);
                            }),
                        Select::make('region')
                            ->label('Region')
                            ->searchable()
                            ->placeholder('Pilih Region')
                            ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('division')))
                            ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('division')))
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
                    ->hidden(fn () => ! Gate::any(['RestoreAny:Outlet', 'ForceDeleteAny:Outlet'], Outlet::class)),

            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),
                    BulkAction::make('resetLocation')
                        ->label('Reset Lokasi Outlet')
                        ->icon('heroicon-o-map-pin')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $successCount = 0;
                            $errors = [];

                            $records->each(function (Outlet $record) use (&$successCount, &$errors): void {
                                try {
                                    // Refresh to get latest data from database
                                    $record->refresh();

                                    $normalizedCount = static::enforceResetLimits(
                                        $record->last_reset_at,
                                        (int) $record->reset_count_yearly,
                                        'Reset lokasi outlet',
                                        $record->kode_outlet
                                    );

                                    $beforeArchive = $record->changeArchiveSnapshot();
                                    $record->update([
                                        'latlong' => null,
                                        'alamat_outlet' => '-',
                                        'poto_shop_sign' => null,
                                        'poto_depan' => null,
                                        'poto_kiri' => null,
                                        'poto_kanan' => null,
                                        'video' => null,
                                        'last_reset_at' => now(),
                                        'reset_count_yearly' => $normalizedCount + 1,
                                    ]);
                                    $record->refresh();
                                    $record->recordChangeArchive(
                                        \App\Models\OutletChangeArchive::ACTION_RESET_LOCATION,
                                        Auth::user(),
                                        $beforeArchive,
                                        $record->changeArchiveSnapshot()
                                    );
                                    $successCount++;
                                } catch (ValidationException $e) {
                                    // Get first error message from validation errors
                                    $firstError = collect($e->errors())->flatten()->first();
                                    $errors[] = $firstError ?? $e->getMessage();
                                }
                            });

                            if (count($errors) > 0) {
                                Notification::make()
                                    ->title('Reset lokasi gagal')
                                    ->body(implode("\n", array_slice(array_unique($errors), 0, 3)))
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Berhasil')
                                ->body("Lokasi {$successCount} outlet berhasil direset.")
                                ->success()
                                ->send();
                        })
                        ->authorize(fn () => Gate::allows('ResetLocation:Outlet'))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reset')
                        ->label('Reset Data Outlet')
                        ->icon('heroicon-o-building-storefront')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $successCount = 0;
                            $errors = [];

                            $records->each(function (Outlet $record) use (&$successCount, &$errors): void {
                                try {
                                    // Refresh to get latest data from database
                                    $record->refresh();

                                    $normalizedCount = static::enforceResetLimits(
                                        $record->last_reset_at,
                                        (int) $record->reset_count_yearly,
                                        'Reset data outlet',
                                        $record->kode_outlet
                                    );

                                    $beforeArchive = $record->changeArchiveSnapshot();
                                    $record->update([
                                        'nama_pemilik_outlet' => null,
                                        'nomer_tlp_outlet' => null,
                                        'alamat_outlet' => '-',
                                        'latlong' => null,
                                        'poto_shop_sign' => null,
                                        'poto_depan' => null,
                                        'poto_kiri' => null,
                                        'poto_kanan' => null,
                                        'video' => null,
                                        'last_reset_at' => now(),
                                        'reset_count_yearly' => $normalizedCount + 1,
                                    ]);
                                    $record->refresh();
                                    $record->recordChangeArchive(
                                        \App\Models\OutletChangeArchive::ACTION_RESET_DATA,
                                        Auth::user(),
                                        $beforeArchive,
                                        $record->changeArchiveSnapshot()
                                    );
                                    $successCount++;
                                } catch (ValidationException $e) {
                                    // Get first error message from validation errors
                                    $firstError = collect($e->errors())->flatten()->first();
                                    $errors[] = $firstError ?? $e->getMessage();
                                }
                            });

                            if (count($errors) > 0) {
                                Notification::make()
                                    ->title('Reset data gagal')
                                    ->body(implode("\n", array_slice(array_unique($errors), 0, 3)))
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Berhasil')
                                ->body("Data {$successCount} outlet berhasil direset.")
                                ->success()
                                ->send();
                        })
                        ->authorize(fn () => Gate::allows('Reset:Outlet'))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Enforce shared reset limits: 30-day cooldown and max 4 per calendar year.
     *
     * @throws ValidationException
     */
    public static function enforceResetLimits(?Carbon $lastResetAt, int $resetCountYearly, string $actionLabel, string $kodeOutlet): int
    {
        $now = now();

        if ($lastResetAt && $lastResetAt->diffInDays($now) < 30) {
            $nextAllowedAt = $lastResetAt->copy()->addDays(30)->translatedFormat('l, d F Y H:i');

            throw ValidationException::withMessages([
                'reset' => sprintf(
                    '%s untuk outlet %s hanya dapat dilakukan setiap 30 hari. Coba lagi setelah %s.',
                    $actionLabel,
                    $kodeOutlet,
                    $nextAllowedAt
                ),
            ]);
        }

        if ($lastResetAt && $lastResetAt->year !== $now->year) {
            $resetCountYearly = 0;
        }

        if ($resetCountYearly >= 4) {
            $nextWindow = $now->copy()->startOfYear()->addYear()->toDateString();

            throw ValidationException::withMessages([
                'reset' => sprintf(
                    '%s untuk outlet %s sudah mencapai batas 4 kali di tahun %s. Coba lagi setelah %s.',
                    $actionLabel,
                    $kodeOutlet,
                    $now->year,
                    $nextWindow
                ),
            ]);
        }

        return $resetCountYearly;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where(function (Builder $query) use ($user): void {
                FilamentOrganizationalScope::applyDirectColumns($query, $user, 'outlets');
            })
            ->with(FilamentTableEagerLoad::fullHierarchy());
    }

    public static function getRelations(): array
    {
        return [
            ChangeArchivesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutlets::route('/'),
            'create' => CreateOutlet::route('/create'),
            'edit' => EditOutlet::route('/{record}/edit'),
            'view' => ViewOutlet::route('/{record}'),
        ];
    }
}
