<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
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
            $actions[] = Actions\Action::make('export')
                ->color('success')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(route('user.export'));
        }

        // Check if the user is authorized to import
        // if (Gate::allows('import', User::class)) {
        //     $actions[] = Actions\Action::make('import')
        //         ->color('success')
        //         ->icon('heroicon-o-arrow-down-tray')
        //         ->form([
        //             FileUpload::make('file')
        //                 ->required()
        //                 ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
        //                 ->hintActions([
        //                     FormAction::make('download_template')
        //                         ->label('Download Template')
        //                         ->url(route('user.template')),
        //                 ]),
        //         ])
        //         ->modalWidth('md')
        //         ->modalHeading('Import Data User')
        //         ->modalButton('Import')
        //         ->action(function (array $data) {
        //             return redirect()->route('visit.export', [
        //                 'file' => $data['file'],
        //             ]);
        //         });
        // }

        return $actions;
    }
}
