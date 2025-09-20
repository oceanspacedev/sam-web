<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BadanUsahaResource\Pages;
use App\Models\BadanUsaha;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BadanUsahaResource extends Resource
{
    protected static ?string $model = BadanUsaha::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = 'Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('divisions_count')
                    ->label('Divisions')
                    ->counts('divisions')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('regions_count')
                    ->label('Regions')
                    ->counts('regions')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('clusters_count')
                    ->label('Clusters')
                    ->counts('clusters')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->filters([
                Tables\Filters\Filter::make('has_divisions')
                    ->label('Has Divisions')
                    ->query(fn (Builder $query) => $query->has('divisions')),
                Tables\Filters\Filter::make('empty')
                    ->label('Empty (No Divisions)')
                    ->query(fn (Builder $query) => $query->doesntHave('divisions')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (BadanUsaha $record) {
                        if ($record->divisions()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot delete')
                                ->body('This Badan Usaha has divisions. Please delete divisions first.')
                                ->danger()
                                ->send();

                            return;
                        }
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBadanUsahas::route('/'),
        ];
    }
}
