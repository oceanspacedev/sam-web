<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVisit extends ViewRecord
{
    protected static string $resource = VisitResource::class;

    public function getTitle(): string
    {
        /** @var Visit $record */
        $record = $this->getRecord();

        return 'Detail Visit: '.$record->outlet->nama_outlet ?? 'Visit';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
