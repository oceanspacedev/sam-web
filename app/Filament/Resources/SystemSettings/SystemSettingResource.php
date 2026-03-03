<?php

namespace App\Filament\Resources\SystemSettings;

use App\Filament\Resources\SystemSettings\Pages;
use App\Models\SystemSetting;
use App\Support\OrganizationalHierarchyOptions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return 'System Settings';
    }

    public static function getModelLabel(): string
    {
        return 'System Setting';
    }

    public static function getPluralModelLabel(): string
    {
        return 'System Settings';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('scope_level')
                    ->label('Level Aturan')
                    ->options(static::scopeOptionsForCurrentUser())
                    ->default(function (): ?string {
                        $allowedScopes = array_keys(static::scopeOptionsForCurrentUser());

                        if ($allowedScopes === []) {
                            return null;
                        }

                        if (in_array(SystemSetting::SCOPE_DIVISION, $allowedScopes, true)) {
                            return SystemSetting::SCOPE_DIVISION;
                        }

                        return $allowedScopes[0];
                    })
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if ($state === SystemSetting::SCOPE_GLOBAL) {
                            $set('badanusaha_id', null);
                            $set('division_id', null);
                            $set('region_id', null);
                            $set('cluster_id', null);

                            return;
                        }

                        if ($state === SystemSetting::SCOPE_BADANUSAHA) {
                            $set('division_id', null);
                            $set('region_id', null);
                            $set('cluster_id', null);

                            return;
                        }

                        if ($state === SystemSetting::SCOPE_DIVISION) {
                            $set('region_id', null);
                            $set('cluster_id', null);

                            return;
                        }

                        if ($state === SystemSetting::SCOPE_REGION) {
                            $set('cluster_id', null);
                        }
                    })
                    ->helperText('Semakin spesifik level-nya, aturan akan override level di atasnya.'),
                Forms\Components\Select::make('badanusaha_id')
                    ->label('Badan Usaha')
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => OrganizationalHierarchyOptions::badanUsaha())
                    ->getSearchResultsUsing(fn (string $search): array => OrganizationalHierarchyOptions::searchBadanUsaha($search))
                    ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::badanUsahaLabel($value))
                    ->native(false)
                    ->visible(fn (callable $get): bool => in_array($get('scope_level'), [
                        SystemSetting::SCOPE_BADANUSAHA,
                        SystemSetting::SCOPE_DIVISION,
                        SystemSetting::SCOPE_REGION,
                        SystemSetting::SCOPE_CLUSTER,
                    ], true))
                    ->required(fn (callable $get): bool => in_array($get('scope_level'), [
                        SystemSetting::SCOPE_BADANUSAHA,
                        SystemSetting::SCOPE_DIVISION,
                        SystemSetting::SCOPE_REGION,
                        SystemSetting::SCOPE_CLUSTER,
                    ], true))
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('division_id', null);
                        $set('region_id', null);
                        $set('cluster_id', null);
                    }),
                Forms\Components\Select::make('division_id')
                    ->label('Division')
                    ->searchable()
                    ->preload()
                    ->options(fn (callable $get): array => OrganizationalHierarchyOptions::division($get('badanusaha_id')))
                    ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchDivision($search, $get('badanusaha_id')))
                    ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::divisionLabel($value))
                    ->native(false)
                    ->visible(fn (callable $get): bool => in_array($get('scope_level'), [
                        SystemSetting::SCOPE_DIVISION,
                        SystemSetting::SCOPE_REGION,
                        SystemSetting::SCOPE_CLUSTER,
                    ], true))
                    ->required(fn (callable $get): bool => in_array($get('scope_level'), [
                        SystemSetting::SCOPE_DIVISION,
                        SystemSetting::SCOPE_REGION,
                        SystemSetting::SCOPE_CLUSTER,
                    ], true))
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('region_id', null);
                        $set('cluster_id', null);
                    }),
                Forms\Components\Select::make('region_id')
                    ->label('Region')
                    ->searchable()
                    ->preload()
                    ->options(fn (callable $get): array => OrganizationalHierarchyOptions::region($get('division_id')))
                    ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchRegion($search, $get('division_id')))
                    ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::regionLabel($value))
                    ->native(false)
                    ->visible(fn (callable $get): bool => in_array($get('scope_level'), [
                        SystemSetting::SCOPE_REGION,
                        SystemSetting::SCOPE_CLUSTER,
                    ], true))
                    ->required(fn (callable $get): bool => in_array($get('scope_level'), [
                        SystemSetting::SCOPE_REGION,
                        SystemSetting::SCOPE_CLUSTER,
                    ], true))
                    ->live()
                    ->afterStateUpdated(function (callable $set): void {
                        $set('cluster_id', null);
                    }),
                Forms\Components\Select::make('cluster_id')
                    ->label('Cluster')
                    ->searchable()
                    ->preload()
                    ->options(fn (callable $get): array => OrganizationalHierarchyOptions::cluster($get('region_id')))
                    ->getSearchResultsUsing(fn (string $search, callable $get): array => OrganizationalHierarchyOptions::searchCluster($search, $get('region_id')))
                    ->getOptionLabelUsing(fn ($value): ?string => OrganizationalHierarchyOptions::clusterLabel($value))
                    ->native(false)
                    ->visible(fn (callable $get): bool => $get('scope_level') === SystemSetting::SCOPE_CLUSTER)
                    ->required(fn (callable $get): bool => $get('scope_level') === SystemSetting::SCOPE_CLUSTER),
                Forms\Components\Toggle::make('allow_register_visit')
                    ->label('Izinkan Visit/Plan Visit ke LEAD/NOO')
                    ->default(false)
                    ->onColor('success')
                    ->offColor('gray')
                    ->inline(false),
                Forms\Components\TextInput::make('plan_visit_min_days')
                    ->label('Minimal Hari Plan Visit')
                    ->numeric()
                    ->default(3)
                    ->minValue(0)
                    ->helperText('Contoh: 3 berarti plan visit minimal H+3 dari hari ini.')
                    ->suffix('hari'),
                Forms\Components\TextInput::make('default_register_radius')
                    ->label('Radius Default Register (meter)')
                    ->numeric()
                    ->default(100)
                    ->minValue(1)
                    ->suffix('m'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scope_level')
                    ->label('Level')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::scopeOptions()[$state] ?? strtoupper((string) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('badanusaha.name')
                    ->label('Badan Usaha')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('division.name')
                    ->label('Division')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cluster.name')
                    ->label('Cluster')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\IconColumn::make('allow_register_visit')
                    ->label('Visit/Plan Register')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('plan_visit_min_days')
                    ->label('Min Plan')
                    ->formatStateUsing(fn ($state) => (int) $state.' hari')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('default_register_radius')
                    ->label('Radius')
                    ->formatStateUsing(fn ($state) => (int) $state.' m')
                    ->badge()
                    ->color('primary'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('scope_level')
                    ->label('Level')
                    ->options(static::scopeOptions()),
                Tables\Filters\TernaryFilter::make('allow_register_visit')
                    ->label('Izinkan Visit/Plan Visit Register')
                    ->placeholder('Semua')
                    ->trueLabel('Ya')
                    ->falseLabel('Tidak'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Buat Setting')
                    ->slideOver()
                    ->modalWidth('md'),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->emptyStateHeading('Belum ada system setting')
            ->emptyStateDescription('Buat aturan global atau hierarkis untuk Visit, Plan Visit, dan batas minimal plan visit')
            ->emptyStateIcon('heroicon-o-cog-6-tooth');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['badanusaha', 'division', 'region', 'cluster']);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user || ! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        $scopeLevel = strtolower((string) $user->role->organizational_scope_level);

        if ($scopeLevel === 'all') {
            return $query;
        }

        $ids = $user->getOrganizationalIds();
        $badanUsahaIds = $ids['badanusaha'] ?? [];
        $divisionIds = $ids['divisi'] ?? [];
        $regionIds = $ids['region'] ?? [];
        $clusterIds = $ids['cluster'] ?? [];

        $hasAnyAssignment = $badanUsahaIds !== [] || $divisionIds !== [] || $regionIds !== [] || $clusterIds !== [];
        if (! $hasAnyAssignment) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scopeQuery) use ($scopeLevel, $badanUsahaIds, $divisionIds, $regionIds, $clusterIds): void {
            if ($scopeLevel === 'badanusaha') {
                if ($badanUsahaIds !== []) {
                    $scopeQuery->whereIn('system_settings.badanusaha_id', $badanUsahaIds);
                } else {
                    $scopeQuery->whereRaw('1 = 0');
                }

                return;
            }

            if ($badanUsahaIds !== []) {
                $scopeQuery->orWhere(function (Builder $query) use ($badanUsahaIds): void {
                    $query
                        ->where('system_settings.scope_level', SystemSetting::SCOPE_BADANUSAHA)
                        ->whereIn('system_settings.badanusaha_id', $badanUsahaIds);
                });
            }

            if ($divisionIds !== []) {
                $scopeQuery->orWhereIn('system_settings.division_id', $divisionIds);
            }

            if (in_array($scopeLevel, ['region', 'cluster'], true) && $regionIds !== []) {
                $scopeQuery->orWhereIn('system_settings.region_id', $regionIds);
            }

            if ($scopeLevel === 'cluster' && $clusterIds !== []) {
                $scopeQuery->orWhereIn('system_settings.cluster_id', $clusterIds);
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemSettings::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function scopeOptions(): array
    {
        return [
            SystemSetting::SCOPE_GLOBAL => 'Global',
            SystemSetting::SCOPE_BADANUSAHA => 'Badan Usaha',
            SystemSetting::SCOPE_DIVISION => 'Division',
            SystemSetting::SCOPE_REGION => 'Region',
            SystemSetting::SCOPE_CLUSTER => 'Cluster',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function scopeOptionsForCurrentUser(): array
    {
        $allScopes = static::scopeOptions();

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $scopeLevel = strtolower((string) ($user?->role?->organizational_scope_level ?? ''));

        $allowedScopeKeys = match ($scopeLevel) {
            'all' => [
                SystemSetting::SCOPE_GLOBAL,
                SystemSetting::SCOPE_BADANUSAHA,
                SystemSetting::SCOPE_DIVISION,
                SystemSetting::SCOPE_REGION,
                SystemSetting::SCOPE_CLUSTER,
            ],
            'badanusaha' => [
                SystemSetting::SCOPE_BADANUSAHA,
                SystemSetting::SCOPE_DIVISION,
                SystemSetting::SCOPE_REGION,
                SystemSetting::SCOPE_CLUSTER,
            ],
            'divisi' => [
                SystemSetting::SCOPE_DIVISION,
                SystemSetting::SCOPE_REGION,
                SystemSetting::SCOPE_CLUSTER,
            ],
            'region' => [
                SystemSetting::SCOPE_REGION,
                SystemSetting::SCOPE_CLUSTER,
            ],
            'cluster' => [
                SystemSetting::SCOPE_CLUSTER,
            ],
            default => [],
        };

        return array_intersect_key($allScopes, array_flip($allowedScopeKeys));
    }
}
