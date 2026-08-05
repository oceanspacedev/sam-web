<?php

namespace App\Exports\User;

use App\Exports\Outlet\AllOutletsSheet;
use App\Exports\PlanVisit\PlanVisitDailyThisMonthSheet;
use App\Exports\PlanVisit\PlanVisitWeeklyThisMonthSheet;
use App\Exports\Register\AllRegistersSheet;
use App\Exports\Visit\UnvisitedOutletsSheet;
use App\Exports\Visit\VisitsThisMonthSheet;
use App\Models\User;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UserMonthlyOutletsExport implements WithMultipleSheets
{
    public function __construct(
        public User $user,
        public ?int $month = null,
        public ?int $year = null,
    ) {
        $this->month = $this->month ?? (int) now()->format('m');
        $this->year = $this->year ?? (int) now()->format('Y');
    }

    public function sheets(): array
    {
        return [
            new AllOutletsSheet($this->user, $this->month, $this->year),
            new AllRegistersSheet($this->user, $this->month, $this->year),
            new PlanVisitDailyThisMonthSheet($this->user, $this->month, $this->year),
            new PlanVisitWeeklyThisMonthSheet($this->user, $this->month, $this->year),
            new VisitsThisMonthSheet($this->user, $this->month, $this->year),
            new UnvisitedOutletsSheet($this->user, $this->month, $this->year),
        ];
    }
}
