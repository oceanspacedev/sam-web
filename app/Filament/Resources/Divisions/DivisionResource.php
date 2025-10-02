<?php

namespace App\Filament\Resources\Divisions;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BadanUsaha;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Divisions\Pages\ManageDivisions;
use App\Filament\Resources\DivisionResource\Pages;
use App\Models\Division;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DivisionResource extends Resource
{
    protected static ?string $model = Division::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-briefcase';

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('badanusaha_id')
                    ->label('Badan Usaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->required()
                    ->placeholder('Pilih badan usaha')
                    ->createOptionForm([
                        TextInput::make('name')
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
                            return BadanUsaha::whereIn('id', $role->filter_data ?? [])
                                ->pluck('name', 'id');
                        } elseif ($role->filter_type === 'all') {
                            return BadanUsaha::pluck('name', 'id');
                        }

                        return BadanUsaha::where('id', $user->badanusaha_id)
                            ->pluck('name', 'id');
                    }),
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
                    ->relationship('badanUsaha', 'name')
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
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ManageDivisions::route('/'),
        ];
    }
}
