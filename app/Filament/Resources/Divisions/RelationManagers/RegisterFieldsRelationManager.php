<?php

namespace App\Filament\Resources\Divisions\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RegisterFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'registerFields';

    protected static ?string $title = 'Custom Fields';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama Field (Slug)')
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Nama unik untuk field (tanpa spasi, gunakan underscore)')
                ->alphaDash()
                ->dehydrateStateUsing(fn ($state) => str()->slug($state, '_')),

            Forms\Components\TextInput::make('label')
                ->label('Label')
                ->required()
                ->helperText('Label yang ditampilkan ke user'),

            Forms\Components\Select::make('type')
                ->label('Tipe Field')
                ->required()
                ->options([
                    'text' => 'Text',
                    'number' => 'Number',
                    'select' => 'Select (Dropdown)',
                    'checkbox' => 'Checkbox',
                    'date' => 'Date',
                    'file' => 'File Upload',
                ])
                ->live()
                ->default('text'),

            Forms\Components\TextInput::make('options')
                ->label('Opsi (untuk tipe Select)')
                ->helperText('Pisahkan dengan koma. Contoh: Option A,Option B,Option C')
                ->visible(fn (callable $get) => $get('type') === 'select')
                ->dehydrateStateUsing(function ($state) {
                    if (empty($state)) {
                        return null;
                    }

                    return array_map('trim', explode(',', $state));
                }),

            Forms\Components\Toggle::make('is_required')
                ->label('Wajib Diisi')
                ->default(false),

            Forms\Components\Select::make('applies_to')
                ->label('Berlaku Untuk')
                ->required()
                ->options([
                    'lead' => 'LEAD saja',
                    'noo' => 'NOO saja',
                    'both' => 'LEAD dan NOO',
                ])
                ->default('both'),

            Forms\Components\TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->default(0)
                ->minValue(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipe')
                    ->colors([
                        'primary' => 'text',
                        'info' => 'number',
                        'success' => 'select',
                        'warning' => 'checkbox',
                        'danger' => 'date',
                        'gray' => 'file',
                    ]),
                Tables\Columns\BadgeColumn::make('applies_to')
                    ->label('Berlaku Untuk')
                    ->colors([
                        'danger' => 'lead',
                        'success' => 'noo',
                        'primary' => 'both',
                    ]),
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Wajib')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
