<?php

namespace App\Exports;

use App\Exports\Sheets\AllOutletsSheet;
use App\Exports\Sheets\UnvisitedOutletsSheet;
use App\Exports\Sheets\VisitsThisMonthSheet;
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
