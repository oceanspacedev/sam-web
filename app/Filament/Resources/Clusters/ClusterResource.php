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
            ->columns(1)
            ->components([
                Select::make('badanusaha_id')
                    ->label('Badan Usaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->placeholder('Pilih badan usaha')
                    ->createOptionForm(
                        auth()->user()->can('create', \App\Models\BadanUsaha::class)
                        ? [
                            TextInput::make('name')
                                ->required()
                                ->unique()
                                ->maxLength(255)
                                ->helperText('Auto-format ke UPPERCASE tanpa spasi')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '_', trim($state)))),
                        ]
                        : null
                    )
                    ->helperText(
                        auth()->user()->can('create', \App\Models\BadanUsaha::class)
                        ? 'Pilih Badan Usaha atau tambah baru.'
                        : 'Pilih Badan Usaha.'
                    )
                    ->options(function (callable $get) {
                        $user = auth()->user();
                        $role = $user->role;

                        // If role has 'all' scope, show all
                        if ($role->organizational_scope_level === 'all') {
                            return BadanUsaha::active()->orderBy('name', 'asc')->pluck('name', 'id');
                        }

                        // Use pivot table for current user's assignments
                        return $user->badanUsahas()->active()->orderBy('name', 'asc')->pluck('name', 'badan_usahas.id');
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
                    ->createOptionForm(
                        auth()->user()->can('create', \App\Models\Division::class)
                        ? [
                            TextInput::make('name')
                                ->required()
                                ->unique()
                                ->maxLength(255)
                                ->helperText('Auto-format ke UPPERCASE tanpa spasi')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '_', trim($state)))),
                        ]
                        : null
                    )
                    ->createOptionUsing(function (array $data, callable $get) {
                        $badanusahaId = $get('badanusaha_id');
                        if (! $badanusahaId) {
                            throw new \Exception('Pilih Badan Usaha terlebih dahulu.');
                        }

                        $division = \App\Models\Division::create([
                            'name' => $data['name'],
                            'badanusaha_id' => $badanusahaId,
                        ]);

                        return $division->id;
                    })
                    ->helperText(
                        auth()->user()->can('create', \App\Models\Division::class)
                        ? 'Divisi akan muncul setelah Badan Usaha dipilih. Atau tambah baru jika belum ada.'
                        : 'Divisi akan muncul setelah Badan Usaha dipilih.'
                    )
                    ->options(function (callable $get) {
                        $badanusahaId = $get('badanusaha_id');
                        if (! $badanusahaId) {
                            return [];
                        }

                        $user = auth()->user();
                        $query = Division::active()->where('badanusaha_id', $badanusahaId);

                        // Apply user scope filtering
                        if ($user && $user->role->organizational_scope_level !== 'all') {
                            $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                            if (! empty($divisiIds)) {
                                $query->whereIn('divisions.id', $divisiIds);
                            }
                        }

                        return $query->orderBy('name', 'asc')->pluck('name', 'id');
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
                    ->createOptionForm(
                        auth()->user()->can('create', \App\Models\Region::class)
                        ? [
                            TextInput::make('name')
                                ->required()
                                ->unique()
                                ->maxLength(255)
                                ->helperText('Auto-format ke UPPERCASE tanpa spasi')
                                ->dehydrateStateUsing(fn ($state) => strtoupper(str_replace(' ', '_', trim($state)))),
                        ]
                        : null
                    )
                    ->createOptionUsing(function (array $data, callable $get) {
                        $divisiId = $get('divisi_id');
                        if (! $divisiId) {
                            throw new \Exception('Pilih Divisi terlebih dahulu.');
                        }

                        $region = \App\Models\Region::create([
                            'name' => $data['name'],
                            'divisi_id' => $divisiId,
                        ]);

                        return $region->id;
                    })
                    ->helperText(
                        auth()->user()->can('create', \App\Models\Region::class)
                        ? 'Region akan muncul setelah Divisi dipilih. Atau tambah baru jika belum ada.'
                        : 'Region akan muncul setelah Divisi dipilih.'
                    )
                    ->options(function (callable $get) {
                        $divisiId = $get('divisi_id');
                        if (! $divisiId) {
                            return [];
                        }

                        $user = auth()->user();
                        $query = Region::where('divisi_id', $divisiId);

                        // Apply user scope filtering
                        if ($user && in_array($user->role->organizational_scope_level, ['region', 'cluster'], true)) {
                            $regionIds = $user->regions()->pluck('regions.id')->toArray();
                            if (! empty($regionIds)) {
                                $query->whereIn('regions.id', $regionIds);
                            }
                        }

                        return $query->orderBy('name', 'asc')->pluck('name', 'id');
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
                    ->relationship('badanUsaha', 'name', fn (Builder $query) => $query->active())
                    ->searchable()
                    ->preload()
                    ->label('Badan Usaha'),
                SelectFilter::make('divisi')
                    ->relationship('divisi', 'name', fn (Builder $query) => $query->active())
                    ->searchable()
                    ->preload()
                    ->label('Divisi'),
                SelectFilter::make('region')
                    ->relationship('region', 'name', fn (Builder $query) => $query->active())
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
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $user = auth()->user();

                // CRITICAL: Block access if user or role is null
                if (! $user || ! $user->role) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $role = $user->role;
                $scopeLevel = $role->organizational_scope_level;

                // CRITICAL: Block access if scope level is null
                if (! $scopeLevel) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                // If role has 'all' access, no filtering needed
                if ($scopeLevel === 'all') {
                    return;
                }

                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();
                $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                // CRITICAL: If user has no assignments at all, block access
                $hasAnyAssignment = ! empty($badanUsahaIds) || ! empty($divisiIds) || ! empty($regionIds) || ! empty($clusterIds);
                if (! $hasAnyAssignment) {
                    $query->whereRaw('1 = 0');

                    return;
                }

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
