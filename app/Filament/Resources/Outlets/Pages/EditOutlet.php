<?php

namespace App\Filament\Resources\Outlets\Pages;

use App\Filament\Resources\Outlets\OutletResource;
use App\Models\OutletChangeArchive;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditOutlet extends EditRecord
{
    protected static string $resource = OutletResource::class;

    /**
     * @var array<string, mixed>
     */
    protected array $beforeArchive = [];

    protected function beforeSave(): void
    {
        $this->beforeArchive = $this->getRecord()->changeArchiveSnapshot();
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $record->refresh();
        $record->recordChangeArchive(
            OutletChangeArchive::ACTION_UPDATE,
            Auth::user(),
            $this->beforeArchive,
            $record->changeArchiveSnapshot()
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
