<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Permission;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Card::make()
                    ->schema([
                        TextInput::make('name')  // Misalnya, nama role
                            ->required()
                            ->maxLength(255),
                        Toggle::make('can_access_web')
                            ->label('Dapat Akses Web')
                            ->helperText('Pilih untuk mengizinkan atau menonaktifkan akses web untuk role ini.')
                            ->reactive()
                            ->required(),
                        Select::make('filter_type')
                            ->options([
                                'badanusaha' => 'Badan Usaha',
                                'divisi' => 'Divisi',
                                'region' => 'Region',
                                'cluster' => 'Cluster',
                                'all' => 'All Data',
                            ])
                            ->visible(fn ($get) => $get('can_access_web') !== false)
                            ->reactive()
                            ->label('Filter Type')
                            ->required(),
                        Select::make('filter_data')
                            ->label('Filter Data')
                            ->options(function ($get) {
                                $filterType = $get('filter_type');

                                switch ($filterType) {
                                    case 'badanusaha':
                                        return \App\Models\BadanUsaha::pluck('name', 'id');

                                    case 'divisi':
                                        return \App\Models\Division::with(['badanusaha'])
                                            ->get()
                                            ->mapWithKeys(function ($division) {
                                                $badanusahaName = $division->badanusaha ? $division->badanusaha->name : 'Tidak ada badan usaha';

                                                return [$division->id => "{$division->name} [{$badanusahaName}]"];
                                            });

                                    case 'region':
                                        return \App\Models\Region::with(['badanusaha'])
                                            ->get()
                                            ->mapWithKeys(function ($region) {
                                                $badanusahaName = $region->badanusaha ? $region->badanusaha->name : 'Tidak ada badan usaha';
                                                $divisiName = $region->divisi ? $region->divisi->name : 'Tidak ada divisi';

                                                return [$region->id => "{$region->name} [{$badanusahaName}/{$divisiName}]"];
                                            });

                                    case 'cluster':
                                        return \App\Models\Cluster::with(['badanusaha', 'divisi', 'region'])
                                            ->get()
                                            ->mapWithKeys(function ($cluster) {
                                                $badanusahaName = $cluster->badanusaha ? $cluster->badanusaha->name : 'Tidak ada badan usaha';
                                                $divisiName = $cluster->divisi ? $cluster->divisi->name : 'Tidak ada divisi';
                                                $regionName = $cluster->region ? $cluster->region->name : 'Tidak ada region';

                                                return [$cluster->id => "{$cluster->name} - {$regionName} [{$badanusahaName}/{$divisiName}]"];
                                            });

                                    default:
                                        return [];
                                }
                            })
                            ->placeholder('Pilih Data')
                            ->reactive()
                            ->visible(fn ($get) => $get('filter_type') && $get('filter_type') !== 'all' && $get('can_access_web') !== false)
                            ->required(fn ($get) => $get('filter_type') !== 'all')
                            ->multiple(),
                    ])
                    ->label('Role Settings')
                    ->columns(2),
                Section::make('Permissions')
                    ->schema(static::getPermissionSchema())
                    ->visible(fn ($get) => $get('can_access_web') !== false)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('can_access_web')
                    ->label('Akses Web')
                    ->boolean(),
                Tables\Columns\TextColumn::make('filter_type')
                    ->label('Filter Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Jumlah Izin')
                    ->badge()
                    ->counts('permissions'),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    protected static function getPermissionSchema(): array
    {
        $permissions = Permission::all()
            ->groupBy(function ($permission) {
                $lastUnderscorePosition = strrpos($permission->name, '_');

                return $lastUnderscorePosition !== false
                    ? substr($permission->name, $lastUnderscorePosition + 1)
                    : $permission->name;
            });

        return [
            Forms\Components\Grid::make(3)
                ->schema(
                    $permissions->map(function ($permissions, $resource) {
                        $operations = $permissions->pluck('name')->toArray();

                        return Card::make(self::formatHeadline($resource))
                            ->schema([
                                Toggle::make("select_all_{$resource}")
                                    ->label('Select All')
                                    ->reactive()
                                    ->afterStateHydrated(function ($component, $state) use ($operations) {
                                        $record = $component->getRecord();
                                        if ($record) {
                                            $existingPermissions = $record->permissions()
                                                ->whereIn('name', $operations)
                                                ->pluck('name')
                                                ->toArray();
                                            $component->state(count($existingPermissions) === count($operations));
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, $get, $set) use ($operations, $resource) {
                                        if ($state) {
                                            $set("permissions.{$resource}", $operations);
                                        } else {
                                            $set("permissions.{$resource}", []);
                                        }
                                    }),
                                CheckboxList::make("permissions.{$resource}")
                                    ->label('')
                                    ->options(self::formatOptions($operations))
                                    ->dehydrated(true)
                                    ->reactive()
                                    ->afterStateHydrated(function ($component, $state) use ($operations) {
                                        $record = $component->getRecord();
                                        if ($record) {
                                            $existingPermissions = $record->permissions()
                                                ->whereIn('name', $operations)
                                                ->pluck('name')
                                                ->toArray();

                                            $component->state($existingPermissions);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, $get, $set) use ($operations, $resource) {
                                        if (count($state) === count($operations)) {
                                            $set("select_all_{$resource}", true);
                                        } else {
                                            $set("select_all_{$resource}", false);
                                        }
                                    })
                                    ->columns(2),
                            ])
                            ->collapsible()
                            ->columnSpan(1);
                    })->values()->toArray()
                )
                ->columnSpanFull(),
        ];
    }

    protected static function formatHeadline(string $resource): string
    {
        return Str::headline(str_replace('::', ' ', $resource));
    }

    protected static function formatOptions(array $operations): array
    {
        return collect($operations)
            ->mapWithKeys(function ($operation) {
                $lastUnderscorePosition = strrpos($operation, '_');
                $baseOperation = $lastUnderscorePosition !== false
                    ? substr($operation, 0, $lastUnderscorePosition)
                    : $operation;
                $label = Str::headline(str_replace('_', ' ', $baseOperation));

                return [$operation => $label];
            })
            ->toArray();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
