<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Exports\UserExporter;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Check if the user is authorized to export
        if (Gate::allows('export', User::class)) {
            $actions[] = ExportAction::make()
                ->exporter(UserExporter::class)
                ->color('success')
                ->icon('heroicon-o-document-arrow-down')
                ->label('Export');
        }

        return $actions;
    }
}
