<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClusterResource\Pages;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClusterResource extends Resource
{
    protected static ?string $model = Cluster::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 4;

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
                        $set('region_id', null);
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
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('region_id', null);
                    }),
                Forms\Components\Select::make('region_id')
                    ->label('Region')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->placeholder('Pilih region')
                    ->helperText('Region akan muncul setelah Divisi dipilih.')
                    ->options(function (callable $get) {
                        $divisiId = $get('divisi_id');
                        if (! $divisiId) {
                            return [];
                        }

                        return Region::where('divisi_id', $divisiId)
                            ->pluck('name', 'id');
                    }),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Akan otomatis diformat ke UPPERCASE tanpa spasi. Contoh: cluster 1 → CLUSTER_1')
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
                Tables\Columns\BadgeColumn::make('divisi.name')
                    ->label('Divisi')
                    ->color('success')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('region.name')
                    ->label('Region')
                    ->color('warning')
                    ->searchable(),
                Tables\Columns\TextColumn::make('outlets_count')
                    ->label('Outlets')
                    ->counts('outlets')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->groups([
                Group::make('region.name')
                    ->label('Region')
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('badanusaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Badan Usaha'),
                Tables\Filters\SelectFilter::make('divisi')
                    ->relationship('divisi', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Divisi'),
                Tables\Filters\SelectFilter::make('region')
                    ->relationship('region', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Region'),
                Tables\Filters\Filter::make('has_outlets')
                    ->label('Has Outlets')
                    ->query(fn (Builder $query) => $query->has('outlets')),
                Tables\Filters\Filter::make('empty')
                    ->label('Empty (No Outlets)')
                    ->query(fn (Builder $query) => $query->doesntHave('outlets')),
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
                $role = $user->role;
                switch ($role->filter_type) {
                    case 'badanusaha':
                        $query->whereIn('clusters.badanusaha_id', $role->filter_data ?? []);
                        break;
                    case 'divisi':
                        $query->whereIn('clusters.divisi_id', $role->filter_data ?? []);
                        break;
                    case 'region':
                        $query->whereIn('clusters.region_id', $role->filter_data ?? []);
                        break;
                    case 'cluster':
                        $query->whereIn('clusters.id', $role->filter_data ?? []);
                        break;
                    case 'all':
                    default:
                        return;
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageClusters::route('/'),
        ];
    }
}
