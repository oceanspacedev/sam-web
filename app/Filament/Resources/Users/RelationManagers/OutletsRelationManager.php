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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OutletsRelationManager extends RelationManager
{
    protected static ?string $title = 'Outlet';

    protected static string $relationship = 'outlets';

    protected function getTableQuery(): Builder
    {
        $owner = $this->getOwnerRecord();

        return Outlet::visibleTo($owner)->active();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_outlet')
            ->columns([
                TextColumn::make('kode_outlet')
                    ->label('Kode Outlet')
                    ->searchable(),
                TextColumn::make('nama_outlet')
                    ->label('Nama Outlet')
                    ->searchable(),
                TextColumn::make('status_outlet')
                    ->label('Status Outlet'),
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
                TextColumn::make('nama_pemilik_outlet')
                    ->label('Nama Pemilik Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nomer_tlp_outlet')
                    ->label('Nomor Telepon Outlet')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distric')
                    ->label('Distrik')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_shop_sign')
                    ->label('Foto Tanda Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_depan')
                    ->label('Foto Depan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_kiri')
                    ->label('Foto Kiri')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_kanan')
                    ->label('Foto Kanan')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('poto_ktp')
                    ->label('Foto KTP')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('FOTO KTP'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('video')
                    ->label('Video Outlet')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('VIDEO'))
                    ->url(fn ($state): string => StorageDisk::url($state), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('limit')
                    ->label('Limit')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('radius')
                    ->label('Radius')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latlong')
                    ->label('Lokasi (LatLong)')
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('LOKASI'))
                    ->url(fn ($state): string => 'https://www.google.com/maps/place/'.$state, shouldOpenInNewTab: true)
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
                Filter::make('region')
                    ->schema([
                        Select::make('businessEntity')
                            ->label('Badan Usaha')
                            ->options(function () {
                                $user = Auth::user();

                                if ($user && $user->role->organizational_scope_level === 'all') {
                                    return BadanUsaha::orderBy('name', 'asc')->pluck('name', 'id')->toArray();
                                }

                                return $user->badanUsahas()->orderBy('name', 'asc')->pluck('name', 'badan_usahas.id')->toArray();
                            })
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
                                if (! $businessEntityId) {
                                    return [];
                                }

                                $user = Auth::user();
                                $query = Division::where('badanusaha_id', $businessEntityId);

                                if ($user && $user->role->organizational_scope_level !== 'all') {
                                    $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                                    if (! empty($divisiIds)) {
                                        $query->whereIn('divisions.id', $divisiIds);
                                    }
                                }

                                return $query->orderBy('name', 'asc')->pluck('name', 'id');
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
                                if (! $divisionId) {
                                    return [];
                                }

                                $user = Auth::user();
                                $query = Region::where('divisi_id', $divisionId);

                                if ($user && in_array($user->role->organizational_scope_level, ['region', 'cluster'], true)) {
                                    $regionIds = $user->regions()->pluck('regions.id')->toArray();
                                    if (! empty($regionIds)) {
                                        $query->whereIn('regions.id', $regionIds);
                                    }
                                }

                                return $query->orderBy('name', 'asc')->pluck('name', 'id');
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
                    ->hidden(fn () => ! Gate::any(['RestoreAny:Visit', 'ForceDeleteAny:Visit'], Outlet::class)),

            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth(Width::Large)
            ->headerActions([])
            ->recordActions([
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
                    BulkAction::make('reset')
                        ->label('Reset Data Outlet')
                        ->icon('heroicon-o-building-storefront')
                        ->action(function (Collection $records) {
                            /** @var Outlet $record */
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
                                if ($record->video) {
                                    Storage::disk(StorageDisk::default())->delete($record->video);
                                }

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
                                ]);
                            }
                        })
                        ->authorize(fn () => Gate::allows('Reset:Outlet')),
                ]),
            ]);
    }
}
