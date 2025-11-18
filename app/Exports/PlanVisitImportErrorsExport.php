<?php

namespace App\Exports;

use App\Exports\Templates\PlanVisitTemplate;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class PlanVisitImportErrorsExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<int, array{message:string,columns:array<string,?string>}>  $rows
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
            new PlanVisitTemplate($this->scheduleScope),
        ];
    }
}

class PlanVisitImportErrorsSummarySheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{message:string,columns:array<string,?string>}>  $rows
     */
    public function __construct(
        private array $rows,
        private string $scheduleScope = 'daily'
    ) {
        if (! in_array($this->scheduleScope, ['daily', 'weekly'], true)) {
            $this->scheduleScope = 'daily';
        }
    }

    public function collection(): Collection
    {
        $headings = $this->columnHeadings();

        return collect($this->rows)->map(function (array $row) use ($headings): array {
            $columns = $row['columns'] ?? [];

            $exportRow = [
                'message' => $row['message'],
            ];

            foreach ($headings as $column) {
                $exportRow[$column] = $columns[$column] ?? '-';
            }

            return $exportRow;
        });
    }

    public function headings(): array
    {
        return array_merge([
            'Pesan',
        ], $this->columnHeadings());
    }

    public function title(): string
    {
        return 'Ringkasan Error';
    }

    /**
     * @return array<int, string>
     */
    private function columnHeadings(): array
    {
        return (new PlanVisitTemplate($this->scheduleScope))->headings();
    }
}
