<?php

namespace App\Filament\Resources\Outlets\RelationManagers;

use App\Models\Outlet;
use App\Models\OutletChangeArchive;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
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
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        OutletChangeArchive::ACTION_RESET_DATA => 'Reset Data',
                        OutletChangeArchive::ACTION_RESET_LOCATION => 'Reset Lokasi',
                        OutletChangeArchive::ACTION_UPDATE => 'Edit Manual',
                        OutletChangeArchive::ACTION_RESTORE => 'Restore',
                        default => (string) $state,
                    }),
                TextColumn::make('actor_name')
                    ->label('Oleh')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('changed_fields')
                    ->label('Field Berubah')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->wrap(),
                TextColumn::make('restored_at')
                    ->label('Direstore')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                TextColumn::make('restoredBy.nama_lengkap')
                    ->label('Restore Oleh')
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->deferLoading()
            ->recordActions([
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restore data outlet dari arsip?')
                    ->modalDescription('Data outlet akan dikembalikan ke nilai lama yang tersimpan di arsip ini.')
                    ->visible(fn (): bool => Gate::allows('Reset:Outlet') || Gate::allows('Update:Outlet'))
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
}
