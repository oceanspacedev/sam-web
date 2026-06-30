<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Support\FilamentTableEagerLoad;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamMembersRelationManager extends RelationManager
{
    protected static ?string $title = 'Team Member';

    protected static string $relationship = 'teamMembers';

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(FilamentTableEagerLoad::userAssignments());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_lengkap')
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'SUPER ADMIN' => 'danger',
                        'APP DEVELOPER' => 'info',
                        default => 'primary',
                    }),
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('badanUsahas.name')
                    ->label('Badan Usaha')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('divisis.name')
                    ->label('Divisi')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('regions.name')
                    ->label('Region')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('clusters.name')
                    ->label('Cluster')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nama_lengkap')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->headerActions([])
            ->recordActions([])
            ->bulkActions([]);
    }
}
