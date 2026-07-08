<?php

namespace App\Filament\Resources\BadanUsahas;

use App\Filament\Resources\BadanUsahas\Pages\ManageBadanUsahas;
use App\Models\BadanUsaha;
use App\Support\FilamentOrganizationalScope;
use App\Support\OrganizationalDeleteGuard;
use App\Support\OrganizationalFormFields;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BadanUsahaResource extends Resource
{
    protected static ?string $model = BadanUsaha::class;

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-building-08';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                OrganizationalFormFields::code(BadanUsaha::class),
                OrganizationalFormFields::name(BadanUsaha::class),
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
                TextColumn::make('divisions_count')
                    ->label('Divisions')
                    ->counts('divisions')
                    ->badge()
                    ->color('primary'),
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
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code', 'asc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->filters([
                Filter::make('has_divisions')
                    ->label('Has Divisions')
                    ->query(fn (Builder $query) => $query->has('divisions')),
                Filter::make('empty')
                    ->label('Empty (No Divisions)')
                    ->query(fn (Builder $query) => $query->doesntHave('divisions')),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Badan Usaha')
                    ->modalDescription('Penghapusan struktur organisasi dapat berdampak pada data relasi. Pastikan tidak ada data terkait sebelum melanjutkan.')
                    ->action(fn (BadanUsaha $record) => OrganizationalDeleteGuard::deleteRecord($record)),
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
                FilamentOrganizationalScope::applyBadanUsahaIds($query, $user);
            })
            ->withCount(['divisions', 'regions', 'clusters']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBadanUsahas::route('/'),
        ];
    }
}
