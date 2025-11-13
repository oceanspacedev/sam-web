<?php

namespace App\Filament\Resources\PlanVisits\Pages;

use App\Filament\Exports\PlanVisitExporter;
use App\Filament\Resources\PlanVisits\PlanVisitResource;
use App\Imports\PlanVisitImport;
use App\Jobs\CleanupUploadedImportFile;
use App\Jobs\Exports\GeneratePlanVisitTemplate;
use App\Jobs\SendImportNotification;
use App\Models\PlanVisit;
use App\Support\StorageDisk;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ListPlanVisits extends ListRecords
{
    protected static string $resource = PlanVisitResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            CreateAction::make(),
        ];

        // Check if the user is authorized to export
        if (Gate::allows('export', PlanVisit::class)) {
            $actions[] = ExportAction::make()
                ->exporter(PlanVisitExporter::class)
                ->label('Export')
                ->color('success')
                ->icon('heroicon-o-document-arrow-down');
        }

        // Import using Maatwebsite/Excel with template download hint
        if (Gate::allows('create', PlanVisit::class)) {
            $actions[] = Action::make('import')
                ->label('Import')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->slideOver()
                ->schema([
                    FileUpload::make('file')
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
                        ->required()
                        ->hintActions([
                            Action::make('download_template')
                                ->label('Download Template')
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

                                    GeneratePlanVisitTemplate::dispatch($userId);

                                    Notification::make()
                                        ->title('Template sedang diproses')
                                        ->body('Mulai menyiapkan template import plan visit, proses akan berjalan di belakang layar.')
                                        ->success()
                                        ->send();
                                }),
                        ]),
                ])
                ->modalWidth('md')
                ->modalHeading('Import Data Plan Visit')
                ->button('Import')
                ->action(function (array $data) {
                    $disk = StorageDisk::default();
                    $relativePath = ltrim($data['file'] ?? '', '/');

                    if ($relativePath === '') {
                        Notification::make()
                            ->title('Import gagal')
                            ->body('Berkas tidak ditemukan. Silakan coba unggah ulang dan jalankan import kembali.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $userId = Auth::id();

                    try {
                        $pendingDispatch = Excel::queueImport(new PlanVisitImport, $relativePath, $disk);

                        if ($pendingDispatch) {
                            $jobs = [
                                new CleanupUploadedImportFile($disk, $relativePath),
                            ];

                            if ($userId) {
                                $jobs[] = new SendImportNotification(
                                    $userId,
                                    'Import Plan Visit',
                                    'Import plan visit berhasil diproses.'
                                );
                            }

                            $pendingDispatch->chain($jobs);
                        }

                        Notification::make()
                            ->title('Import sedang diproses')
                            ->body('Kami akan memproses data di latar belakang dan memberi tahu jika terjadi kegagalan melalui log queue.')
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
                                'Import Plan Visit',
                                'Import plan visit gagal diproses. Silakan cek log queue.',
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
