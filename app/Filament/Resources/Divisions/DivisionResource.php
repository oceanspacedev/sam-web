<?php

namespace App\Filament\Resources\Divisions;

use App\Filament\Resources\Divisions\Pages\ManageDivisions;
use App\Models\Division;
use App\Support\OrganizationalHierarchyOptions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

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
                    ->relationship('badanUsaha', 'name', modifyQueryUsing: fn (Builder $query): Builder => OrganizationalHierarchyOptions::applyBadanUsahaScope($query))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Pilih badan usaha')
                    ->createOptionForm(
                        $canCreateBadanUsaha
                        ? [
                            TextInput::make('name')
                                ->required()
                                ->unique()
                                ->maxLength(255)
                                ->helperText('Auto-format ke UPPERCASE tanpa spasi')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '_', trim($state)))),
                        ]
                        : null
                    )
                    ->helperText(
                        $canCreateBadanUsaha
                        ? 'Pilih Badan Usaha atau tambah baru jika belum ada.'
                        : 'Pilih Badan Usaha.'
                    ),
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Akan otomatis diformat ke UPPERCASE tanpa spasi. Contoh: divisi a → DIVISI_A')
                    ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '_', trim($state)))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
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
            ->defaultSort('name', 'asc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->groups([
                Group::make('badanusaha.name')
                    ->label('Badan Usaha'),
            ])
            ->filters([
                SelectFilter::make('badanusaha')
                    ->relationship('badanUsaha', 'name', fn (Builder $query) => $query->active())
                    ->searchable()
                    ->preload(),
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
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $user = auth()->user();

                if (! $user || ! $user->role) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $scopeLevel = $user->role->organizational_scope_level;

                if (! $scopeLevel) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                // If role has 'all' access, no filtering needed
                if ($scopeLevel === 'all') {
                    return;
                }

                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();

                if (empty($badanUsahaIds) && empty($divisiIds)) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                // Apply filters based on assignments
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('divisions.badanusaha_id', $badanUsahaIds);
                }

                if (! empty($divisiIds)) {
                    $query->whereIn('divisions.id', $divisiIds);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDivisions::route('/'),
        ];
    }
}
