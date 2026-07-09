<?php

namespace App\Filament\Resources\Clusters;

use App\Filament\Resources\Clusters\Pages\ManageClusters;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Support\FilamentOrganizationalScope;
use App\Support\FilamentTableEagerLoad;
use App\Support\OrganizationalDeleteGuard;
use App\Support\OrganizationalFormFields;
use App\Support\OrganizationalHierarchyOptions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClusterResource extends Resource
{
    protected static ?string $model = Cluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-marker-pin-02';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        $canCreateBadanUsaha = auth()->user()?->can('create', \App\Models\BadanUsaha::class) ?? false;
        $canCreateDivision = auth()->user()?->can('create', \App\Models\Division::class) ?? false;
        $canCreateRegion = auth()->user()?->can('create', \App\Models\Region::class) ?? false;

        return $schema
            ->columns(1)
            ->components([
                Select::make('badanusaha_id')
                    ->label('Badan Usaha')
                    ->searchable()
                    ->required()
                    ->live()
                    ->placeholder('Pilih badan usaha')
                    ->createOptionForm(
                        $canCreateBadanUsaha
                        ? [
                            OrganizationalFormFields::code(BadanUsaha::class),
                            OrganizationalFormFields::name(BadanUsaha::class),
                        ]
                        : null
                    )
                    ->createOptionUsing(function (array $data): int {
                        return BadanUsaha::create([
                            'code' => $data['code'],
                            'name' => $data['name'],
                        ])->id;
                    })
                    ->options(fn (): array => OrganizationalHierarchyOptions::badanUsaha())
                    ->getSearchResultsUsing(fn (string $search): array => OrganizationalHierarchyOptions::searchBadanUsaha($search))
                    ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::badanUsahaLabel($value))
                    ->helperText(
                        $canCreateBadanUsaha
                        ? 'Pilih Badan Usaha atau tambah baru.'
                        : 'Pilih Badan Usaha.'
                    )
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('divisi_id', null);
                        $set('region_id', null);
                    }),

                Select::make('divisi_id')
                    ->label('Divisi')
                    ->searchable()
                    ->required()
                    ->live()
                    ->placeholder('Pilih divisi')
                    ->createOptionForm(
                        $canCreateDivision
                        ? [
                            OrganizationalFormFields::code(Division::class, 'badanusaha_id', 'badanusaha_id'),
                            OrganizationalFormFields::name(Division::class, 'badanusaha_id', 'badanusaha_id'),
                        ]
                        : null
                    )
                    ->createOptionUsing(function (array $data, callable $get) {
                        $badanusahaId = $get('badanusaha_id');
                        if (! $badanusahaId) {
                            throw new \Exception('Pilih Badan Usaha terlebih dahulu.');
                        }

                        if (! OrganizationalFormFields::ensureUnique(Division::class, 'code', $data['code'], 'badanusaha_id', $badanusahaId)) {
                            throw new \Exception('Kode divisi sudah digunakan pada Badan Usaha yang sama.');
                        }

                        if (! OrganizationalFormFields::ensureUnique(Division::class, 'name', $data['name'], 'badanusaha_id', $badanusahaId)) {
                            throw new \Exception('Nama divisi sudah digunakan pada Badan Usaha yang sama.');
                        }

                        $division = \App\Models\Division::create([
                            'code' => $data['code'],
                            'name' => $data['name'],
                            'badanusaha_id' => $badanusahaId,
                        ]);

                        return $division->id;
                    })
                    ->helperText(
                        $canCreateDivision
                        ? 'Divisi akan muncul setelah Badan Usaha dipilih. Atau tambah baru jika belum ada.'
                        : 'Divisi akan muncul setelah Badan Usaha dipilih.'
                    )
                    ->options(fn (callable $get): array => OrganizationalHierarchyOptions::division($get('badanusaha_id')))
                    ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchDivision($search, $get('badanusaha_id')))
                    ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value))
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('region_id', null);
                    }),
                Select::make('region_id')
                    ->label('Region')
                    ->searchable()
                    ->required()
                    ->live()
                    ->placeholder('Pilih region')
                    ->createOptionForm(
                        $canCreateRegion
                        ? [
                            OrganizationalFormFields::code(Region::class, 'divisi_id', 'divisi_id'),
                            OrganizationalFormFields::name(Region::class, 'divisi_id', 'divisi_id'),
                        ]
                        : null
                    )
                    ->createOptionUsing(function (array $data, callable $get) {
                        $divisiId = $get('divisi_id');
                        if (! $divisiId) {
                            throw new \Exception('Pilih Divisi terlebih dahulu.');
                        }

                        $division = Division::query()
                            ->select('id', 'badanusaha_id')
                            ->find($divisiId);

                        if (! $division) {
                            throw new \Exception('Divisi tidak ditemukan.');
                        }

                        if (! OrganizationalFormFields::ensureUnique(Region::class, 'code', $data['code'], 'divisi_id', $division->id)) {
                            throw new \Exception('Kode region sudah digunakan pada Divisi yang sama.');
                        }

                        if (! OrganizationalFormFields::ensureUnique(Region::class, 'name', $data['name'], 'divisi_id', $division->id)) {
                            throw new \Exception('Nama region sudah digunakan pada Divisi yang sama.');
                        }

                        $region = \App\Models\Region::create([
                            'code' => $data['code'],
                            'name' => $data['name'],
                            'divisi_id' => $division->id,
                            'badanusaha_id' => $division->badanusaha_id,
                        ]);

                        return $region->id;
                    })
                    ->helperText(
                        $canCreateRegion
                        ? 'Region akan muncul setelah Divisi dipilih. Atau tambah baru jika belum ada.'
                        : 'Region akan muncul setelah Divisi dipilih.'
                    )
                    ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('divisi_id')))
                    ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('divisi_id')))
                    ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::regionLabel($value)),
                OrganizationalFormFields::code(Cluster::class, 'region_id', 'region_id'),
                OrganizationalFormFields::name(Cluster::class, 'region_id', 'region_id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('badanusaha.name')
                    ->label('Badan Usaha')
                    ->color('primary')
                    ->searchable(),
                BadgeColumn::make('divisi.name')
                    ->label('Divisi')
                    ->color('success')
                    ->searchable(),
                BadgeColumn::make('region.name')
                    ->label('Region')
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('outlets_count')
                    ->label('Outlets')
                    ->counts('outlets')
                    ->badge()
                    ->color('info'),
                TextColumn::make('created_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code', 'asc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->groups([
                Group::make('region.name')
                    ->label('Region')
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('badanusaha')
                    ->relationship('badanUsaha', 'name', fn (Builder $query) => $query->active())
                    ->searchable()
                    ->label('Badan Usaha'),
                SelectFilter::make('divisi')
                    ->relationship('divisi', 'name', fn (Builder $query) => $query->active())
                    ->searchable()
                    ->label('Divisi'),
                SelectFilter::make('region')
                    ->relationship('region', 'name', fn (Builder $query) => $query->active())
                    ->searchable()
                    ->label('Region'),
                Filter::make('has_outlets')
                    ->label('Has Outlets')
                    ->query(fn (Builder $query) => $query->has('outlets')),
                Filter::make('empty')
                    ->label('Empty (No Outlets)')
                    ->query(fn (Builder $query) => $query->doesntHave('outlets')),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Cluster')
                    ->modalDescription('Penghapusan struktur organisasi dapat berdampak pada data relasi. Pastikan tidak ada data terkait sebelum melanjutkan.')
                    ->action(fn (Cluster $record) => OrganizationalDeleteGuard::deleteRecord($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny')
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => OrganizationalDeleteGuard::deleteRecords($records)),
                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny')
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => OrganizationalDeleteGuard::forceDeleteRecords($records)),
                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where(function (Builder $query) use ($user): void {
                FilamentOrganizationalScope::applyDirectColumns($query, $user, 'clusters', [
                    'badanusaha' => 'badanusaha_id',
                    'divisi' => 'divisi_id',
                    'region' => 'region_id',
                    'cluster' => 'id',
                ]);
            })
            ->withCount('outlets')
            ->with(FilamentTableEagerLoad::hierarchy('badanusaha', 'divisi', 'region'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClusters::route('/'),
        ];
    }
}
