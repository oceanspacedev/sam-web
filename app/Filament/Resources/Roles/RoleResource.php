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
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
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

    protected static string|\BackedEnum|null $navigationIcon = 'untitledui-settings-02';

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
                                ->searchable()
                                ->options(fn (): array => self::parentRoleOptions())
                                ->getSearchResultsUsing(fn (string $search): array => self::parentRoleOptions($search))
                                ->getOptionLabelUsing(fn ($value): ?string => $value ? Role::query()->whereKey($value)->value('name') : null)
                                ->placeholder('Pilih parent role (opsional)'),
                        ])
                        ->columns(2),
                    static::getShieldFormComponents()
                        ->visible(fn ($get) => $get('can_access_web') || $get('can_access_mobile')),
                ])->columnSpan(3),
                Group::make([
                    Section::make('Akses & Scope')
                        ->schema([
                            Toggle::make('can_access_web')
                                ->label('Dapat Akses Web')
                                ->helperText('Aktifkan jika role ini dapat mengakses panel web (Filament).')
                                ->live()
                                ->default(false),
                            Toggle::make('can_access_mobile')
                                ->label('Dapat Akses Mobile')
                                ->helperText('Aktifkan jika role ini dapat mengakses aplikasi mobile.')
                                ->live()
                                ->default(false),
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
                                ->live()
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
                    ->label('Web')
                    ->boolean(),
                IconColumn::make('can_access_mobile')
                    ->label('Mobile')
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
            ->deferLoading()
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->authorize('deleteAny'),
                ForceDeleteBulkAction::make()
                    ->authorize('forceDeleteAny'),
                RestoreBulkAction::make()
                    ->authorize('restoreAny'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                /** @var User|null $user */
                $user = Auth::user();

                if (! $user || ! $user->role) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                // Super Admin can see all
                if ($user->role->name === 'SUPER ADMIN') {
                    return;
                }

                // Get all descendant role IDs
                $descendantIds = self::getAllDescendantIds($user->role);

                $query->whereIn('roles.id', $descendantIds);
            })
            ->with(['parent:id,name'])
            ->withCount(['permissions', 'user']);
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

    /**
     * @return array<int, string>
     */
    private static function parentRoleOptions(string $search = '', int $limit = 50): array
    {
        $keyword = trim($search);
        $record = request()->route('record');
        $recordId = is_object($record) ? $record->getKey() : $record;

        return Role::query()
            ->when($recordId, fn (Builder $query) => $query->where('id', '!=', $recordId))
            ->when($keyword !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$keyword.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name', 'id')
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
