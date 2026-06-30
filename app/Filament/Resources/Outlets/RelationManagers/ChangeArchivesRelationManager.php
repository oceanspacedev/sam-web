<?php

namespace App\Filament\Resources\Outlets\RelationManagers;

use App\Models\Outlet;
use App\Models\OutletChangeArchive;
use App\Support\OutletChangeArchivePresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChangeArchivesRelationManager extends RelationManager
{
    protected static ?string $title = 'Riwayat Perubahan';

    protected static string $relationship = 'changeArchives';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (?string $state): string => OutletChangeArchivePresenter::actionColor($state))
                    ->formatStateUsing(fn (?string $state): string => OutletChangeArchivePresenter::actionLabel($state)),
                TextColumn::make('actor_name')
                    ->label('Oleh')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('changed_fields')
                    ->label('Perubahan')
                    ->formatStateUsing(fn ($state): string => OutletChangeArchivePresenter::summary(is_array($state) ? $state : null))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('restored_at')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?OutletChangeArchive $record): string => filled($record?->restored_at) ? 'gray' : 'success')
                    ->formatStateUsing(fn (?OutletChangeArchive $record): string => filled($record?->restored_at)
                        ? 'Direstore · '.$record->restored_at->format('d M Y H:i')
                        : 'Aktif'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->recordActions([
                Action::make('detail')
                    ->label('Lihat Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading('Detail Perubahan')
                    ->modalWidth(Width::Medium)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->fillForm(fn (OutletChangeArchive $record): array => [
                        'changes' => OutletChangeArchivePresenter::diffRows($record),
                    ])
                    ->disabledForm()
                    ->schema(self::detailSchema()),
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restore data outlet dari arsip?')
                    ->modalDescription('Data outlet akan dikembalikan ke nilai lama yang tersimpan di arsip ini.')
                    ->visible(fn (OutletChangeArchive $record): bool => blank($record->restored_at)
                        && (Gate::allows('Reset:Outlet') || Gate::allows('Update:Outlet')))
                    ->action(function (OutletChangeArchive $record): void {
                        $owner = $this->getOwnerRecord();

                        DB::transaction(function () use ($owner, $record): void {
                            $outlet = Outlet::query()
                                ->whereKey($owner->getKey())
                                ->lockForUpdate()
                                ->firstOrFail();

                            $archive = OutletChangeArchive::query()
                                ->whereKey($record->getKey())
                                ->where('outlet_id', $outlet->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $beforeArchive = $outlet->changeArchiveSnapshot();
                            $outlet->forceFill(Outlet::restorableValues($archive->old_values ?? []));
                            $outlet->save();
                            $outlet->refresh();

                            $outlet->recordChangeArchive(
                                OutletChangeArchive::ACTION_RESTORE,
                                Auth::user(),
                                $beforeArchive,
                                $outlet->changeArchiveSnapshot(),
                                null,
                                $archive
                            );

                            $archive->forceFill([
                                'restored_by_user_id' => Auth::id(),
                                'restored_at' => now(),
                            ])->save();
                        });

                        Notification::make()
                            ->title('Berhasil')
                            ->body('Data outlet berhasil direstore dari arsip.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected static function detailSchema(): array
    {
        return [
            TextEntry::make('created_at')
                ->label('Waktu')
                ->dateTime('d M Y H:i')
                ->columnSpanFull(),
            TextEntry::make('action')
                ->label('Aksi')
                ->badge()
                ->color(fn (?string $state): string => OutletChangeArchivePresenter::actionColor($state))
                ->formatStateUsing(fn (?string $state): string => OutletChangeArchivePresenter::actionLabel($state))
                ->columnSpanFull(),
            TextEntry::make('actor_name')
                ->label('Oleh')
                ->placeholder('-')
                ->columnSpanFull(),
            TextEntry::make('status_summary')
                ->label('Status')
                ->getStateUsing(fn (OutletChangeArchive $record): string => OutletChangeArchivePresenter::statusLabel($record))
                ->columnSpanFull(),
            Repeater::make('changes')
                ->label('Perubahan')
                ->table([
                    TableColumn::make('Field')
                        ->width('30%'),
                    TableColumn::make('Nilai Lama')
                        ->width('35%'),
                    TableColumn::make('Nilai Baru')
                        ->width('35%'),
                ])
                ->compact()
                ->schema([
                    TextInput::make('label'),
                    Textarea::make('old')
                        ->rows(2),
                    Textarea::make('new')
                        ->rows(2),
                ])
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->columnSpanFull(),
        ];
    }
}
