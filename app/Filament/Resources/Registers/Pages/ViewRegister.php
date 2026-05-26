<?php

namespace App\Filament\Resources\Registers\Pages;

use App\Exceptions\Api\BadRequestException;
use App\Filament\Resources\Registers\RegisterResource;
use App\Models\Register;
use App\Models\User;
use App\Services\FilenameGeneratorService;
use App\Services\RegisterApprovalService;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
                        ->fetchFileInformation(false)
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
                ->slideOver()
                ->visible(fn ($record) => ($record->status === 'PENDING' || $record->status === 'CONFIRMED') && Gate::allows('Approve:Register', $record) && strtoupper((string) $record->type) !== 'LEAD')
                ->form([
                    Hidden::make('outlet_code_checked')
                        ->default(false)
                        ->dehydrated(false),
                    Hidden::make('checked_kode_outlet'),
                    Hidden::make('has_duplicate_outlet_code')
                        ->default(false)
                        ->dehydrated(false),
                    Hidden::make('duplicate_outlet_summary')
                        ->dehydrated(false),
                    Hidden::make('duplicate_suggestion_title')
                        ->dehydrated(false),
                    Hidden::make('duplicate_suggestion_body')
                        ->dehydrated(false),
                    Hidden::make('next_branch_code')
                        ->dehydrated(false),
                    TextInput::make('kode_outlet')
                        ->regex('/^\S+$/')
                        ->helperText('Isi kode outlet, lalu tekan tombol cek sebelum melanjutkan.')
                        ->default(fn ($record) => $record->kode_outlet)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set): void {
                            RegisterResource::resetOutletCodeCheck($set);
                        })
                        ->required()
                        ->suffixAction(
                            Action::make('check_outlet_code')
                                ->label('Cek')
                                ->icon('heroicon-o-magnifying-glass')
                                ->color('primary')
                                ->action(function (Get $get, Set $set, ?Register $record): void {
                                    RegisterResource::checkOutletCodeForApproval($get, $set, $record);
                                })
                        ),
                    Placeholder::make('duplicate_outlet_suggestion_preview')
                        ->label('Saran hasil cek')
                        ->content(fn (Get $get) => RegisterResource::duplicateOutletSuggestionPreview($get))
                        ->visible(fn (Get $get): bool => (bool) $get('outlet_code_checked') && (bool) $get('has_duplicate_outlet_code')),
                    TextInput::make('limit')
                        ->numeric()
                        ->default(fn ($record) => $record->limit)
                        ->visible(fn (Get $get): bool => (bool) $get('outlet_code_checked'))
                        ->required(),
                    ToggleButtons::make('duplicate_resolution')
                        ->label('Kode outlet sudah dipakai')
                        ->options([
                            RegisterApprovalService::DUPLICATE_BRANCH => 'Buat Cabang',
                            RegisterApprovalService::DUPLICATE_OVERRIDE => 'Override Outlet Lama',
                        ])
                        ->helperText('Buat Cabang membuat kode -CB1, -CB2, dan seterusnya. Override mengganti outlet lama dan menyimpan histori perubahan.')
                        ->inline()
                        ->visible(fn (Get $get): bool => (bool) $get('outlet_code_checked') && (bool) $get('has_duplicate_outlet_code'))
                        ->required(),
                ])
                ->action(function ($record, $data): void {
                    /** @var User|null $authUser */
                    $authUser = Auth::user();

                    RegisterResource::validateOutletCodeCheckBeforeApproval($record, $data);

                    $record->forceFill([
                        'kode_outlet' => $data['kode_outlet'],
                        'limit' => $data['limit'],
                        'confirmed_at' => $record->confirmed_at ?? Carbon::now(),
                        'confirmed_by_id' => $record->confirmed_by_id ?? $authUser?->id,
                        'status' => 'CONFIRMED',
                    ])->save();

                    try {
                        $result = app(RegisterApprovalService::class)->approve(
                            $record,
                            $authUser,
                            $data['duplicate_resolution'] ?? RegisterApprovalService::DUPLICATE_BRANCH
                        );
                    } catch (BadRequestException $exception) {
                        Notification::make()
                            ->title('Approval gagal')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        throw ValidationException::withMessages([
                            'kode_outlet' => $exception->getMessage(),
                        ]);
                    }

                    Notification::make()
                        ->title($result['register']->nama_outlet.' Approved')
                        ->body('Kode outlet: '.$result['final_kode_outlet'])
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
