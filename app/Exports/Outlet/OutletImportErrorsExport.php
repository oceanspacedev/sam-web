<?php

namespace App\Exports\Outlet;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OutletImportErrorsExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<int, array{message:string,columns:array<string,?string>}>  $rows
     */
    public function __construct(
        private array $rows,
        private string $mode
    ) {}

    public function sheets(): array
    {
        return [
            new OutletImportErrorsSummarySheet($this->rows, $this->mode),
            new OutletHierarchySheet,
        ];
    }
}
