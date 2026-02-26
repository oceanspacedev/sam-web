<?php

namespace App\Filament\Resources\SystemSettings;

use App\Filament\Resources\SystemSettings\Pages;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\SystemSetting;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->options(static::scopeOptions())
                    ->default(SystemSetting::SCOPE_DIVISION)
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
                    ->options(fn () => BadanUsaha::active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
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
                    ->options(function (callable $get) {
                        $badanusahaId = $get('badanusaha_id');

                        return Division::active()
                            ->when($badanusahaId, fn ($query) => $query->where('badanusaha_id', $badanusahaId))
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
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
                    ->options(function (callable $get) {
                        $divisionId = $get('division_id');

                        return Region::active()
                            ->when($divisionId, fn ($query) => $query->where('divisi_id', $divisionId))
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
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
                    ->options(function (callable $get) {
                        $regionId = $get('region_id');

                        return Cluster::active()
                            ->when($regionId, fn ($query) => $query->where('region_id', $regionId))
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
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
        return parent::getEloquentQuery()->with(['badanusaha', 'division', 'region', 'cluster']);
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
}
