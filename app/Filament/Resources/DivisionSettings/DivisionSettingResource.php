<?php

namespace App\Filament\Resources\DivisionSettings;

use App\Filament\Resources\DivisionSettings\Pages;
use App\Models\Division;
use App\Models\DivisionSetting;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DivisionSettingResource extends Resource
{
    protected static ?string $model = DivisionSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('division_id')
                    ->label('Division')
                    ->options(function () {
                        return Division::active()->orderBy('name')->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Pilih divisi untuk setting ini'),
                Forms\Components\Toggle::make('allow_register_visit')
                    ->label('Izinkan Visit ke LEAD/NOO')
                    ->default(false)
                    ->onColor('success')
                    ->offColor('gray')
                    ->inline(false),
                Forms\Components\TextInput::make('default_register_radius')
                    ->label('Radius Default Register (meter)')
                    ->numeric()
                    ->default(100)
                    ->minValue(1)
                    ->suffix('m'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('division.name')
                    ->label('Division')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\IconColumn::make('allow_register_visit')
                    ->label('Izinkan Visit')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('default_register_radius')
                    ->label('Radius')
                    ->formatStateUsing(fn ($state) => $state . ' m')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('division.name')
            ->filters([
                Tables\Filters\TernaryFilter::make('allow_register_visit')
                    ->label('Izinkan Visit')
                    ->placeholder('Semua')
                    ->trueLabel('Ya')
                    ->falseLabel('Tidak'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Buat Setting')
                    ->slideOver()
                    ->modalWidth('md'),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading('Belum ada setting')
            ->emptyStateDescription('Buat setting division untuk mengatur visit ke LEAD/NOO')
            ->emptyStateIcon('heroicon-o-cog-6-tooth');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('division');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDivisionSettings::route('/'),
        ];
    }
}
