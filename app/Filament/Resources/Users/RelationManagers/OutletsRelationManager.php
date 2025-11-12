<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\BadanUsaha;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Support\StorageDisk;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OutletsRelationManager extends RelationManager
{
    protected static string $relationship = 'outlet';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_outlet')
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
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('poto_depan')
                    ->label('Foto Depan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('poto_kiri')
                    ->label('Foto Kiri')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('poto_kanan')
                    ->label('Foto Kanan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('poto_ktp')
                    ->label('Foto KTP')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO KTP'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary'),
                TextColumn::make('video')
                    ->label('Video Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('VIDEO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
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
            ->headerActions([])
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
}
