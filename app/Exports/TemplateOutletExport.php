<?php

namespace App\Exports;

use App\Exports\Templates\OutletCreatedTemplate;
use App\Exports\Templates\OutletHierarchyMasterTemplate;
use App\Exports\Templates\OutletUpdatedTemplate;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TemplateOutletExport implements ShouldAutoSize, WithMultipleSheets
{
    use Exportable;

    public function __construct(private ?string $mode = null) {}

    public function sheets(): array
    {
        // Adjust sheets based on requested mode
        return match ($this->mode) {
            'create' => [
                new OutletCreatedTemplate,
                new OutletHierarchyMasterTemplate,
            ],
            'update' => [
                new OutletUpdatedTemplate,
                new OutletHierarchyMasterTemplate,
            ],
            // For upsert or unspecified, provide both templates plus master data for convenience
            default => [
                new OutletUpdatedTemplate,
                new OutletCreatedTemplate,
                new OutletHierarchyMasterTemplate,
            ],
        };
    }
}
