<?php

namespace App\Filament\Resources\Regions;

use App\Filament\Resources\Regions\Pages\ManageRegions;
use App\Models\BadanUsaha;
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

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

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
                            return BadanUsaha::orderBy('name', 'asc')->pluck('name', 'id');
                        }

                        // Use pivot table for current user's assignments
                        return $user->badanUsahas()->orderBy('name', 'asc')->pluck('name', 'badan_usahas.id');
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('divisi_id', null);
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
                        $query = Division::where('badanusaha_id', $badanusahaId);

                        // Apply user scope filtering
                        if ($user && $user->role->organizational_scope_level !== 'all') {
                            $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                            if (! empty($divisiIds)) {
                                $query->whereIn('divisions.id', $divisiIds);
                            }
                        }

                        return $query->orderBy('name', 'asc')->pluck('name', 'id');
                    }),
                TextInput::make('name')
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
                Group::make('divisi.name')
                    ->label('Divisi')
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('badanusaha')
                    ->relationship('badanusaha', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Badan Usaha'),
                SelectFilter::make('divisi')
                    ->relationship('divisi', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Divisi'),
                Filter::make('has_clusters')
                    ->label('Has Clusters')
                    ->query(fn (Builder $query) => $query->has('clusters')),
                Filter::make('empty')
                    ->label('Empty (No Clusters)')
                    ->query(fn (Builder $query) => $query->doesntHave('clusters')),
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
                $scopeLevel = $role->organizational_scope_level ?? 'region';

                // If role has 'all' access, no filtering needed
                if ($scopeLevel === 'all') {
                    return;
                }

                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();

                // Apply filters based on assignments
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('regions.badanusaha_id', $badanUsahaIds);
                }

                if (! empty($divisiIds)) {
                    $query->whereIn('regions.divisi_id', $divisiIds);
                }

                if (! empty($regionIds)) {
                    $query->whereIn('regions.id', $regionIds);
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegions::route('/'),
        ];
    }
}
