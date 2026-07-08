<?php

namespace App\Filament\Resources\Visits;

use App\Filament\Resources\Visits\Pages\CreateVisit;
use App\Filament\Resources\Visits\Pages\EditVisit;
use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Filament\Resources\Visits\Pages\ViewVisit;
use App\Models\Outlet;
use App\Models\Register;
use App\Models\User;
use App\Models\Visit;
use App\Services\FilenameGeneratorService;
use App\Services\SystemSettingResolver;
use App\Support\FilamentOrganizationalScope;
use App\Support\FilamentTableEagerLoad;
use App\Support\ScopedUserSelectOptions;
use App\Support\StorageDisk;
use App\Support\VisitTargetSelectOptions;
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

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-camera-02';

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
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (callable $set) => $set('outlet_id', null))
                                    ->label('Pilih User')
                                    ->placeholder('Cari User berdasarkan nama lengkap')
                                    ->options(fn (): array => ScopedUserSelectOptions::search(Auth::user(), ''))
                                    ->getSearchResultsUsing(fn (string $search): array => ScopedUserSelectOptions::search(Auth::user(), $search))
                                    ->getOptionLabelUsing(fn ($value): ?string => ScopedUserSelectOptions::label($value)),
                                // Polymorphic visit target selector
                                Select::make('visitable_type')
                                    ->label('Tipe Target')
                                    ->options(function () {
                                        $options = [
                                            Outlet::class => 'Outlet',
                                        ];

                                        // Show register target only when system setting enables register visit.
                                        $allowsRegisterVisit = app(SystemSettingResolver::class)->hasAnyAllowRegisterVisit();

                                        if ($allowsRegisterVisit) {
                                            $options[Register::class] = 'Register (LEAD/NOO)';
                                        }

                                        return $options;
                                    })
                                    ->required()
                                    ->live()
                                    ->default(Outlet::class)
                                    ->afterStateUpdated(fn (callable $set) => $set('visitable_id', null)),
                                Select::make('visitable_id')
                                    ->label('Target')
                                    ->required()
                                    ->searchable()
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
                                        $type = (string) $get('visitable_type');
                                        $tipeVisit = (string) $get('tipe_visit');
                                        $selectedUserId = $get('user_id');
                                        $selectedUser = $selectedUserId
                                            ? User::query()->select(['id', 'role_id'])->with('role')->find($selectedUserId)
                                            : null;
                                        if (! $selectedUser) {
                                            return [];
                                        }

                                        $resolver = app(SystemSettingResolver::class);
                                        $plannedIds = null;

                                        if ($tipeVisit === 'PLANNED') {
                                            $plannedIds = VisitTargetSelectOptions::plannedVisitableIdsForDate(
                                                $selectedUser->id,
                                                $type,
                                                $get('tanggal_visit')
                                            );

                                            if ($plannedIds === []) {
                                                return [];
                                            }
                                        }

                                        if ($type === Outlet::class) {
                                            return VisitTargetSelectOptions::searchOutlets(
                                                $search,
                                                $selectedUser,
                                                50,
                                                $plannedIds
                                            );
                                        }

                                        if ($type !== Register::class) {
                                            return [];
                                        }

                                        return VisitTargetSelectOptions::searchRegisters(
                                            $search,
                                            $selectedUser,
                                            $resolver,
                                            50,
                                            $plannedIds
                                        );
                                    })
                                    ->options(function (callable $get) {
                                        $type = (string) $get('visitable_type');
                                        $id = $get('visitable_id');
                                        $tipeVisit = (string) $get('tipe_visit');
                                        $selectedUserId = $get('user_id');
                                        $selectedUser = $selectedUserId
                                            ? User::query()->select(['id', 'role_id'])->with('role')->find($selectedUserId)
                                            : null;

                                        $options = [];

                                        if ($selectedUser) {
                                            $plannedIds = null;

                                            if ($tipeVisit === 'PLANNED') {
                                                $plannedIds = VisitTargetSelectOptions::plannedVisitableIdsForDate(
                                                    $selectedUser->id,
                                                    $type,
                                                    $get('tanggal_visit')
                                                );
                                            }

                                            if ($plannedIds !== []) {
                                                if ($type === Outlet::class) {
                                                    $options = VisitTargetSelectOptions::searchOutlets('', $selectedUser, 50, $plannedIds);
                                                } elseif ($type === Register::class) {
                                                    $options = VisitTargetSelectOptions::searchRegisters('', $selectedUser, app(SystemSettingResolver::class), 50, $plannedIds);
                                                }
                                            }
                                        }

                                        if ($id && $type !== '' && ! array_key_exists($id, $options)) {
                                            $label = VisitTargetSelectOptions::label($type, $id);

                                            if ($label) {
                                                $options[$id] = $label;
                                            }
                                        }

                                        return $options;
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $type = (string) $get('visitable_type');
                                        if ($state && $type === Outlet::class) {
                                            $outlet = Outlet::find($state);
                                            $set('latlong_in', $outlet?->latlong ?? null);
                                        } elseif ($state && $type === Register::class) {
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
                                    ->fetchFileInformation(false)
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
                                    ->fetchFileInformation(false)
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
                                        ->checkFileExistence(false)
                                        ->columnSpanFull(),
                                    ImageEntry::make('picture_visit_out')
                                        ->label('Picture at End of Visit')
                                        ->disk(StorageDisk::default())
                                        ->checkFileExistence(false)
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
                    ->color(fn ($record): string => $record->isRegisterVisit() ? 'warning' : 'success'),
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

        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->tap(fn (Builder $query) => FilamentOrganizationalScope::applyViaUserForeignKey($query, $user))
            ->with(FilamentTableEagerLoad::visitableTarget());
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
