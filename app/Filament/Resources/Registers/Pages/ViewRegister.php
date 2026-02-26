<?php

namespace App\Filament\Resources\Registers\Pages;

use App\Filament\Resources\Registers\RegisterResource;
use App\Models\Register;
use App\Models\User;
use App\Services\FilenameGeneratorService;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ViewRegister extends ViewRecord
{
    protected static string $resource = RegisterResource::class;

    public function getTitle(): string
    {
        /** @var Register $record */
        $record = $this->getRecord();

        return 'Detail Register: '.$record->nama_outlet;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('update_ktp')
                ->label('Update KTP')
                ->icon('heroicon-o-identification')
                ->color('primary')
                ->visible(fn ($record) => strtoupper((string) $record->type) === 'LEAD' && Gate::allows('Upgrade:Register'))
                ->form([
                    TextInput::make('ktp_outlet')
                        ->label('Nomor KTP Outlet')
                        ->required()
                        ->default(fn ($record) => $record->ktp_outlet)
                        ->maxLength(255),
                    FileUpload::make('poto_ktp')
                        ->label('Foto KTP')
                        ->image()
                        ->disk(StorageDisk::default())
                        ->required()
                        ->getUploadedFileNameForStorageUsing(function (UploadedFile $file, $get) {
                            $userId = Auth::id();
                            $filenameGenerator = new FilenameGeneratorService;

                            return $filenameGenerator->generate($file, 'register-ktp', $userId);
                        }),
                ])
                ->action(function ($record, $data): void {
                    $record->update([
                        'ktp_outlet' => $data['ktp_outlet'],
                        'poto_ktp' => $data['poto_ktp'],
                        'type' => 'NOO',
                    ]);

                    Notification::make()
                        ->title('KTP Updated')
                        ->success()
                        ->send();
                }),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => ($record->status === 'PENDING' || $record->status === 'CONFIRMED') && Gate::allows('Approve:Register', $record) && strtoupper((string) $record->type) !== 'LEAD')
                ->form([
                    TextInput::make('kode_outlet')
                        ->regex('/^\S+$/')
                        ->helperText('Kode outlet tidak boleh mengandung spasi')
                        ->default(fn ($record) => $record->kode_outlet)
                        ->required()
                        ->rules(function ($record) {
                            return [
                                function (string $attribute, $value, \Closure $fail) use ($record) {
                                    $exists = \App\Models\Outlet::where('kode_outlet', $value)
                                        ->where('divisi_id', $record->divisi_id)
                                        ->exists();

                                    if ($exists) {
                                        $fail("Kode outlet {$value} sudah digunakan.");
                                    }
                                },
                            ];
                        }),
                    TextInput::make('limit')
                        ->numeric()
                        ->default(fn ($record) => $record->limit)
                        ->required(),
                ])
                ->action(function ($record, $data): void {
                    /** @var User|null $authUser */
                    $authUser = Auth::user();

                    $record->update([
                        'kode_outlet' => $data['kode_outlet'],
                        'limit' => $data['limit'],
                        'confirmed_at' => $record->confirmed_at ?? Carbon::now(),
                        'confirmed_by_id' => $record->confirmed_by_id ?? $authUser?->id,
                        'approved_at' => Carbon::now(),
                        'approved_by_id' => $authUser?->id,
                        'status' => 'APPROVED',
                    ]);

                    Notification::make()
                        ->title($record->nama_outlet.' Approved')
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record) => $record->status !== 'REJECTED' && $record->status !== 'APPROVED' && Gate::allows('Reject:Register', $record) && strtoupper((string) $record->type) !== 'LEAD')
                ->form([
                    Textarea::make('alasan')
                        ->required(),
                ])
                ->action(function ($record, $data): void {
                    /** @var User|null $authUser */
                    $authUser = Auth::user();

                    $record->update([
                        'rejected_at' => Carbon::now(),
                        'rejected_by_id' => $authUser?->id,
                        'status' => 'REJECTED',
                        'keterangan' => $data['alasan'],
                    ]);
                    Notification::make()
                        ->title($record->nama_outlet.' Rejected')
                        ->success()
                        ->send();
                }),
        ];
    }
}
