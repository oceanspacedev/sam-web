<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Outlet;
use App\Support\OrganizationalHierarchyOptions;
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
                    ->label('Limit')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Filter::make('region')
                    ->schema([
                        Select::make('businessEntity')
                            ->label('Badan Usaha')
                            ->reactive()
                            ->searchable()
                            ->preload()
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
                    ->hidden(fn () => ! Gate::any(['RestoreAny:Outlet', 'ForceDeleteAny:Outlet'], Outlet::class)),

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
                                $record->refresh();
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
                                ]);
                                $record->refresh();
                                $record->recordChangeArchive(
                                    \App\Models\OutletChangeArchive::ACTION_RESET_DATA,
                                    auth()->user(),
                                    $beforeArchive,
                                    $record->changeArchiveSnapshot()
                                );
                            }
                        })
                        ->authorize(fn () => Gate::allows('Reset:Outlet')),
                ]),
            ]);
    }
}
