<?php

namespace App\Filament\Resources\Outlets;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Support\Enums\Width;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\BulkAction;
use App\Filament\Resources\Outlets\Pages\ListOutlets;
use App\Filament\Resources\Outlets\Pages\CreateOutlet;
use App\Filament\Resources\Outlets\Pages\EditOutlet;
use App\Filament\Resources\OutletResource\Pages;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OutletResource extends Resource
{
    protected static ?string $model = Outlet::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';

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
                                        ->regex('/^[\S]+$/', 'Kode outlet tidak boleh mengandung spasi')
                                        ->helperText('Kode outlet tidak boleh mengandung spasi')
                                        ->rule(function (callable $get) {
                                            return function ($attribute, $value, $fail) use ($get) {
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
                                            ->label('Foto Tanda Toko')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                                return $outletName.'-fotoshopsign-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                                            }),
                                        FileUpload::make('poto_depan')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->label('Foto Depan')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                                return $outletName.'-fotodepan-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                                            }),
                                        FileUpload::make('poto_kiri')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->label('Foto Kiri')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                                return $outletName.'-fotokiri-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                                            }),
                                        FileUpload::make('poto_kanan')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->label('Foto Kanan')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                                return $outletName.'-fotokanan-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                                            }),
                                        FileUpload::make('poto_ktp')
                                            ->image()
                                            ->disk(StorageDisk::default())
                                            ->label('Foto KTP Pemilik')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                                return $outletName.'-fotoktp-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                                            }),
                                        FileUpload::make('video')
                                            ->disk(StorageDisk::default())
                                            ->acceptedFileTypes(['video/mp4', 'video/avi', 'video/mkv'])
                                            ->label('Video Toko')
                                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                                return $outletName.'-video-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
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
                                                }),
                                            Select::make('divisi_id')
                                                ->label('Divisi')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive()
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
                                                }),
                                            Select::make('region_id')
                                                ->label('Region')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive()
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
                                                }),
                                            Select::make('cluster_id')
                                                ->label('Cluster')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive()
                                                ->options(function (callable $get) {
                                                    $regionId = $get('region_id');

                                                    if (! $regionId) {
                                                        return [];
                                                    }

                                                    return Cluster::where('region_id', $regionId)
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id');
                                                }),
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_outlet')
                    ->label('Kode Outlet')
                    ->searchable(),
                TextColumn::make('badanusaha.name')
                    ->label('Badan Usaha'),
                TextColumn::make('divisi.name')
                    ->label('Divisi'),
                TextColumn::make('region.name')
                    ->label('Region'),
                TextColumn::make('cluster.name')
                    ->label('Cluster'),
                TextColumn::make('nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                TextColumn::make('nama_pemilik_outlet')
                    ->label('Nama Pemilik Outlet'),
                TextColumn::make('nomer_tlp_outlet')
                    ->label('Nomor Telepon Outlet'),
                TextColumn::make('distric')
                    ->label('Distrik'),
                TextColumn::make('poto_shop_sign')
                    ->label('Foto Tanda Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('poto_depan')
                    ->label('Foto Depan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('poto_kiri')
                    ->label('Foto Kiri')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('poto_kanan')
                    ->label('Foto Kanan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('poto_ktp')
                    ->label('Foto KTP')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO KTP'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('video')
                    ->label('Video Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('VIDEO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('limit')
                    ->label('Limit'),
                TextColumn::make('radius')
                    ->label('Radius'),
                TextColumn::make('latlong')
                    ->label('Lokasi (LatLong)')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('status_outlet')
                    ->label('Status Outlet'),
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
                    ->hidden(fn () => ! Gate::any(['restore_any_visit', 'force_delete_any_visit'], Outlet::class)),

            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth(Width::Large)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    BulkAction::make('reset')
                        ->label('Reset Data Outlet')
                        ->icon('heroicon-o-building-storefront')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                // Hapus file yang terkait dengan kolom gambar/video
                                if ($record->poto_shop_sign) {
                                    Storage::disk(StorageDisk::default())->delete($record->poto_shop_sign);
                                }
                                if ($record->poto_depan) {
                                    Storage::disk(StorageDisk::default())->delete($record->poto_depan);
                                }
                                if ($record->poto_kiri) {
                                    Storage::disk(StorageDisk::default())->delete($record->poto_kiri);
                                }
                                if ($record->poto_kanan) {
                                    Storage::disk(StorageDisk::default())->delete($record->poto_kanan);
                                }
                                if ($record->poto_ktp) {
                                    Storage::disk(StorageDisk::default())->delete($record->poto_ktp);
                                }
                                if ($record->video) {
                                    Storage::disk(StorageDisk::default())->delete($record->video);
                                }

                                // Reset kolom data menjadi null
                                $record->update([
                                    'nama_pemilik_outlet' => null,
                                    'nomer_tlp_outlet' => null,
                                    'latlong' => null,
                                    'poto_shop_sign' => null,
                                    'poto_depan' => null,
                                    'poto_kiri' => null,
                                    'poto_kanan' => null,
                                    'poto_ktp' => null,
                                    'video' => null,
                                ]);
                            }
                        })
                        ->authorize(fn () => Gate::allows('reset_any_outlet')),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $user = auth()->user();
                $role = $user->role;
                switch ($role->filter_type) {
                    case 'badanusaha':
                        $query->whereIn('outlets.badanusaha_id', $role->filter_data ?? []);
                        break;
                    case 'divisi':
                        $query->whereIn('outlets.divisi_id', $role->filter_data ?? []);
                        break;
                    case 'region':
                        $query->whereIn('outlets.region_id', $role->filter_data ?? []);
                        break;
                    case 'cluster':
                        $query->whereIn('outlets.cluster_id', $role->filter_data ?? []);
                        break;
                    case 'all':
                    default:
                        return;
                }
            });
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
            'index' => ListOutlets::route('/'),
            'create' => CreateOutlet::route('/create'),
            'edit' => EditOutlet::route('/{record}/edit'),
        ];
    }
}
