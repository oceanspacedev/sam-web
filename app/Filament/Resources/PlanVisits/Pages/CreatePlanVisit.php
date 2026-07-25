<?php

namespace App\Filament\Resources\PlanVisits\Pages;

use App\Filament\Resources\PlanVisits\PlanVisitResource;
use App\Models\PlanVisit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePlanVisit extends CreateRecord
{
    protected static string $resource = PlanVisitResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PlanVisitResource::prepareSchedulePayload($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return PlanVisit::createOrRestoreForPeriod($data);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Plan visit sudah ada')
                ->body('Plan visit untuk user, target, dan periode tersebut sudah terdaftar.')
                ->danger()
                ->send();

            // period_start is derived, not a form field, so surface the error on the visible date input.
            throw ValidationException::withMessages([
                'data.tanggal_visit' => $exception->validator->errors()->first('period_start'),
            ]);
        }
    }
}
