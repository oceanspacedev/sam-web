<?php

namespace App\Exports;

use App\Exports\Templates\OutletCreatedTemplate;
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
            'create_new' => [new OutletCreatedTemplate],
            'update_cluster', 'update' => [new OutletUpdatedTemplate],
            // For upsert or unspecified, provide both for clarity
            default => [new OutletUpdatedTemplate, new OutletCreatedTemplate],
        };
    }
}
