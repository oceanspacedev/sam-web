<?php

namespace App\Filament\Resources\Clusters;

use App\Filament\Resources\Clusters\Pages\ManageClusters;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClusterResource extends Resource
{
    protected static ?string $model = Cluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('badanusaha_id')
                    ->label('Badan Usaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->placeholder('Pilih badan usaha')
                    ->createOptionForm([
                        TextInput::make('name')
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

                        // If role has 'all' scope, show all
                        if ($role->organizational_scope_level === 'all') {
                            return BadanUsaha::pluck('name', 'id');
                        }

                        // Use pivot table for current user's assignments
                        return $user->badanUsahas()->pluck('name', 'id');
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('divisi_id', null);
                        $set('region_id', null);
                    }),

                Select::make('divisi_id')
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
                Select::make('region_id')
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
                TextInput::make('name')
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                BadgeColumn::make('badanusaha.name')
                    ->label('Badan Usaha')
                    ->color('primary')
                    ->searchable(),
                BadgeColumn::make('divisi.name')
                    ->label('Divisi')
                    ->color('success')
                    ->searchable(),
                BadgeColumn::make('region.name')
                    ->label('Region')
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('outlets_count')
                    ->label('Outlets')
                    ->counts('outlets')
                    ->badge()
                    ->color('info'),
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
                Group::make('region.name')
                    ->label('Region')
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('badanusaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Badan Usaha'),
                SelectFilter::make('divisi')
                    ->relationship('divisi', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Divisi'),
                SelectFilter::make('region')
                    ->relationship('region', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Region'),
                Filter::make('has_outlets')
                    ->label('Has Outlets')
                    ->query(fn (Builder $query) => $query->has('outlets')),
                Filter::make('empty')
                    ->label('Empty (No Outlets)')
                    ->query(fn (Builder $query) => $query->doesntHave('outlets')),
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
                $role = $user->role;
                $scopeLevel = $role->organizational_scope_level ?? 'cluster';

                // If role has 'all' access, no filtering needed
                if ($scopeLevel === 'all') {
                    return;
                }

                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();
                $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                // Apply filters based on assignments
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('clusters.badanusaha_id', $badanUsahaIds);
                }

                if (! empty($divisiIds)) {
                    $query->whereIn('clusters.divisi_id', $divisiIds);
                }

                if (! empty($regionIds)) {
                    $query->whereIn('clusters.region_id', $regionIds);
                }

                if (! empty($clusterIds)) {
                    $query->whereIn('clusters.id', $clusterIds);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClusters::route('/'),
        ];
    }
}
