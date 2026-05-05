<?php

namespace App\Exports\Outlet;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OutletTemplateExport implements ShouldAutoSize, WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private ?string $mode = null,
        private ?int $userId = null,
    ) {}

    public function sheets(): array
    {
        return match ($this->mode) {
            'create' => [
                new OutletCreatedSheet,
                new OutletHierarchySheet($this->userId),
            ],
            'update' => [
                new OutletUpdatedSheet($this->userId),
                new OutletHierarchySheet($this->userId),
            ],
            default => [
                new OutletUpdatedSheet($this->userId),
                new OutletCreatedSheet,
                new OutletHierarchySheet($this->userId),
            ],
        };
    }
}
