<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\BadanUsaha;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
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
                Tables\Columns\TextColumn::make('kode_outlet')
                    ->label('Kode Outlet')
                    ->searchable(),
                Tables\Columns\TextColumn::make('badanusaha.name')
                    ->label('Badan Usaha'),
                Tables\Columns\TextColumn::make('divisi.name')
                    ->label('Divisi'),
                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region'),
                Tables\Columns\TextColumn::make('cluster.name')
                    ->label('Cluster'),
                Tables\Columns\TextColumn::make('nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                Tables\Columns\TextColumn::make('nama_pemilik_outlet')
                    ->label('Nama Pemilik Outlet'),
                Tables\Columns\TextColumn::make('nomer_tlp_outlet')
                    ->label('Nomor Telepon Outlet'),
                Tables\Columns\TextColumn::make('distric')
                    ->label('Distrik'),
                Tables\Columns\TextColumn::make('poto_shop_sign')
                    ->label('Foto Tanda Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('poto_depan')
                    ->label('Foto Depan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('poto_kiri')
                    ->label('Foto Kiri')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('poto_kanan')
                    ->label('Foto Kanan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('poto_ktp')
                    ->label('Foto KTP')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO KTP'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('video')
                    ->label('Video Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('VIDEO'))
                    ->url(fn ($state): string => asset('storage/'.$state), shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('limit')
                    ->label('Limit'),
                Tables\Columns\TextColumn::make('radius')
                    ->label('Radius'),
                Tables\Columns\TextColumn::make('latlong')
                    ->label('Lokasi (LatLong)')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status_outlet')
                    ->label('Status Outlet'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
                    ->hidden(fn () => ! Gate::any(['restore_any_visit', 'force_delete_any_visit'], Outlet::class)),

            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth(MaxWidth::Large)
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    BulkAction::make('reset')
                        ->label('Reset Data Outlet')
                        ->icon('heroicon-o-building-storefront')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                if ($record->poto_shop_sign) {
                                    Storage::disk('public')->delete($record->poto_shop_sign);
                                }
                                if ($record->poto_depan) {
                                    Storage::disk('public')->delete($record->poto_depan);
                                }
                                if ($record->poto_kiri) {
                                    Storage::disk('public')->delete($record->poto_kiri);
                                }
                                if ($record->poto_kanan) {
                                    Storage::disk('public')->delete($record->poto_kanan);
                                }
                                if ($record->poto_ktp) {
                                    Storage::disk('public')->delete($record->poto_ktp);
                                }
                                if ($record->video) {
                                    Storage::disk('public')->delete($record->video);
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
