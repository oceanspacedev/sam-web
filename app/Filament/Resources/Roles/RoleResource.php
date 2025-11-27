<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Role;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasShieldFormComponents;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RoleResource extends Resource
{
    use HasShieldFormComponents;

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
                    static::getShieldFormComponents()
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                /** @var User|null $user */
                $user = Auth::user();

                if (! $user || ! $user->role) {
                    return;
                }

                // Super Admin can see all
                if ($user->role->name === 'SUPER ADMIN') {
                    return;
                }

                // Get all descendant role IDs
                $descendantIds = self::getAllDescendantIds($user->role);

                $query->whereIn('roles.id', $descendantIds);
            });
    }

    public static function getAllDescendantIds(Role $role): array
    {
        $ids = [];
        foreach ($role->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, self::getAllDescendantIds($child));
        }

        return $ids;
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
