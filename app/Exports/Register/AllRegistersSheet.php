<?php

namespace App\Exports\Register;

use App\Exports\Concerns\PreservesTextColumns;
use App\Models\Register;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AllRegistersSheet implements FromCollection, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithTitle
{
    use PreservesTextColumns;

    public function __construct(
        public User $user,
        public ?int $month = null,
        public ?int $year = null,
    ) {
        $this->month = $this->month ?? (int) now()->format('m');
        $this->year = $this->year ?? (int) now()->format('Y');
    }

    public function collection(): Collection
    {
        $anchor = Carbon::createFromDate($this->year, $this->month, 1);
        $end = $anchor->copy()->endOfMonth();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Register> $registers */
        $registers = Register::query()
            ->where(function ($q) {
                $q->where('created_by_id', $this->user->id)
                    ->orWhere('tm_id', $this->user->id);
            })
            ->where('created_at', '<=', $end)
            ->with(['region:id,name', 'cluster:id,name', 'badanusaha:id,name', 'divisi:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return $registers->map(function (Register $r) {
            $relation = [];
            if ((int) $r->created_by_id === (int) $this->user->id) {
                $relation[] = 'Dibuat';
            }
            if ((int) $r->tm_id === (int) $this->user->id) {
                $relation[] = 'TM';
            }

            return [
                'Tanggal Dibuat' => $r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d H:i') : null,
                'Jenis' => strtoupper((string) ($r->type ?? '-')),
                'Status' => $r->status ?? '-',
                'Kode Outlet' => $r->kode_outlet ?? '-',
                'Nama Outlet' => $r->nama_outlet ?? '-',
                'Distrik' => $r->distric ?? '-',
                'Region' => $r->region?->name ?? '-',
                'Cluster' => $r->cluster?->name ?? '-',
                'Badan Usaha' => $r->badanusaha?->name ?? '-',
                'Divisi' => $r->divisi?->name ?? '-',
                'Relasi User' => implode(' + ', $relation) ?: '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tanggal Dibuat',
            'Jenis',
            'Status',
            'Kode Outlet',
            'Nama Outlet',
            'Distrik',
            'Region',
            'Cluster',
            'Badan Usaha',
            'Divisi',
            'Relasi User',
        ];
    }

    public function title(): string
    {
        return 'All Registers';
    }

    protected function textColumns(): array
    {
        return ['D'];
    }
}
