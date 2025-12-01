<?php

namespace App\Filament\Resources\BadanUsahas;

use App\Filament\Resources\BadanUsahas\Pages\ManageBadanUsahas;
use App\Models\BadanUsaha;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BadanUsahaResource extends Resource
{
    protected static ?string $model = BadanUsaha::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Akan otomatis diformat ke UPPERCASE tanpa spasi. Contoh: badan usaha a → BADAN_USAHA_A')
                    ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '_', trim($state))))
                    ->columnSpanFull(),
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
            ->defaultSort('name', 'asc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
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
                    ->action(function (BadanUsaha $record) {
                        if ($record->divisions()->exists()) {
                            Notification::make()
                                ->title('Cannot delete')
                                ->body('This Badan Usaha has divisions. Please delete divisions first.')
                                ->danger()
                                ->send();

                            return;
                        }
                        $record->delete();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $user = auth()->user();
                $role = $user->role;
                $scopeLevel = $role->organizational_scope_level ?? 'badan_usaha';

                // If role has 'all' access, no filtering needed
                if ($scopeLevel === 'all') {
                    return;
                }

                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();

                // Apply filters based on assignments
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('badan_usahas.id', $badanUsahaIds);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBadanUsahas::route('/'),
        ];
    }
}
