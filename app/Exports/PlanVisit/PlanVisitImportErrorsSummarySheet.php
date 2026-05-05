<?php

namespace App\Exports\PlanVisit;

use App\Exports\Concerns\PreservesTextColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PlanVisitImportErrorsSummarySheet implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithTitle
{
    use PreservesTextColumns;

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

    public function collection(): Collection
    {
        $headings = $this->columnHeadings();

        return collect($this->rows)->map(function (array $row) use ($headings): array {
            $columns = $row['columns'] ?? [];

            $exportRow = [
                'message' => $row['message'],
            ];

            foreach ($headings as $column) {
                $exportRow[$column] = $columns[$column] ?? null;
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
        return (new PlanVisitSheet($this->scheduleScope))->headings();
    }

    protected function textColumns(): array
    {
        return ['C'];
    }
}
