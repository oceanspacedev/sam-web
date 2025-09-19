<?php

namespace App\Filament\Resources\PlanVisitResource\Pages;

use App\Filament\Exports\PlanVisitExporter;
use App\Filament\Resources\PlanVisitResource;
use App\Imports\PlanVisitImport;
use App\Models\PlanVisit;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class ListPlanVisits extends ListRecords
{
    protected static string $resource = PlanVisitResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
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
            $actions[] = Actions\Action::make('import')
                ->label('Import')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('File (.xlsx/.csv)')
                        ->disk('public')
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
                            FormAction::make('download_template')
                                ->label('Download Template')
                                ->url('/planvisit/export/template'),
                        ]),
                ])
                ->modalWidth('md')
                ->modalHeading('Import Data Plan Visit')
                ->modalButton('Import')
                ->action(function (array $data) {
                    $relativePath = $data['file'];
                    $fullPath = storage_path('app/public/'.ltrim($relativePath, '/'));

                    try {
                        Excel::import(new PlanVisitImport, $fullPath);
                        Notification::make()
                            ->title('Import berhasil')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Import gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                });
        }

        return $actions;
    }
}
