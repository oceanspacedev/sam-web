<?php

namespace App\Filament\Resources\PlanVisits\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Throwable;
use App\Filament\Exports\PlanVisitExporter;
use App\Filament\Resources\PlanVisits\PlanVisitResource;
use App\Imports\PlanVisitImport;
use App\Models\PlanVisit;
use App\Support\StorageDisk;
use App\Support\StoragePathResolver;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

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
                                ->url('/planvisit/export/template'),
                        ]),
                ])
                ->modalWidth('md')
                ->modalHeading('Import Data Plan Visit')
                ->button('Import')
                ->action(function (array $data) {
                    $disk = StorageDisk::default();
                    $relativePath = $data['file'];
                    [$fullPath, $temporaryPath] = StoragePathResolver::resolveForLocalAccess($disk, $relativePath);

                    try {
                        Excel::import(new PlanVisitImport, $fullPath);
                        if ($relativePath) {
                            Storage::disk($disk)->delete($relativePath);
                        }
                        Notification::make()
                            ->title('Import berhasil')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Import gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        StoragePathResolver::cleanupTemporaryPath($temporaryPath ?? null);
                    }
                });
        }

        return $actions;
    }
}
