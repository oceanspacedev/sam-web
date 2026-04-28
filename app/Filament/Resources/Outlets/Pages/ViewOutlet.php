<?php

namespace App\Filament\Resources\Outlets\Pages;

use App\Filament\Resources\Outlets\OutletResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOutlet extends ViewRecord
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            \Filament\Actions\Action::make('resetLocation')
                ->label('Reset Lokasi')
                ->icon('heroicon-o-map-pin')
                ->requiresConfirmation()
                ->color('warning')
                ->authorize(fn (): bool => \Illuminate\Support\Facades\Gate::allows('resetLocation', $this->getRecord()))
                ->action(function () {
                    $record = $this->getRecord();
                    try {
                        $normalizedCount = OutletResource::enforceResetLimits(
                            $record->last_reset_at,
                            (int) $record->reset_count_yearly,
                            'Reset lokasi outlet',
                            $record->kode_outlet
                        );

                        $beforeArchive = $record->changeArchiveSnapshot();
                        $record->update([
                            'latlong' => null,
                            'alamat_outlet' => '-',
                            'poto_shop_sign' => null,
                            'poto_depan' => null,
                            'poto_kiri' => null,
                            'poto_kanan' => null,
                            'video' => null,
                            'last_reset_at' => now(),
                            'reset_count_yearly' => $normalizedCount + 1,
                        ]);
                        $record->refresh();
                        $record->recordChangeArchive(
                            \App\Models\OutletChangeArchive::ACTION_RESET_LOCATION,
                            \Illuminate\Support\Facades\Auth::user(),
                            $beforeArchive,
                            $record->changeArchiveSnapshot()
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil')
                            ->body('Lokasi outlet dan data pendukung berhasil direset.')
                            ->success()
                            ->send();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Gagal reset lokasi')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('reset')
                ->label('Reset Data')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->color('danger')
                ->authorize(fn (): bool => \Illuminate\Support\Facades\Gate::allows('reset', $this->getRecord()))
                ->action(function () {
                    $record = $this->getRecord();
                    try {
                        $normalizedCount = OutletResource::enforceResetLimits(
                            $record->last_reset_at,
                            (int) $record->reset_count_yearly,
                            'Reset data outlet',
                            $record->kode_outlet
                        );

                        $beforeArchive = $record->changeArchiveSnapshot();
                        $record->update([
                            'nama_pemilik_outlet' => null,
                            'nomer_tlp_outlet' => null,
                            'alamat_outlet' => '-',
                            'latlong' => null,
                            'poto_shop_sign' => null,
                            'poto_depan' => null,
                            'poto_kiri' => null,
                            'poto_kanan' => null,
                            'video' => null,
                            'last_reset_at' => now(),
                            'reset_count_yearly' => $normalizedCount + 1,
                        ]);
                        $record->refresh();
                        $record->recordChangeArchive(
                            \App\Models\OutletChangeArchive::ACTION_RESET_DATA,
                            \Illuminate\Support\Facades\Auth::user(),
                            $beforeArchive,
                            $record->changeArchiveSnapshot()
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil')
                            ->body('Data outlet berhasil direset.')
                            ->success()
                            ->send();
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Gagal reset data')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
