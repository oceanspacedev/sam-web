<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NooResource\Pages;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Noo;
use App\Models\Outlet;
use App\Models\Region;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class NooResource extends Resource
{
    protected static ?string $model = Noo::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Data Outlet
                Forms\Components\Section::make('Data Outlet')
                    ->schema([
                        Forms\Components\TextInput::make('kode_outlet')
                            ->required()
                            ->regex('/^[\S]+$/', 'Kode outlet tidak boleh mengandung spasi')
                            ->helperText('Kode outlet tidak boleh mengandung spasi')
                            ->rule(function (callable $get) {
                                return function ($attribute, $value, $fail) use ($get) {
                                    $divisiId = $get('divisi_id'); // Retrieve badanusaha_id using $get
                                    $outletId = $get('id'); // Ambil id outlet untuk proses edit (pastikan field ini tersedia)
                                    // Cek apakah kode_outlet sudah digunakan di divisi yang sama, kecuali oleh outlet ini sendiri
                                    $exists = \DB::table('outlets')
                                        ->where('kode_outlet', $value)
                                        ->where('divisi_id', $divisiId)
                                        ->where('id', '!=', $outletId) // Abaikan data ini sendiri jika dalam mode edit
                                        ->where('deleted_at', null)
                                        ->exists();
                                    if ($exists) {
                                        $fail(__('Kode Outlet sudah digunakan untuk divisi ini.'));
                                    }
                                };
                            })
                            ->maxLength(255)
                            ->label('Kode Outlet')
                            ->placeholder('Masukkan kode outlet'),
                        Forms\Components\TextInput::make('nama_outlet')
                            ->required()
                            ->maxLength(255)
                            ->reactive()
                            ->label('Nama Outlet'),
                        Forms\Components\TextInput::make('distric')
                            ->required()
                            ->maxLength(255)
                            ->label('Distrik'),
                        Forms\Components\Textarea::make('alamat_outlet')
                            ->required()
                            ->columnSpanFull()
                            ->label('Alamat Outlet'),
                        Forms\Components\TextInput::make('nama_pemilik_outlet')
                            ->required()
                            ->maxLength(255)
                            ->label('Nama Pemilik Outlet'),
                        Forms\Components\TextInput::make('nomer_tlp_outlet')
                            ->required()
                            ->maxLength(255)
                            ->label('Nomor Telepon Outlet'),
                        Forms\Components\TextInput::make('nomer_wakil_outlet')
                            ->maxLength(255)
                            ->label('Nomor Wakil Outlet'),
                        Forms\Components\TextInput::make('ktp_outlet')
                            ->required()
                            ->maxLength(255)
                            ->label('KTP Pemilik Outlet'),

                    ])
                    ->columns(2), // Menggunakan dua kolom untuk tampilan lebih kompak

                Forms\Components\Section::make('Badan Usaha & Divisi')
                    ->schema([
                        Forms\Components\Select::make('badanusaha_id')
                            ->label('Badan Usaha')
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->placeholder('Pilih badan usaha')
                            ->options(function (callable $get) {
                                $user = auth()->user();
                                $role = $user->role;

                                if ($role->filter_type === 'badanusaha') {
                                    return \App\Models\BadanUsaha::whereIn('id', $role->filter_data ?? [])
                                        ->pluck('name', 'id');
                                } elseif ($role->filter_type === 'all') {
                                    return \App\Models\BadanUsaha::pluck('name', 'id');
                                }

                                return \App\Models\BadanUsaha::where('id', $user->badanusaha_id)
                                    ->pluck('name', 'id');
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('divisi_id', null);
                                $set('region_id', null);
                                $set('cluster_id', null);
                                $set('cluster_id2', null);
                            }),

                        Forms\Components\Select::make('divisi_id')
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
                                    ->pluck('name', 'id');
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('region_id', null);
                                $set('cluster_id', null);
                                $set('cluster_id2', null);
                            }),

                        Forms\Components\Select::make('region_id')
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
                                    ->pluck('name', 'id');
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('cluster_id', null);
                                $set('cluster_id2', null);
                            }),

                        Forms\Components\Select::make('cluster_id')
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
                                    ->pluck('name', 'id');
                            }),
                    ])
                    ->columns(2), // Menyusun dropdown dalam dua kolom

                // Foto dan Video
                Forms\Components\Section::make('Dokumentasi')
                    ->schema([
                        Forms\Components\FileUpload::make('poto_shop_sign')
                            ->required()
                            ->image()
                            ->disk('public')
                            ->resize(30)
                            ->label('Foto Tanda Toko')
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                return 'noo-'.$outletName.'-fotoshopsign-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                            }),
                        Forms\Components\FileUpload::make('poto_depan')
                            ->required()
                            ->image()
                            ->disk('public')
                            ->resize(30)
                            ->label('Foto Depan')
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                return 'noo-'.$outletName.'-fotodepan-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                            }),
                        Forms\Components\FileUpload::make('poto_kiri')
                            ->required()
                            ->image()
                            ->disk('public')
                            ->resize(30)
                            ->label('Foto Kiri')
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                return 'noo-'.$outletName.'-fotokiri-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                            }),
                        Forms\Components\FileUpload::make('poto_kanan')
                            ->required()
                            ->image()
                            ->disk('public')
                            ->resize(30)
                            ->label('Foto Kanan')
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                return 'noo-'.$outletName.'-fotokanan-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                            }),
                        Forms\Components\FileUpload::make('poto_ktp')
                            ->required()
                            ->image()
                            ->disk('public')
                            ->resize(30)
                            ->label('Foto KTP Pemilik')
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                return 'noo-'.$outletName.'-fotoktp-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                            }),
                        Forms\Components\FileUpload::make('video')
                            ->required()
                            ->disk('public')
                            ->label('Video Toko')
                            ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                                $outletName = strtolower(str_replace(' ', '_', $get('nama_outlet')));

                                return 'noo-'.$outletName.'-video-'.Carbon::now()->format('dmYHis').'.'.$file->getClientOriginalExtension();
                            }),
                    ])
                    ->columns(2),

                // Produk dan Merk
                Forms\Components\Section::make('Promotor dan Frontliner')
                    ->schema([
                        Forms\Components\TextInput::make('oppo')
                            ->required()
                            ->numeric()
                            ->label('Oppo'),
                        Forms\Components\TextInput::make('vivo')
                            ->required()
                            ->numeric()
                            ->label('Vivo'),
                        Forms\Components\TextInput::make('realme')
                            ->required()
                            ->numeric()
                            ->label('Realme'),
                        Forms\Components\TextInput::make('samsung')
                            ->required()
                            ->numeric()
                            ->label('Samsung'),
                        Forms\Components\TextInput::make('xiaomi')
                            ->required()
                            ->numeric()
                            ->label('Xiaomi'),
                        Forms\Components\TextInput::make('fl')
                            ->required()
                            ->numeric()
                            ->label('FL'),
                    ])
                    ->columns(2),

                // Informasi Tambahan
                Forms\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Forms\Components\TextInput::make('latlong')
                            ->required()
                            ->maxLength(255)
                            ->label('Koordinat Lat/Long'),
                        Forms\Components\TextInput::make('limit')
                            ->numeric()
                            ->label('Limit'),
                        Forms\Components\Select::make('status')
                            ->required()
                            ->label('Status')
                            ->options([
                                'PENDING' => 'PENDING',
                                'CONFIRMED' => 'CONFIRMED',
                                'APPROVED' => 'APPROVED',
                                'REJECTED' => 'REJECTED',
                            ])
                            ->placeholder('Pilih Status'),
                        Forms\Components\TextInput::make('created_by')
                            ->required()
                            ->maxLength(255)
                            ->label('Dibuat Oleh'),
                        Forms\Components\TextInput::make('keterangan')
                            ->maxLength(255)
                            ->label('Keterangan'),
                    ])
                    ->columns(2),

                // Tanggal dan Persetujuan
                Forms\Components\Section::make('Tanggal dan Persetujuan')
                    ->schema([
                        Forms\Components\DateTimePicker::make('rejected_at')
                            ->label('Tanggal Ditolak'),
                        Forms\Components\TextInput::make('rejected_by')
                            ->maxLength(255)
                            ->label('Ditolak Oleh'),
                        Forms\Components\DateTimePicker::make('confirmed_at')
                            ->label('Tanggal Dikonfirmasi'),
                        Forms\Components\TextInput::make('confirmed_by')
                            ->maxLength(255)
                            ->label('Dikonfirmasi Oleh'),
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->label('Tanggal Disetujui'),
                        Forms\Components\TextInput::make('approved_by')
                            ->maxLength(255)
                            ->label('Disetujui Oleh'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('TM')
                    ->schema([
                        Forms\Components\Select::make('tm_id')
                            ->relationship('tm', 'nama_lengkap')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->label('Nama TM'),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Dibuat') // Capitalized the label for consistency
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('created_by')
                    ->label('Dibuat Oleh')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kode_outlet')
                    ->label('Kode Outlet'),
                Tables\Columns\TextColumn::make('divisi.name')
                    ->label('Divisi'),
                Tables\Columns\TextColumn::make('badanusaha.name')
                    ->label('Badan Usaha'),
                Tables\Columns\TextColumn::make('nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                Tables\Columns\TextColumn::make('alamat_outlet')
                    ->label('Alamat Outlet'),
                Tables\Columns\TextColumn::make('nama_pemilik_outlet')
                    ->label('Nama Pemilik Outlet'),
                Tables\Columns\TextColumn::make('ktp_outlet')
                    ->label('Nomor KTP Outlet'),
                Tables\Columns\TextColumn::make('nomer_tlp_outlet')
                    ->label('Nomor Telepon Outlet'),
                Tables\Columns\TextColumn::make('nomer_wakil_outlet')
                    ->label('Nomor Wakil Outlet'),
                Tables\Columns\TextColumn::make('distric')
                    ->label('Distrik'),
                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region'),
                Tables\Columns\TextColumn::make('cluster.name')
                    ->label('Cluster'),
                Tables\Columns\TextColumn::make('poto_ktp')
                    ->label('Foto KTP')
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('KTP'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('poto_shop_sign')
                    ->label('Foto Tanda Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('poto_depan')
                    ->label('Foto Depan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('poto_kanan')
                    ->label('Foto Kanan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('poto_kiri')
                    ->label('Foto Kiri')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->color('primary')
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('video')
                    ->label('Video Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('VIDEO'))
                    ->color('primary')
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('oppo')
                    ->label('Oppo'),
                Tables\Columns\TextColumn::make('vivo')
                    ->label('Vivo'),
                Tables\Columns\TextColumn::make('realme')
                    ->label('Realme'),
                Tables\Columns\TextColumn::make('samsung')
                    ->label('Samsung'),
                Tables\Columns\TextColumn::make('xiaomi')
                    ->label('Xiaomi'),
                Tables\Columns\TextColumn::make('fl')
                    ->label('Frontliner'),
                Tables\Columns\TextColumn::make('latlong')
                    ->label('Lokasi (LatLong)')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->color('primary')
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('limit')
                    ->label('Limit'),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
                Filter::make('region')
                    ->form([
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
                Tables\Filters\TrashedFilter::make()
                    ->hidden(fn () => ! Gate::any(['restore_any_visit', 'force_delete_any_visit'], Noo::class)),
            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth(MaxWidth::Large)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'PENDING' && Gate::allows('confirm', $record))
                    ->form([
                        TextInput::make('kode_outlet')
                            ->regex('/^[\S]+$/', 'Kode outlet tidak boleh mengandung spasi')
                            ->helperText('Kode outlet tidak boleh mengandung spasi')
                            ->required(),
                        TextInput::make('limit')
                            ->numeric()
                            ->required(),
                    ])
                    ->action(function ($record, $data) {
                        $record->update([
                            'kode_outlet' => $data['kode_outlet'],
                            'limit' => $data['limit'],
                            'confirmed_at' => Carbon::now(),
                            'confirmed_by' => auth()->user()->nama_lengkap,
                            'status' => 'CONFIRMED',
                            Notification::make()
                                ->title($record->nama_outlet.' Confirm')
                                ->success()
                                ->send(),
                        ]);
                    }),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'CONFIRMED' && Gate::allows('approve', $record))
                    ->action(function ($record, $data) {
                        $record->update([
                            'approved_at' => Carbon::now(),
                            'approved_by' => auth()->user()->nama_lengkap,
                            'status' => 'APPROVED',
                            Notification::make()
                                ->title($record->nama_outlet.' Approved')
                                ->success()
                                ->send(),
                        ]);
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== 'REJECTED' && $record->status !== 'APPROVED' && Gate::allows('reject', $record))
                    ->form([
                        Textarea::make('alasan')
                            ->required(),
                    ])
                    ->action(function ($record, $data) {
                        $record->update([
                            'confirmed_at' => Carbon::now(),
                            'confirmed_by' => auth()->user()->name,
                            'status' => 'REJECTED',
                            'keterangan' => $data['alasan'],
                        ]);
                        Notification::make()
                            ->title($record->nama_outlet.' Rejected')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('createOutlets')
                        ->label('Create Outlets')
                        ->icon('heroicon-o-plus-circle')
                        ->visible(fn () => Auth::user()->role->name === 'SUPER ADMIN')
                        ->action(function ($records) { // Removed $request here
                            foreach ($records as $record) {
                                $data = [
                                    'kode_outlet' => 'LEAD'.$record->id,
                                    'nama_outlet' => $record->nama_outlet,
                                    'alamat_outlet' => $record->alamat_outlet,
                                    'nama_pemilik_outlet' => $record->nama_pemilik_outlet,
                                    'nomer_tlp_outlet' => $record->nomer_tlp_outlet,
                                    'badanusaha_id' => $record->badanusaha_id,
                                    'divisi_id' => $record->divisi_id,
                                    'region_id' => $record->region_id,
                                    'cluster_id' => $record->cluster_id,
                                    'distric' => $record->distric,
                                    'poto_shop_sign' => $record->poto_shop_sign,
                                    'poto_depan' => $record->poto_depan,
                                    'poto_kanan' => $record->poto_kanan,
                                    'poto_kiri' => $record->poto_kiri,
                                    'poto_ktp' => $record->poto_ktp,
                                    'video' => $record->video,
                                    'limit' => $record->limit ?? 0,
                                    'radius' => 100,
                                    'latlong' => $record->latlong,
                                    'status_outlet' => 'MAINTAIN',
                                    'is_member' => '0',
                                ];
                                Outlet::create($data);
                            }
                            Notification::make()
                                ->title('Outlets created successfully!')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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
                $user = auth()->user();
                $role = $user->role;
                switch ($role->filter_type) {
                    case 'badanusaha':
                        $query->whereIn('noos.badanusaha_id', $role->filter_data ?? []);
                        break;
                    case 'divisi':
                        $query->whereIn('noos.divisi_id', $role->filter_data ?? []);
                        break;
                    case 'region':
                        $query->whereIn('noos.region_id', $role->filter_data ?? []);
                        break;
                    case 'cluster':
                        $query->whereIn('noos.cluster_id', $role->filter_data ?? []);
                        break;
                    case 'all':
                    default:
                        return;
                }
            })
            ->where(function ($query) {
                $query->whereNull('keterangan')
                    ->orWhere('keterangan', '!=', 'LEAD');
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNoos::route('/'),
            'create' => Pages\CreateNoo::route('/create'),
            'edit' => Pages\EditNoo::route('/{record}/edit'),
        ];
    }
}
