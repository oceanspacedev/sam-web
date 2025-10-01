<?php

namespace App\Filament\Resources\OutletResource\Pages;

use App\Filament\Exports\OutletExporter;
use App\Filament\Resources\OutletResource;
use App\Imports\OutletImport;
use App\Models\Outlet;
use App\Support\StorageDisk;
use App\Support\StoragePathResolver;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListOutlets extends ListRecords
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
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
            $actions[] = Actions\Action::make('import')
                ->label('Import')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
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
                            FormAction::make('download_template')
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
                    } catch (\Throwable $e) {
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

    public function getTabs(): array
    {
        $query = OutletResource::getEloquentQuery();

        return [
            'all' => Tab::make(),

            'MEMBER' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_member', '1'))
                ->badge($this->getStatusBadgeCount($query, 1))
                ->badgeColor('success'),

            'LEAD' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_member', '0'))
                ->badge($this->getStatusBadgeCount($query, 0))
                ->badgeColor('info'),
        ];
    }

    private function getStatusBadgeCount(Builder $query, ?string $status): int
    {
        if ($status === null) {
            return $query->clone()->count();
        }

        return $query->clone()->where('is_member', $status)->count();
    }
}
