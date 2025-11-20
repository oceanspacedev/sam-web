<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Permission;
use App\Models\Role;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('Identitas & Hirarki')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nama Role')
                                ->required()
                                ->maxLength(255),
                            Select::make('parent_role_id')
                                ->label('Parent Role')
                                ->helperText('Pilih role induk jika ada struktur atasan (mis. TM → ASM).')
                                ->options(function (?Role $record) {
                                    return Role::query()
                                        ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                                        ->orderBy('name')
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->placeholder('Pilih parent role (opsional)'),
                        ])
                        ->columns(2),
                    Section::make('Permissions')
                        ->schema(static::getPermissionSchema())
                        ->visible(fn ($get) => $get('can_access_web') !== false),
                ])->columnSpan(3),
                Group::make([
                    Section::make('Akses & Scope')
                        ->schema([
                            Toggle::make('can_access_web')
                                ->label('Dapat Akses Web')
                                ->helperText('Nonaktifkan jika role ini hanya untuk mobile/API.')
                                ->reactive()
                                ->required(),
                            Select::make('organizational_scope_level')
                                ->label('Organizational Scope Level')
                                ->helperText('Tentukan tingkat hierarki akses data untuk role ini (berlaku untuk API & akses web).')
                                ->options([
                                    'all' => 'All (Full Access)',
                                    'badanusaha' => 'Badan Usaha',
                                    'divisi' => 'Divisi',
                                    'region' => 'Region',
                                    'cluster' => 'Cluster',
                                ])
                                ->default('cluster')
                                ->reactive()
                                ->required(),
                        ])
                        ->columns(1),
                ])->columnSpan(1),
            ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                IconColumn::make('can_access_web')
                    ->label('Akses Web')
                    ->boolean(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('-'),
                TextColumn::make('organizational_scope_level')
                    ->label('Scope Level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'all' => 'success',
                        'badanusaha' => 'info',
                        'divisi' => 'warning',
                        'region' => 'primary',
                        'cluster' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('permissions_count')
                    ->label('Jumlah Izin')
                    ->badge()
                    ->counts('permissions'),
                TextColumn::make('user_count')
                    ->label('Jumlah User')
                    ->badge()
                    ->counts('user'),
            ])
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
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
            Grid::make(3)
                ->schema(
                    $permissions->map(function ($permissions, $resource) {
                        $operations = $permissions->pluck('name')->toArray();

                        return Section::make(self::formatHeadline($resource))
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
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
