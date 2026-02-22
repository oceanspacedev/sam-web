<?php

namespace App\Filament\Resources\Divisions\RelationManagers;

use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SettingRelationManager extends RelationManager
{
    protected static string $relationship = 'setting';

    protected static ?string $title = 'Pengaturan Divisi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('allow_register_visit')
                ->label('Izinkan Visit ke LEAD/NOO')
                ->helperText('Jika aktif, sales bisa melakukan visit ke LEAD dan NOO selain ke outlet')
                ->default(false),

            TextInput::make('max_visit_per_day')
                ->label('Maks Visit per Hari')
                ->helperText('0 = tidak dibatasi')
                ->numeric()
                ->default(0)
                ->minValue(0),

            TextInput::make('default_register_radius')
                ->label('Radius Default Register (meter)')
                ->helperText('Radius GPS untuk validasi visit ke LEAD/NOO')
                ->numeric()
                ->default(100)
                ->minValue(0)
                ->suffix('meter'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('allow_register_visit')
                    ->label('Visit Register')
                    ->boolean(),
                Tables\Columns\TextColumn::make('max_visit_per_day')
                    ->label('Maks Visit/Hari')
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Tidak dibatasi' : $state),
                Tables\Columns\TextColumn::make('default_register_radius')
                    ->label('Radius Register')
                    ->suffix(' m'),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->visible(fn () => ! $this->getOwnerRecord()->setting()->exists()),
            ])
            ->actions([
                Actions\EditAction::make(),
            ]);
    }
}
