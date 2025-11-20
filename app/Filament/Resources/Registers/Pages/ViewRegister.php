<?php

namespace App\Filament\Resources\Registers\Pages;

use App\Filament\Resources\Registers\RegisterResource;
use App\Models\Register;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ViewRegister extends ViewRecord
{
    protected static string $resource = RegisterResource::class;

    public function getTitle(): string
    {
        /** @var Register $record */
        $record = $this->getRecord();

        return 'Detail Register: ' . $record->nama_outlet;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('confirm')
                ->label('Confirm')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn($record) => $record->status === 'PENDING' && Gate::allows('confirm', $record))
                ->form([
                    TextInput::make('kode_outlet')
                        ->regex('/^[0-9]+$/')
                        ->helperText('Kode outlet tidak boleh mengandung spasi')
                        ->required(),
                    TextInput::make('limit')
                        ->numeric()
                        ->required(),
                ])
                ->action(function ($record, $data): void {
                    /** @var User|null $authUser */
                    $authUser = Auth::user();

                    $record->update([
                        'kode_outlet' => $data['kode_outlet'],
                        'limit' => $data['limit'],
                        'confirmed_at' => Carbon::now(),
                        'confirmed_by' => $authUser?->nama_lengkap,
                        'status' => 'CONFIRMED',
                    ]);

                    Notification::make()
                        ->title($record->nama_outlet . ' Confirm')
                        ->success()
                        ->send();
                }),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn($record) => $record->status === 'CONFIRMED' && Gate::allows('approve', $record))
                ->action(function ($record, $data): void {
                    /** @var User|null $authUser */
                    $authUser = Auth::user();

                    $record->update([
                        'approved_at' => Carbon::now(),
                        'approved_by' => $authUser?->nama_lengkap,
                        'status' => 'APPROVED',
                    ]);

                    Notification::make()
                        ->title($record->nama_outlet . ' Approved')
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn($record) => $record->status !== 'REJECTED' && $record->status !== 'APPROVED' && Gate::allows('reject', $record))
                ->form([
                    Textarea::make('alasan')
                        ->required(),
                ])
                ->action(function ($record, $data): void {
                    /** @var User|null $authUser */
                    $authUser = Auth::user();

                    $record->update([
                        'confirmed_at' => Carbon::now(),
                        'confirmed_by' => $authUser?->name,
                        'status' => 'REJECTED',
                        'keterangan' => $data['alasan'],
                    ]);
                    Notification::make()
                        ->title($record->nama_outlet . ' Rejected')
                        ->success()
                        ->send();
                }),
        ];
    }
}
