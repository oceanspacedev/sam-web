<?php

namespace App\Filament\Resources\Clusters\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Clusters\ClusterResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageClusters extends ManageRecords
{
    protected static string $resource = ClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
