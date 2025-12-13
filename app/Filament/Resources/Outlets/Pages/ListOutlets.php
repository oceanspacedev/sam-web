<?php

namespace App\Filament\Resources\Outlets\Pages;

use App\Filament\Exports\OutletExporter;
use App\Filament\Resources\Outlets\OutletResource;
use App\Imports\OutletImport;
use App\Jobs\CleanupUploadedImportFile;
use App\Jobs\Exports\GenerateOutletTemplate;
use App\Jobs\SendImportNotification;
use App\Models\Outlet;
use App\Support\StorageDisk;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ListOutlets extends ListRecords
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make(),
        ];

        // Check if the user is authorized to export
        if (Gate::allows('export', Outlet::class)) {
            $actions[] = ExportAction::make()
                ->exporter(OutletExporter::class)
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->label('Export');
        }

        // Import using Maatwebsite/Excel - single action with inline button group for mode
        if (Gate::allows('create', Outlet::class)) {
            $actions[] = Action::make('import')
                ->label('Import')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->slideOver()
                ->schema([
                    ToggleButtons::make('mode')
                        ->label('Mode Import')
                        ->inline()
                        ->live()
                        ->options([
                            'create' => 'Create',
                            'update' => 'Update',
                        ])
                        ->required(),
                    FileUpload::make('file_create')
                        ->label('File (.xlsx/.csv)')
                        ->disk(StorageDisk::default())
                        ->directory('import')
                        ->preserveFilenames()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'application/csv',
                        ])
                        ->visible(fn (callable $get) => $get('mode') === 'create')
                        ->required(fn (callable $get) => $get('mode') === 'create')
                        ->hintActions([
                            Action::make('download_template')
                                ->label('Download Template Create')
                                ->action(function () {
                                    $userId = Auth::id();

                                    if (! $userId) {
                                        Notification::make()
                                            ->title('Permintaan template gagal')
                                            ->body('Sesi Anda kedaluwarsa. Silakan login ulang lalu coba kembali.')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    GenerateOutletTemplate::dispatch($userId, 'create');

                                    Notification::make()
                                        ->title('Template sedang diproses')
                                        ->body('Mulai menyiapkan template import create outlet, proses akan berjalan di belakang layar.')
                                        ->success()
                                        ->send();
                                }),
                        ]),
                    FileUpload::make('file_update')
                        ->label('File (.xlsx/.csv)')
                        ->disk(StorageDisk::default())
                        ->directory('import')
                        ->preserveFilenames()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'application/csv',
                        ])
                        ->visible(fn (callable $get) => $get('mode') === 'update')
                        ->required(fn (callable $get) => $get('mode') === 'update')
                        ->hintActions([
                            Action::make('download_template')
                                ->label('Download Template Update')
                                ->action(function () {
                                    $userId = Auth::id();

                                    if (! $userId) {
                                        Notification::make()
                                            ->title('Permintaan template gagal')
                                            ->body('Sesi Anda kedaluwarsa. Silakan login ulang lalu coba kembali.')
                                            ->danger()
                                            ->send();

                                        return;
                                    }

                                    GenerateOutletTemplate::dispatch($userId, 'update');

                                    Notification::make()
                                        ->title('Template sedang diproses')
                                        ->body('Mulai menyiapkan template import update outlet, proses akan berjalan di belakang layar.')
                                        ->success()
                                        ->send();
                                }),
                        ]),
                ])
                ->modalWidth('md')
                ->modalHeading('Import Data Outlet')
                ->button('Import')
                ->action(function (array $data) {
                    $disk = StorageDisk::default();
                    $mode = $data['mode'] ?? 'create';
                    $relativePath = $mode === 'create'
                        ? ltrim((string) ($data['file_create'] ?? ''), '/')
                        : ltrim((string) ($data['file_update'] ?? ''), '/');

                    if ($relativePath === '') {
                        Notification::make()
                            ->title('Import gagal')
                            ->body('Berkas tidak ditemukan. Silakan unggah ulang dan jalankan import kembali.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $userId = Auth::id();

                    try {
                        $pendingDispatch = Excel::queueImport(new OutletImport($mode, $userId), $relativePath, $disk);

                        if ($pendingDispatch) {
                            $jobs = [
                                new CleanupUploadedImportFile($disk, $relativePath),
                            ];

                            $pendingDispatch->chain($jobs);
                        }

                        Notification::make()
                            ->title('Import sedang diproses')
                            ->body('Mulai mengimport data outlet, proses akan berjalan di belakang layar.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Import gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        if ($userId) {
                            SendImportNotification::dispatch(
                                $userId,
                                'Import Data Outlet',
                                'Import data outlet (mode: '.strtoupper($mode).') gagal diproses. Silakan hubungi tim IT.',
                                false
                            );
                        }

                        report($e);
                    }
                });
        }

        return $actions;
    }
}
