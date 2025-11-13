<?php

namespace App\Exports;

use App\Exports\Templates\OutletHierarchyMasterTemplate;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class OutletImportErrorsExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<int, array{message:string,columns:array<string,?string>}>  $rows
     */
    public function __construct(private array $rows) {}

    public function sheets(): array
    {
        return [
            new OutletImportErrorsSummarySheet($this->rows),
            new OutletHierarchyMasterTemplate,
        ];
    }
}

class OutletImportErrorsSummarySheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{message:string,columns:array<string,?string>}>  $rows
     */
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows)->map(function (array $row): array {
            $columns = $row['columns'] ?? [];

            $exportRow = [
                'message' => $row['message'],
            ];

            foreach (self::COLUMN_LABELS as $key => $label) {
                $exportRow[$key] = $columns[$key] ?? '-';
            }

            return $exportRow;
        });
    }

    public function headings(): array
    {
        return array_merge([
            'Pesan',
        ], array_values(self::COLUMN_LABELS));
    }

    public function title(): string
    {
        return 'Ringkasan Error';
    }

    private const COLUMN_LABELS = [
        'badan_usaha' => 'Badan Usaha',
        'divisi' => 'Divisi',
        'region' => 'Region',
        'cluster' => 'Cluster',
        'kode_outlet' => 'Kode Outlet',
        'nama_outlet' => 'Nama Outlet',
        'alamat_outlet' => 'Alamat Outlet',
        'distric' => 'Distric',
        'limit' => 'Limit',
    ];
}
