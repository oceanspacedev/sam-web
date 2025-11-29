<?php

namespace App\Exports\PlanVisit;

use App\Exports\Outlet\OutletMasterSheet;
use App\Exports\User\UserMasterSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PlanVisitTemplateExport implements ShouldAutoSize, WithMultipleSheets
{
    use Exportable;

    public function __construct(private string $scheduleScope = 'daily')
    {
        if (! in_array($this->scheduleScope, ['daily', 'weekly'], true)) {
            $this->scheduleScope = 'daily';
        }
    }

    public function sheets(): array
    {
        return [
            new PlanVisitSheet($this->scheduleScope),
            new UserMasterSheet,
            new OutletMasterSheet,
        ];
    }
}
