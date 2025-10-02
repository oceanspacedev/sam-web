<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegionResource\Pages;
use App\Models\Division;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('badanusaha_id')
                    ->label('Badan Usaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->placeholder('Pilih badan usaha')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique()
                            ->maxLength(255)
                            ->helperText('Auto-format ke UPPERCASE tanpa spasi')
                            ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '_', trim($state)))),
                    ])
                    ->helperText('Pilih Badan Usaha atau tambah baru.')
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
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('divisi_id', null);
                    }),
                Forms\Components\Select::make('divisi_id')
                    ->label('Divisi')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->placeholder('Pilih divisi')
                    ->helperText('Divisi akan muncul setelah Badan Usaha dipilih.')
                    ->options(function (callable $get) {
                        $badanusahaId = $get('badanusaha_id');
                        if (! $badanusahaId) {
                            return [];
                        }

                        return Division::where('badanusaha_id', $badanusahaId)
                            ->pluck('name', 'id');
                    }),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Akan otomatis diformat ke UPPERCASE tanpa spasi. Contoh: region jakarta → REGION_JAKARTA')
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
                Tables\Columns\BadgeColumn::make('badanusaha.name')
                    ->label('Badan Usaha')
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('divisi.name')
                    ->label('Divisi')
                    ->color('success')
                    ->searchable(),
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
                Group::make('divisi.name')
                    ->label('Divisi')
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('badanusaha')
                    ->relationship('badanusaha', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Badan Usaha'),
                Tables\Filters\SelectFilter::make('divisi')
                    ->relationship('divisi', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Divisi'),
                Tables\Filters\Filter::make('has_clusters')
                    ->label('Has Clusters')
                    ->query(fn (Builder $query) => $query->has('clusters')),
                Tables\Filters\Filter::make('empty')
                    ->label('Empty (No Clusters)')
                    ->query(fn (Builder $query) => $query->doesntHave('clusters')),
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
                    $query->where('regions.badanusaha_id', $user->badanusaha_id);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRegions::route('/'),
        ];
    }
}
