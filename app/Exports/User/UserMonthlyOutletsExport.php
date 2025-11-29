<?php

namespace App\Exports\User;

use App\Exports\Outlet\AllOutletsSheet;
use App\Exports\Visit\UnvisitedOutletsSheet;
use App\Exports\Visit\VisitsThisMonthSheet;
use App\Models\User;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UserMonthlyOutletsExport implements WithMultipleSheets
{
    public function __construct(public User $user) {}

    public function sheets(): array
    {
        return [
            new AllOutletsSheet($this->user),
            new VisitsThisMonthSheet($this->user),
            new UnvisitedOutletsSheet($this->user),
        ];
    }
}
