<?php

namespace App\Exports\Outlet;

use App\Exports\Concerns\PreservesTextColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OutletImportErrorsSummarySheet implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithTitle
{
    use PreservesTextColumns;

    /**
     * @param  array<int, array{row:int,message:string,columns:array<string,?string>}>  $rows
     */
    public function __construct(
        private array $rows,
        private string $mode
    ) {}

    public function collection(): Collection
    {
        $columnHeadings = $this->columnHeadings();

        return collect($this->rows)->map(function (array $row) use ($columnHeadings): array {
            $columns = $row['columns'] ?? [];

            $exportRow = [
                'message' => $row['message'],
            ];

            foreach ($columnHeadings as $column) {
                $exportRow[$column] = $columns[$column] ?? null;
            }

            return $exportRow;
        });
    }

    public function headings(): array
    {
        $columnHeadings = $this->columnHeadings();

        return array_merge([
            'Pesan',
        ], $columnHeadings);
    }

    public function title(): string
    {
        return 'Ringkasan Error';
    }

    /**
     * @return array<string, string>
     */
    private function columnHeadings(): array
    {
        return match ($this->mode) {
            'create' => $this->createdTemplateHeadings(),
            'update' => $this->updatedTemplateHeadings(),
            default => $this->updatedTemplateHeadings(),
        };
    }

    private function createdTemplateHeadings(): array
    {
        return (new OutletCreatedSheet)->headings();
    }

    private function updatedTemplateHeadings(): array
    {
        return (new OutletUpdatedSheet)->headings();
    }

    protected function textColumns(): array
    {
        return match ($this->mode) {
            'create' => ['F'],
            default => ['F', 'Q'],
        };
    }
}
