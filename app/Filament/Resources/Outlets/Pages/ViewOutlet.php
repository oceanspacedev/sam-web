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
                ->action(function () {
                    $record = $this->getRecord();
                    try {
                        $normalizedCount = OutletResource::enforceResetLimits(
                            $record->last_reset_at,
                            (int) $record->reset_count_yearly,
                            'Reset lokasi outlet',
                            $record->kode_outlet
                        );

                        $record->update([
                            'latlong' => null,
                            'last_reset_at' => now(),
                            'reset_count_yearly' => $normalizedCount + 1,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil')
                            ->body('Lokasi outlet berhasil direset.')
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
                ->action(function () {
                    $record = $this->getRecord();
                    try {
                        $normalizedCount = OutletResource::enforceResetLimits(
                            $record->last_reset_at,
                            (int) $record->reset_count_yearly,
                            'Reset data outlet',
                            $record->kode_outlet
                        );

                        if ($record->poto_shop_sign) {
                            \Illuminate\Support\Facades\Storage::disk(\App\Support\StorageDisk::default())->delete($record->poto_shop_sign);
                        }
                        if ($record->poto_depan) {
                            \Illuminate\Support\Facades\Storage::disk(\App\Support\StorageDisk::default())->delete($record->poto_depan);
                        }
                        if ($record->poto_kiri) {
                            \Illuminate\Support\Facades\Storage::disk(\App\Support\StorageDisk::default())->delete($record->poto_kiri);
                        }
                        if ($record->poto_kanan) {
                            \Illuminate\Support\Facades\Storage::disk(\App\Support\StorageDisk::default())->delete($record->poto_kanan);
                        }
                        if ($record->poto_ktp) {
                            \Illuminate\Support\Facades\Storage::disk(\App\Support\StorageDisk::default())->delete($record->poto_ktp);
                        }
                        if ($record->video) {
                            \Illuminate\Support\Facades\Storage::disk(\App\Support\StorageDisk::default())->delete($record->video);
                        }

                        $record->update([
                            'nama_pemilik_outlet' => null,
                            'nomer_tlp_outlet' => null,
                            'poto_shop_sign' => null,
                            'poto_depan' => null,
                            'poto_kiri' => null,
                            'poto_kanan' => null,
                            'poto_ktp' => null,
                            'video' => null,
                            'last_reset_at' => now(),
                            'reset_count_yearly' => $normalizedCount + 1,
                        ]);

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
