<?php

namespace App\Exports\Outlet;

use App\Exports\Concerns\PreservesTextColumns;
use App\Models\Outlet;
use App\Models\User;
use App\Support\OrganizationalScope;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletUpdatedSheet implements FromQuery, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use PreservesTextColumns;

    private bool $userResolved = false;

    private ?User $user = null;

    public function __construct(private ?int $userId = null) {}

    public function title(): string
    {
        return 'UpdatedCluster';
    }

    public function headings(): array
    {
        return [
            'badan_usaha',
            'divisi',
            'region',
            'cluster',
            'kode_outlet',
            'nama_outlet',
            'nama_pemilik_outlet',
            'nomer_tlp_outlet',
            'distric',
            'limit',
            'status_outlet',
            'badan_usaha_baru',
            'divisi_baru',
            'region_baru',
            'cluster_baru',
            'kode_outlet_baru',
            'nama_outlet_baru',
            'nama_pemilik_outlet_baru',
            'nomer_tlp_outlet_baru',
            'distric_baru',
            'limit_baru',
            'status_outlet_baru',
        ];
    }

    public function query(): Builder
    {
        $query = Outlet::query()
            ->select([
                'outlets.*',
                'badan_usahas.name as badan_usaha_name',
                'divisions.name as divisi_name',
                'regions.name as region_name',
                'clusters.name as cluster_name',
            ])
            ->leftJoin('badan_usahas', 'outlets.badanusaha_id', '=', 'badan_usahas.id')
            ->leftJoin('divisions', 'outlets.divisi_id', '=', 'divisions.id')
            ->leftJoin('regions', 'outlets.region_id', '=', 'regions.id')
            ->leftJoin('clusters', 'outlets.cluster_id', '=', 'clusters.id');

        OrganizationalScope::applyToQuery($query, $this->user(), 'outlets');

        return $query->orderBy('outlets.kode_outlet', 'asc');
    }

    public function map($outlet): array
    {
        return [
            $outlet->badan_usaha_name ?? '',
            $outlet->divisi_name ?? '',
            $outlet->region_name ?? '',
            $outlet->cluster_name ?? '',
            $outlet->kode_outlet,
            $outlet->nama_outlet,
            $outlet->nama_pemilik_outlet ?? '',
            $outlet->nomer_tlp_outlet ?? '',
            $outlet->distric ?? '',
            $outlet->limit ?? 0,
            $outlet->status_outlet ?? 'MAINTAIN',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 18,
            'C' => 20,
            'D' => 22,
            'E' => 18,
            'F' => 32,
            'G' => 28,
            'H' => 20,
            'I' => 22,
            'J' => 12,
            'K' => 18,
            'L' => 20,
            'M' => 20,
            'N' => 22,
            'O' => 24,
            'P' => 20,
            'Q' => 34,
            'R' => 30,
            'S' => 22,
            'T' => 24,
            'U' => 14,
            'V' => 20,
        ];
    }

    protected function user(): ?User
    {
        if (! $this->userResolved) {
            $this->user = $this->userId ? User::with('role')->find($this->userId) : null;
            $this->userResolved = true;
        }

        return $this->user;
    }

    protected function textColumns(): array
    {
        return ['E', 'P'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1FAD7');

        $sheet->getStyle('A1:K1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('000000');

        $sheet->getStyle('L1:V1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FEF3C7');

        $sheet->getStyle('L1:V1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('000000');

        return $sheet;
    }
}
