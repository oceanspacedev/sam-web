<?php

namespace App\Filament\Resources\Visits;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Filament\Resources\Visits\Pages\CreateVisit;
use App\Filament\Resources\Visits\Pages\EditVisit;
use App\Filament\Resources\VisitResource\Pages;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-camera';

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
                                    ->default(Carbon::parse(now())->startOfDay()) // Setel waktu ke 00:00:00
                                    ->required()
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
                                    ->default('EXTRACALL'),
                                Select::make('user_id')
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
                                Select::make('outlet_id')
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
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $outlet = Outlet::find($state);
                                            $set('latlong_in', $outlet?->latlong ?? null);
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
                                        $user = User::find($get('user_id'));
                                        $username = $user ? $user->username : 'vacant';

                                        return Carbon::now()->format('Y-m-d').'-'.$username.'-IN-'.Carbon::now()->getPreciseTimestamp(3).'.'.$file->getClientOriginalExtension();
                                    }),
                                FileUpload::make('picture_visit_out')
                                    ->image()
                                    ->columnSpanFull()
                                    // ->required()
                                    ->disk(StorageDisk::default())
                                    ->label('Picture at End of Visit')
                                    ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                        $user = User::find($get('user_id'));
                                        $username = $user ? $user->username : 'vacant';

                                        return Carbon::now()->format('Y-m-d').'-'.$username.'-OUT-'.Carbon::now()->getPreciseTimestamp(3).'.'.$file->getClientOriginalExtension();
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
                TextColumn::make('outlet.nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                TextColumn::make('tipe_visit')
                    ->label('Tipe Visit'),
                TextColumn::make('latlong_in')
                    ->label('Lokasi Check-In')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true),
                TextColumn::make('latlong_out')
                    ->label('Lokasi Check-Out')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true),
                TextColumn::make('check_in_time')
                    ->label('Jam Check-In')
                    ->time(),
                TextColumn::make('check_out_time')
                    ->label('Jam Check-Out')
                    ->time(),
                TextColumn::make('picture_visit_in')
                    ->label('Foto Check-In')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                TextColumn::make('picture_visit_out')
                    ->label('Foto Check-Out')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                TextColumn::make('transaksi')
                    ->label('Transaksi'),
                TextColumn::make('durasi_visit')
                    ->label('Durasi Visit'),
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
                TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['restore_any_visit', 'force_delete_any_visit'], Visit::class)),
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

            ])
            ->recordActions([
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

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $role = $user->role;

        if ($role->filter_type === 'all') {
            return parent::getEloquentQuery();
        }

        $query = parent::getEloquentQuery()
            ->join('users', 'visits.user_id', '=', 'users.id')
            ->select('visits.*', 'users.id as user_id')
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
            'edit' => EditVisit::route('/{record}/edit'),
        ];
    }
}
