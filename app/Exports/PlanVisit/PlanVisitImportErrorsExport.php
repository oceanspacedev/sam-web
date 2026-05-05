<?php

namespace App\Exports\PlanVisit;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PlanVisitImportErrorsExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<int, array{row:int,message:string,columns:array<string,?string>}>  $rows
     */
    public function __construct(
        private array $rows,
        private string $scheduleScope = 'daily'
    ) {
        if (! in_array($this->scheduleScope, ['daily', 'weekly'], true)) {
            $this->scheduleScope = 'daily';
        }
    }

    public function sheets(): array
    {
        return [
            new PlanVisitImportErrorsSummarySheet($this->rows, $this->scheduleScope),
            new PlanVisitSheet($this->scheduleScope),
        ];
    }
}
