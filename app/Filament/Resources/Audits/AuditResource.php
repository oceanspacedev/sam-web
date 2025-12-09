<?php

namespace App\Filament\Resources\Audits;

use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Filament\Resources\Audits\Pages\ViewAudit;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class AuditResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Developer';

    protected static ?int $navigationSort = 99;

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Logs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'login', 'created', 'restored' => 'success',
                        'updated' => 'warning',
                        'deleted', 'force-deleted' => 'danger',
                        'logout' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (Activity $record, string $state): string => strtoupper(str_replace('-', ' ', $state)))
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('causer.nama_lengkap')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('properties.ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('properties.url')
                    ->label('URL')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Event')
                    ->options([
                        'login' => 'Login',
                        'logout' => 'Logout',
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                        'force-deleted' => 'Force Deleted',
                    ]),
                TernaryFilter::make('has_subject')
                    ->label('Punya Subject')
                    ->nullable()
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('subject_id'),
                        false: fn (Builder $query) => $query->whereNull('subject_id'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail')
                ->schema([
                    TextEntry::make('description')->label('Deskripsi'),
                    TextEntry::make('event')
                        ->label('Event')
                        ->formatStateUsing(fn (Activity $record) => strtoupper(str_replace('-', ' ', $record->event))),
                    TextEntry::make('log_name')->label('Log Name'),
                    TextEntry::make('created_at')->label('Tanggal')->dateTime('d M Y H:i'),
                ])
                ->columns(2),
            Section::make('User & Subject')
                ->schema([
                    TextEntry::make('causer.nama_lengkap')->label('User'),
                    TextEntry::make('causer.email')->label('Email')->visible(fn (Activity $record) => filled($record->causer?->email)),
                    TextEntry::make('subject_type')
                        ->label('Subject')
                        ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
                    TextEntry::make('subject_id')->label('Subject ID'),
                ])
                ->columns(2),
            Section::make('Meta')
                ->schema([
                    KeyValueEntry::make('properties')
                        ->label('Properties')
                        ->state(function (Activity $record): array {
                            $props = $record->properties?->toArray() ?? [];

                            return collect($props)
                                ->map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                                ->all();
                        }),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAudits::route('/'),
            'view' => ViewAudit::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->role?->name === 'SUPER ADMIN';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('log_name', 'filament-admin');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->role?->name === 'SUPER ADMIN';
    }
}
