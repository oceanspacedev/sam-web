<?php

namespace App\Filament\Resources\Regions;

use App\Filament\Resources\Regions\Pages\ManageRegions;
use App\Models\BadanUsaha;
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

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-flag-06';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $canCreateBadanUsaha = auth()->user()?->can('create', \App\Models\BadanUsaha::class) ?? false;
        $canCreateDivision = auth()->user()?->can('create', \App\Models\Division::class) ?? false;

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
                    ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value)),
                OrganizationalFormFields::code(Region::class, 'divisi_id', 'divisi_id'),
                OrganizationalFormFields::name(Region::class, 'divisi_id', 'divisi_id'),
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
                TextColumn::make('clusters_count')
                    ->label('Clusters')
                    ->counts('clusters')
                    ->badge()
                    ->color('warning'),
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
                Group::make('divisi.name')
                    ->label('Divisi')
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('badanusaha')
                    ->relationship('badanusaha', 'name', fn (Builder $query) => $query->active())
                    ->searchable()
                    ->label('Badan Usaha'),
                SelectFilter::make('divisi')
                    ->relationship('divisi', 'name', fn (Builder $query) => $query->active())
                    ->searchable()
                    ->label('Divisi'),
                Filter::make('has_clusters')
                    ->label('Has Clusters')
                    ->query(fn (Builder $query) => $query->has('clusters')),
                Filter::make('empty')
                    ->label('Empty (No Clusters)')
                    ->query(fn (Builder $query) => $query->doesntHave('clusters')),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Region')
                    ->modalDescription('Penghapusan struktur organisasi dapat berdampak pada data relasi. Pastikan tidak ada data terkait sebelum melanjutkan.')
                    ->action(fn (Region $record) => OrganizationalDeleteGuard::deleteRecord($record)),
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
                FilamentOrganizationalScope::applyRegionScope($query, $user);
            })
            ->withCount('clusters')
            ->with(FilamentTableEagerLoad::hierarchy('badanusaha', 'divisi'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegions::route('/'),
        ];
    }
}
