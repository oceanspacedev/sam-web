<?php

namespace App\Filament\Resources\Clusters\Pages;

use App\Filament\Resources\Clusters\ClusterResource;
use Filament\Actions\CreateAction;
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
