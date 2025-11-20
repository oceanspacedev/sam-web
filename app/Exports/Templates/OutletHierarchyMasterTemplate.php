<?php

namespace App\Exports\Templates;

use App\Models\Cluster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletHierarchyMasterTemplate implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    /**
     * @var array<int, array{0: string, 1: int, 2: int}>
     */
    private array $mergeInstructions = [];

    public function title(): string
    {
        return 'MasterHierarchy';
    }

    public function headings(): array
    {
        return [
            'badan_usaha',
            'divisi',
            'region',
            'cluster',
        ];
    }

    public function collection(): Collection
    {
        $query = Cluster::query()
            ->select([
                'clusters.name as cluster_name',
                'clusters.region_id',
                'clusters.divisi_id',
                'clusters.badanusaha_id',
                'regions.name as region_name',
                'divisions.name as divisi_name',
                'badan_usahas.name as badanusaha_name',
            ])
            ->leftJoin('regions', 'clusters.region_id', '=', 'regions.id')
            ->leftJoin('divisions', 'clusters.divisi_id', '=', 'divisions.id')
            ->leftJoin('badan_usahas', 'clusters.badanusaha_id', '=', 'badan_usahas.id')
            ->orderBy('badanusaha_name')
            ->orderBy('divisi_name')
            ->orderBy('region_name')
            ->orderBy('cluster_name');

        if (Auth::check()) {
            $user = Auth::user();
            $role = $user->role;
            $scopeLevel = $role->organizational_scope_level ?? 'cluster';

            if ($scopeLevel !== 'all') {
                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();
                $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                if (! empty($badanUsahaIds)) {
                    $query->whereIn('clusters.badanusaha_id', $badanUsahaIds);
                }
                if (! empty($divisiIds)) {
                    $query->whereIn('clusters.divisi_id', $divisiIds);
                }
                if (! empty($regionIds)) {
                    $query->whereIn('clusters.region_id', $regionIds);
                }
                if (! empty($clusterIds)) {
                    $query->whereIn('clusters.id', $clusterIds);
                }
            }
        }

        $clusters = $query->get();

        if ($clusters->isEmpty()) {
            return new Collection;
        }

        $rows = [];
        $currentRow = 2; // Header occupies row 1

        $groupedByBadanUsaha = $clusters->groupBy('badanusaha_id');

        foreach ($groupedByBadanUsaha as $badanusahaId => $divisions) {
            $badanusahaStart = $currentRow;

            foreach ($divisions->groupBy('divisi_id') as $divisiId => $regions) {
                $divisiStart = $currentRow;

                foreach ($regions->groupBy('region_id') as $regionId => $clustersGroup) {
                    $regionStart = $currentRow;

                    foreach ($clustersGroup as $cluster) {
                        $rows[] = [
                            $cluster->badanusaha_name,
                            $cluster->divisi_name,
                            $cluster->region_name,
                            $cluster->cluster_name,
                        ];
                        $currentRow++;
                    }

                    $regionEnd = $currentRow - 1;
                    if ($regionEnd >= $regionStart) {
                        $this->mergeInstructions[] = ['C', $regionStart, $regionEnd];
                    }
                }

                $divisiEnd = $currentRow - 1;
                if ($divisiEnd >= $divisiStart) {
                    $this->mergeInstructions[] = ['B', $divisiStart, $divisiEnd];
                }
            }

            $badanusahaEnd = $currentRow - 1;
            if ($badanusahaEnd >= $badanusahaStart) {
                $this->mergeInstructions[] = ['A', $badanusahaStart, $badanusahaEnd];
            }
        }

        return collect($rows);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F59E0B');

        $sheet->getStyle('A1:D1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('FFFFFF');

        return $sheet;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->mergeInstructions as [$column, $startRow, $endRow]) {
                    $range = sprintf('%s%d:%s%d', $column, $startRow, $column, $endRow);

                    if ($endRow > $startRow) {
                        $sheet->mergeCells($range);
                    }

                    $sheet->getStyle($range)
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Freeze header for better navigation
                $sheet->freezePane('A2');
            },
        ];
    }
}
