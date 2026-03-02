<?php

namespace App\Filament\Resources\Visits;

use App\Filament\Resources\Visits\Pages\CreateVisit;
use App\Filament\Resources\Visits\Pages\EditVisit;
use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Filament\Resources\Visits\Pages\ViewVisit;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\User;
use App\Models\Visit;
use App\Services\FilenameGeneratorService;
use App\Services\SystemSettingResolver;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-camera';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Visit Information')
                            ->schema([
                                DateTimePicker::make('tanggal_visit')
                                    ->default(Carbon::parse(now())->startOfDay())
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set, callable $get, string $context) {
                                        if ($context !== 'edit' || $get('tipe_visit') !== 'EXTRACALL') {
                                            $set('outlet_id', null);
                                        }
                                    })
                                    ->label('Tanggal Visit'),
                                ToggleButtons::make('tipe_visit')
                                    ->label('Tipe Visit')
                                    ->required()
                                    ->inline()
                                    ->options([
                                        'PLANNED' => 'PLANNED',
                                        'EXTRACALL' => 'EXTRACALL',
                                    ])
                                    ->icons([
                                        'PLANNED' => 'heroicon-o-calendar',
                                        'EXTRACALL' => 'heroicon-o-bolt',
                                    ])
                                    ->colors([
                                        'PLANNED' => 'primary',
                                        'EXTRACALL' => 'info',
                                    ])
                                    ->default('EXTRACALL')
                                    ->live()
                                    ->afterStateUpdated(fn (callable $set) => $set('outlet_id', null)),
                                Select::make('user_id')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (callable $set) => $set('outlet_id', null))
                                    ->label('Pilih User')
                                    ->placeholder('Cari User berdasarkan nama lengkap')
                                    ->getSearchResultsUsing(function (string $search) {
                                        $currentUser = Auth::user();

                                        // SECURITY FIX: Apply scope filtering to user search
                                        $query = User::query()
                                            ->with(['badanUsahas:id,name', 'divisis:id,name'])
                                            ->where('nama_lengkap', 'like', "%{$search}%");

                                        // Apply scope filtering based on current user's role
                                        if ($currentUser && $currentUser->role && $currentUser->role->organizational_scope_level !== 'all') {
                                            $badanUsahaIds = $currentUser->badanUsahas()->pluck('badan_usahas.id')->toArray();
                                            $divisiIds = $currentUser->divisis()->pluck('divisions.id')->toArray();
                                            $regionIds = $currentUser->regions()->pluck('regions.id')->toArray();
                                            $clusterIds = $currentUser->clusters()->pluck('clusters.id')->toArray();

                                            $query->where(function ($q) use ($badanUsahaIds, $divisiIds, $regionIds, $clusterIds) {
                                                if (! empty($badanUsahaIds)) {
                                                    $q->whereHas('badanUsahas', fn ($sq) => $sq->whereIn('badan_usahas.id', $badanUsahaIds));
                                                }
                                                if (! empty($divisiIds)) {
                                                    $q->whereHas('divisis', fn ($sq) => $sq->whereIn('divisions.id', $divisiIds));
                                                }
                                                if (! empty($regionIds)) {
                                                    $q->whereHas('regions', fn ($sq) => $sq->whereIn('regions.id', $regionIds));
                                                }
                                                if (! empty($clusterIds)) {
                                                    $q->whereHas('clusters', fn ($sq) => $sq->whereIn('clusters.id', $clusterIds));
                                                }
                                            });
                                        }

                                        return $query
                                            ->orderBy('nama_lengkap')
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(function ($user) {
                                                $badanusahaName = $user->badanUsahas->first()->name ?? 'Tidak ada badan usaha';
                                                $divisiName = $user->divisis->first()->name ?? 'Tidak ada divisi';

                                                return [$user->id => "{$user->nama_lengkap} - {$badanusahaName} / {$divisiName}"];
                                            })
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        if (! $value) {
                                            return null;
                                        }
                                        $user = User::with(['badanUsahas:id,name', 'divisis:id,name'])->find($value);
                                        if (! $user) {
                                            return null;
                                        }
                                        $badanusahaName = $user->badanUsahas->first()->name ?? 'Tidak ada badan usaha';
                                        $divisiName = $user->divisis->first()->name ?? 'Tidak ada divisi';

                                        return "{$user->nama_lengkap} - {$badanusahaName} / {$divisiName}";
                                    }),
                                // Polymorphic visit target selector
                                Select::make('visitable_type')
                                    ->label('Tipe Target')
                                    ->options(function () {
                                        $options = [
                                            'App\\Models\\Outlet' => 'Outlet',
                                        ];

                                        // Show register target only when system setting enables register visit.
                                        $allowsRegisterVisit = app(SystemSettingResolver::class)->hasAnyAllowRegisterVisit();

                                        if ($allowsRegisterVisit) {
                                            $options['App\\Models\\Register'] = 'Register (LEAD/NOO)';
                                        }

                                        return $options;
                                    })
                                    ->required()
                                    ->live()
                                    ->default('App\\Models\\Outlet')
                                    ->afterStateUpdated(fn (callable $set) => $set('visitable_id', null)),
                                Select::make('visitable_id')
                                    ->label('Target')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (callable $get): bool => ! $get('user_id'))
                                    ->placeholder(fn (callable $get) => ! $get('user_id')
                                        ? 'Pilih user terlebih dahulu'
                                        : ($get('tipe_visit') === 'PLANNED'
                                            ? 'Pilih target dari Plan Visit'
                                            : 'Cari target berdasarkan nama/kode'))
                                    ->helperText(fn (callable $get) => $get('tipe_visit') === 'PLANNED' && $get('user_id')
                                        ? 'Hanya menampilkan target yang ada di Plan Visit untuk user dan tanggal yang dipilih'
                                        : ($get('tipe_visit') === 'EXTRACALL' && $get('user_id')
                                            ? 'Hanya menampilkan target sesuai scope user yang dipilih'
                                            : null))
                                    ->getSearchResultsUsing(function (string $search, callable $get) {
                                        $type = $get('visitable_type');
                                        $tipeVisit = $get('tipe_visit');
                                        $selectedUserId = $get('user_id');
                                        $tanggalVisit = $get('tanggal_visit');

                                        // Jika user belum dipilih, return empty
                                        if (! $selectedUserId) {
                                            return [];
                                        }

                                        // Jika PLANNED, filter berdasarkan PlanVisit
                                        if ($tipeVisit === 'PLANNED' && $selectedUserId && $tanggalVisit) {
                                            $visitDate = Carbon::parse($tanggalVisit);

                                            // Ambil visitable_id dan visitable_type dari PlanVisit untuk user dan tanggal tersebut
                                            $plannedVisits = PlanVisit::query()
                                                ->where('user_id', $selectedUserId)
                                                ->whereNull('realized_at')
                                                ->where(function ($q) use ($visitDate) {
                                                    // Daily: tanggal_visit sama dengan tanggal yang dipilih
                                                    $q->where(function ($daily) use ($visitDate) {
                                                        $daily->where('schedule_scope', 'daily')
                                                            ->whereDate('tanggal_visit', $visitDate);
                                                    })
                                                    // Weekly: tanggal yang dipilih dalam range period_start - period_end
                                                        ->orWhere(function ($weekly) use ($visitDate) {
                                                            $weekly->where('schedule_scope', 'weekly')
                                                                ->whereDate('period_start', '<=', $visitDate)
                                                                ->whereDate('period_end', '>=', $visitDate);
                                                        });
                                                })
                                                ->get();

                                            $plannedIds = $plannedVisits
                                                ->where('visitable_type', $type)
                                                ->pluck('visitable_id')
                                                ->toArray();

                                            if (empty($plannedIds)) {
                                                return [];
                                            }

                                            if ($type === 'App\\Models\\Outlet') {
                                                return Outlet::query()
                                                    ->with(['badanusaha:id,name', 'divisi:id,name'])
                                                    ->whereIn('id', $plannedIds)
                                                    ->where(function ($q) use ($search) {
                                                        $q->where('nama_outlet', 'like', "%{$search}%")
                                                            ->orWhere('kode_outlet', 'like', "%{$search}%");
                                                    })
                                                    ->orderBy('nama_outlet')
                                                    ->limit(50)
                                                    ->get()
                                                    ->mapWithKeys(function ($outlet) {
                                                        $badanusahaName = $outlet->badanusaha->name ?? '-';
                                                        $divisiName = $outlet->divisi->name ?? '-';

                                                        return [$outlet->id => "[{$outlet->kode_outlet}] {$outlet->nama_outlet} - {$badanusahaName} / {$divisiName}"];
                                                    })
                                                    ->toArray();
                                            }

                                            return Register::query()
                                                ->where(function ($q) use ($search) {
                                                    $q->where('nama_outlet', 'like', "%{$search}%")
                                                        ->orWhere('kode_outlet', 'like', "%{$search}%");
                                                })
                                                ->where(function ($q) {
                                                    $q->whereNull('status')->orWhere('status', '!=', 'APPROVED');
                                                })
                                                ->whereDoesntHave('outlet')
                                                ->whereIn('id', $plannedIds)
                                                ->orderBy('nama_outlet')
                                                ->limit(50)
                                                ->get()
                                                ->filter(fn (Register $register) => app(SystemSettingResolver::class)->allowsRegisterVisitForModel($register))
                                                ->mapWithKeys(function ($register) {
                                                    return [$register->id => "[{$register->kode_outlet}] {$register->nama_outlet} - Register"];
                                                })
                                                ->toArray();
                                        }

                                        // EXTRACALL: Tampilkan target sesuai scope SELECTED USER
                                        $selectedUser = User::with('role')->find($selectedUserId);

                                        if (! $selectedUser) {
                                            return [];
                                        }

                                        if ($type === 'App\\Models\\Outlet') {
                                            return Outlet::query()
                                                ->with(['badanusaha:id,name', 'divisi:id,name'])
                                                ->accessibleTo($selectedUser)
                                                ->where(function ($q) use ($search) {
                                                    $q->where('nama_outlet', 'like', "%{$search}%")
                                                        ->orWhere('kode_outlet', 'like', "%{$search}%");
                                                })
                                                ->orderBy('nama_outlet')
                                                ->limit(50)
                                                ->get()
                                                ->mapWithKeys(function ($outlet) {
                                                    $badanusahaName = $outlet->badanusaha->name ?? '-';
                                                    $divisiName = $outlet->divisi->name ?? '-';

                                                    return [$outlet->id => "[{$outlet->kode_outlet}] {$outlet->nama_outlet} - {$badanusahaName} / {$divisiName}"];
                                                })
                                                ->toArray();
                                        }

                                        return Register::query()
                                            ->where(function ($q) use ($search) {
                                                $q->where('nama_outlet', 'like', "%{$search}%")
                                                    ->orWhere('kode_outlet', 'like', "%{$search}%");
                                            })
                                            ->where(function ($q) {
                                                $q->whereNull('status')->orWhere('status', '!=', 'APPROVED');
                                            })
                                            ->whereDoesntHave('outlet')
                                            ->orderBy('nama_outlet')
                                            ->limit(50)
                                            ->get()
                                            ->filter(fn (Register $register) => app(SystemSettingResolver::class)->allowsRegisterVisitForModel($register))
                                            ->mapWithKeys(function ($register) {
                                                return [$register->id => "[{$register->kode_outlet}] {$register->nama_outlet} - Register"];
                                            })
                                            ->toArray();
                                    })
                                    ->options(function (callable $get) {
                                        $id = $get('visitable_id');
                                        $type = $get('visitable_type');

                                        if (! $id || ! $type) {
                                            return [];
                                        }

                                        if ($type === 'App\\Models\\Outlet') {
                                            $outlet = Outlet::with(['badanusaha:id,name', 'divisi:id,name'])->find($id);
                                            if (! $outlet) {
                                                return [];
                                            }
                                            $badanusahaName = $outlet->badanusaha->name ?? '-';
                                            $divisiName = $outlet->divisi->name ?? '-';

                                            return [$outlet->id => "[{$outlet->kode_outlet}] {$outlet->nama_outlet} - {$badanusahaName} / {$divisiName}"];
                                        }

                                        $register = Register::find($id);
                                        if (! $register) {
                                            return [];
                                        }

                                        return [$register->id => "[{$register->kode_outlet}] {$register->nama_outlet} - Register"];
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $type = $get('visitable_type');
                                        if ($state && $type === 'App\\Models\\Outlet') {
                                            $outlet = Outlet::find($state);
                                            $set('latlong_in', $outlet?->latlong ?? null);
                                        } elseif ($state && $type === 'App\\Models\\Register') {
                                            $register = Register::find($state);
                                            $set('latlong_in', $register?->latlong ?? null);
                                        } else {
                                            $set('latlong_in', null);
                                        }
                                    }),
                            ])
                            ->columns(2),
                        Section::make('Location & Timing')
                            ->schema([
                                TextInput::make('latlong_in')
                                    ->maxLength(255)
                                    ->label('LatLong In')
                                    ->placeholder('Latitude and Longitude at the start'),
                                TextInput::make('latlong_out')
                                    ->maxLength(255)
                                    ->label('LatLong Out')
                                    ->placeholder('Latitude and Longitude at the end')
                                    ->visible(fn (string $context): bool => $context === 'edit'),
                                DateTimePicker::make('check_in_time')
                                    ->label('Check-in Time')
                                    ->default(now()),
                                DateTimePicker::make('check_out_time')
                                    ->label('Check-out Time')
                                    ->default(now())
                                    ->visible(fn (string $context): bool => $context === 'edit'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make('Files')
                            ->schema([
                                FileUpload::make('picture_visit_in')
                                    ->image()
                                    ->columnSpanFull()
                                    ->required()
                                    ->disk(StorageDisk::default())
                                    ->label('Picture at Start of Visit')
                                    ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                        /** @var int|string|null $userId */
                                        $userId = Auth::id() ?? $get('user_id');
                                        $filenameGenerator = new FilenameGeneratorService;

                                        return $filenameGenerator->generate($file, 'visit-in', $userId);
                                        // Output: vi241204123-a1b2c3-550e8400-e29b-41d4-a716-446655440000.jpg
                                    }),
                                FileUpload::make('picture_visit_out')
                                    ->image()
                                    ->columnSpanFull()
                                    // ->required()
                                    ->disk(StorageDisk::default())
                                    ->label('Picture at End of Visit')
                                    ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                        /** @var int|string|null $userId */
                                        $userId = Auth::id() ?? $get('user_id');
                                        $filenameGenerator = new FilenameGeneratorService;

                                        return $filenameGenerator->generate($file, 'visit-out', $userId);
                                        // Output: vo241204123-a1b2c3-550e8400-e29b-41d4-a716-446655440000.jpg
                                    })
                                    ->visible(fn (string $context): bool => $context === 'edit'),
                            ]),
                        Section::make('Transaction Information')
                            ->schema([
                                ToggleButtons::make('transaksi')
                                    ->label('Transaksi')
                                    ->required()
                                    ->inline()
                                    ->options([
                                        'YES' => 'YES',
                                        'NO' => 'NO',
                                    ])
                                    ->icons([
                                        'YES' => 'heroicon-o-check-circle',
                                        'NO' => 'heroicon-o-x-circle',
                                    ])
                                    ->colors([
                                        'YES' => 'success',
                                        'NO' => 'danger',
                                    ])
                                    ->default('NO'),
                                Textarea::make('laporan_visit')
                                    ->columnSpanFull()
                                    ->label('Laporan Visit'),
                            ])
                            ->visible(fn (string $context): bool => $context === 'edit'),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->schema([
                        Group::make([
                            Section::make('Visit Information')
                                ->schema([
                                    TextEntry::make('tanggal_visit')
                                        ->label('Tanggal Visit')
                                        ->date('d M Y'),
                                    TextEntry::make('tipe_visit')
                                        ->label('Tipe Visit')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'PLANNED' => 'primary',
                                            'EXTRACALL' => 'info',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('user.nama_lengkap')
                                        ->label('User'),
                                    TextEntry::make('visitable.nama_outlet')
                                        ->label('Target')
                                        ->badge()
                                        ->color(fn ($record) => $record->isOutletVisit() ? 'primary' : 'warning')
                                        ->formatStateUsing(fn ($state, $record) => $state ?? '-')
                                        ->tooltip(fn ($record) => $record->isOutletVisit() ? 'Outlet' : 'Register'),
                                ])
                                ->columns(2),
                            Section::make('Location & Timing')
                                ->schema([
                                    TextEntry::make('latlong_in')
                                        ->label('Lokasi Check-In')
                                        ->url(fn ($state) => $state ? "https://www.google.com/maps/place/{$state}" : null, shouldOpenInNewTab: true)
                                        ->color('primary'),
                                    TextEntry::make('latlong_out')
                                        ->label('Lokasi Check-Out')
                                        ->url(fn ($state) => $state ? "https://www.google.com/maps/place/{$state}" : null, shouldOpenInNewTab: true)
                                        ->color('primary'),
                                    TextEntry::make('check_in_time')
                                        ->label('Check-in Time')
                                        ->time(),
                                    TextEntry::make('check_out_time')
                                        ->label('Check-out Time')
                                        ->time(),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpan(['lg' => 8]),
                        Group::make([
                            Section::make('Files')
                                ->schema([
                                    ImageEntry::make('picture_visit_in')
                                        ->label('Picture at Start of Visit')
                                        ->disk(StorageDisk::default())
                                        ->columnSpanFull(),
                                    ImageEntry::make('picture_visit_out')
                                        ->label('Picture at End of Visit')
                                        ->disk(StorageDisk::default())
                                        ->columnSpanFull(),
                                ]),
                            Section::make('Transaction Information')
                                ->schema([
                                    TextEntry::make('transaksi')
                                        ->label('Transaksi')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'YES' => 'success',
                                            'NO' => 'danger',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('laporan_visit')
                                        ->label('Laporan Visit')
                                        ->columnSpanFull(),
                                ]),
                        ])
                            ->columnSpan(['lg' => 4]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_visit')
                    ->label('Tanggal Visit')
                    ->date('d M Y'),
                TextColumn::make('user.nama_lengkap')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('visitable_type')
                    ->label('Jenis Target')
                    ->badge()
                    ->formatStateUsing(function (?string $state): string {
                        $normalized = strtolower((string) $state);

                        return str_contains($normalized, 'register') ? 'REGISTER' : 'OUTLET';
                    })
                    ->color(function (?string $state): string {
                        $normalized = strtolower((string) $state);

                        return str_contains($normalized, 'register') ? 'warning' : 'primary';
                    }),
                TextColumn::make('visitable.nama_outlet')
                    ->label('Target')
                    ->formatStateUsing(fn ($state) => $state ?? '-')
                    ->searchable(),
                TextColumn::make('tipe_visit')
                    ->label('Tipe Visit')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'PLANNED' => 'primary',
                        'EXTRACALL' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('latlong_in')
                    ->label('Lokasi Check-In')
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString($state ? 'LOKASI' : '-'))
                    ->url(fn (?string $state): ?string => filled($state) ? 'https://www.google.com/maps/place/'.$state : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latlong_out')
                    ->label('Lokasi Check-Out')
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString($state ? 'LOKASI' : '-'))
                    ->url(fn (?string $state): ?string => filled($state) ? 'https://www.google.com/maps/place/'.$state : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('check_in_time')
                    ->label('Jam Check-In')
                    ->time()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('check_out_time')
                    ->label('Jam Check-Out')
                    ->time()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('picture_visit_in')
                    ->label('Foto Check-In')
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString($state ? 'FOTO' : '-'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('picture_visit_out')
                    ->label('Foto Check-Out')
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString($state ? 'FOTO' : '-'))
                    ->url(fn (?string $state): ?string => filled($state) ? StorageDisk::url($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transaksi')
                    ->label('Transaksi')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'YES' => 'success',
                        'NO' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('durasi_visit')
                    ->label('Durasi Visit')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? $state.' menit' : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('tanggal_visit_from')
                            ->label('Tanggal Visit Mulai'),
                        DatePicker::make('tanggal_visit_until')
                            ->label('Tanggal Visit Akhir'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_visit_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal_visit', '>=', $date),
                            )
                            ->when(
                                $data['tanggal_visit_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal_visit', '<=', $date),
                            );
                    }),
                TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['RestoreAny:Visit', 'ForceDeleteAny:Visit'], Visit::class)),

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
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();

        // CRITICAL: Block access if user or role is null
        if (! $user || ! $user->role) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        $role = $user->role;
        $scopeLevel = $role->organizational_scope_level;

        // CRITICAL: Block access if scope level is null
        if (! $scopeLevel) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        if ($scopeLevel === 'all') {
            return parent::getEloquentQuery();
        }

        // Get user's organizational assignments from pivot tables
        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
        $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
        $regionIds = $user->regions()->pluck('regions.id')->toArray();
        $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

        // CRITICAL: If user has no assignments at all, block access
        $hasAnyAssignment = ! empty($badanUsahaIds) || ! empty($divisiIds) || ! empty($regionIds) || ! empty($clusterIds);
        if (! $hasAnyAssignment) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        $query = parent::getEloquentQuery();

        // Apply filters based on user pivot assignments
        if (! empty($badanUsahaIds)) {
            $query->whereHas('user.badanUsahas', function ($q) use ($badanUsahaIds) {
                $q->whereIn('badan_usahas.id', $badanUsahaIds);
            });
        }

        if (! empty($divisiIds)) {
            $query->whereHas('user.divisis', function ($q) use ($divisiIds) {
                $q->whereIn('divisions.id', $divisiIds);
            });
        }

        if (! empty($regionIds)) {
            $query->whereHas('user.regions', function ($q) use ($regionIds) {
                $q->whereIn('regions.id', $regionIds);
            });
        }

        if (! empty($clusterIds)) {
            $query->whereHas('user.clusters', function ($q) use ($clusterIds) {
                $q->whereIn('clusters.id', $clusterIds);
            });
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisits::route('/'),
            'create' => CreateVisit::route('/create'),
            'view' => ViewVisit::route('/{record}'),
            'edit' => EditVisit::route('/{record}/edit'),
        ];
    }
}
