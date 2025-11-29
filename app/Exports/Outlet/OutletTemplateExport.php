<?php

namespace App\Exports\Outlet;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OutletTemplateExport implements ShouldAutoSize, WithMultipleSheets
{
    use Exportable;

    public function __construct(private ?string $mode = null) {}

    public function sheets(): array
    {
        return match ($this->mode) {
            'create' => [
                new OutletCreatedSheet,
                new OutletHierarchySheet,
            ],
            'update' => [
                new OutletUpdatedSheet,
                new OutletHierarchySheet,
            ],
            default => [
                new OutletUpdatedSheet,
                new OutletCreatedSheet,
                new OutletHierarchySheet,
            ],
        };
    }
}
