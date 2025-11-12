<?php

namespace App\Filament\Resources\Outlets\Pages;

use App\Filament\Exports\OutletExporter;
use App\Filament\Resources\Outlets\OutletResource;
use App\Imports\OutletImport;
use App\Models\Outlet;
use App\Support\StorageDisk;
use App\Support\StoragePathResolver;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        if (Gate::allows('exportAll', Outlet::class)) {
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
                ->schema([
                    ToggleButtons::make('mode')
                        ->label('Mode Import')
                        ->inline()
                        ->live()
                        ->options([
                            'create_new' => 'Created',
                            'update_cluster' => 'Update',
                        ])
                        ->default('create_new')
                        ->required(),
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
                                ->url(fn (callable $get) => '/outlet/export/template?mode='.urlencode((string) $get('mode'))),
                        ]),
                ])
                ->modalWidth('md')
                ->modalHeading('Import Data Outlet')
                ->button('Import')
                ->action(function (array $data) {
                    $disk = StorageDisk::default();
                    $relativePath = $data['file'];
                    [$fullPath, $temporaryPath] = StoragePathResolver::resolveForLocalAccess($disk, $relativePath);

                    try {
                        Excel::import(new OutletImport($data['mode']), $fullPath);
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
