<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DivisionResource\Pages;
use App\Models\Division;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DivisionResource extends Resource
{
    protected static ?string $model = Division::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('badanusaha_id')
                    ->label('Badan Usaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->required()
                    ->placeholder('Pilih badan usaha')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique()
                            ->maxLength(255)
                            ->helperText('Auto-format ke UPPERCASE tanpa spasi')
                            ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '_', trim($state)))),
                    ])
                    ->helperText('Pilih Badan Usaha atau tambah baru jika belum ada.')
                    ->options(function (callable $get) {
                        $user = auth()->user();
                        $role = $user->role;

                        if ($role->filter_type === 'badanusaha') {
                            return \App\Models\BadanUsaha::whereIn('id', $role->filter_data ?? [])
                                ->pluck('name', 'id');
                        } elseif ($role->filter_type === 'all') {
                            return \App\Models\BadanUsaha::pluck('name', 'id');
                        }

                        return \App\Models\BadanUsaha::where('id', $user->badanusaha_id)
                            ->pluck('name', 'id');
                    }),
                Forms\Components\TextInput::make('name')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('badanusaha.name')
                    ->label('Badan Usaha')
                    ->color('primary')
                    ->searchable(),
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
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
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
                Tables\Filters\SelectFilter::make('badanusaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('has_regions')
                    ->label('Has Regions')
                    ->query(fn (Builder $query) => $query->has('regions')),
                Tables\Filters\Filter::make('empty')
                    ->label('Empty (No Regions)')
                    ->query(fn (Builder $query) => $query->doesntHave('regions')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $user = auth()->user();
                if ($user->role->name == 'SUPER ADMIN') {
                    return;
                } else {
                    $query->where('divisions.badanusaha_id', $user->badanusaha_id);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDivisions::route('/'),
        ];
    }
}
