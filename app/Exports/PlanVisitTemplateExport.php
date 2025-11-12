<?php

namespace App\Exports;

use App\Exports\Templates\OutletMasterTemplate;
use App\Exports\Templates\PlanVisitTemplate;
use App\Exports\Templates\UserMasterTemplate;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PlanVisitTemplateExport implements ShouldAutoSize, WithMultipleSheets
{
    use Exportable;

    public function __construct() {}

    public function sheets(): array
    {
        return [
            new PlanVisitTemplate,
            new UserMasterTemplate,
            new OutletMasterTemplate,
        ];
    }
}
