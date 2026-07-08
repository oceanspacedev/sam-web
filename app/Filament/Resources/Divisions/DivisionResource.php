<?php

namespace App\Filament\Resources\Divisions;

use App\Filament\Resources\Divisions\Pages\ManageDivisions;
use App\Models\BadanUsaha;
use App\Models\Division;
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

class DivisionResource extends Resource
{
    protected static ?string $model = Division::class;

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-briefcase-02';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $canCreateBadanUsaha = auth()->user()?->can('create', \App\Models\BadanUsaha::class) ?? false;

        return $schema
            ->columns(1)
            ->components([
                Select::make('badanusaha_id')
                    ->label('Badan Usaha')
                    ->searchable()
                    ->required()
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
                        ? 'Pilih Badan Usaha atau tambah baru jika belum ada.'
                        : 'Pilih Badan Usaha.'
                    ),
                OrganizationalFormFields::code(Division::class, 'badanusaha_id', 'badanusaha_id'),
                OrganizationalFormFields::name(Division::class, 'badanusaha_id', 'badanusaha_id'),
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
                TextColumn::make('regions_count')
                    ->label('Regions')
                    ->counts('regions')
                    ->badge()
                    ->color('success'),
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
                Group::make('badanusaha.name')
                    ->label('Badan Usaha'),
            ])
            ->filters([
                SelectFilter::make('badanusaha')
                    ->relationship('badanUsaha', 'name', fn (Builder $query) => $query->active())
                    ->searchable(),
                Filter::make('has_regions')
                    ->label('Has Regions')
                    ->query(fn (Builder $query) => $query->has('regions')),
                Filter::make('empty')
                    ->label('Empty (No Regions)')
                    ->query(fn (Builder $query) => $query->doesntHave('regions')),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Division')
                    ->modalDescription('Penghapusan struktur organisasi dapat berdampak pada data relasi. Pastikan tidak ada data terkait sebelum melanjutkan.')
                    ->action(fn (Division $record) => OrganizationalDeleteGuard::deleteRecord($record)),
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
                FilamentOrganizationalScope::applyDivisionScope($query, $user);
            })
            ->withCount(['regions', 'clusters'])
            ->with(FilamentTableEagerLoad::hierarchy('badanusaha'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDivisions::route('/'),
        ];
    }
}
