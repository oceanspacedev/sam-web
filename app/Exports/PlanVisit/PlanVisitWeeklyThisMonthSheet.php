<?php

namespace App\Exports\PlanVisit;

use App\Exports\Concerns\PreservesTextColumns;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PlanVisitWeeklyThisMonthSheet implements FromCollection, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithTitle
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
        $start = $anchor->copy()->startOfMonth()->toDateString();
        $end = $anchor->copy()->endOfMonth()->toDateString();

        $plans = PlanVisit::query()
            ->where('user_id', $this->user->id)
            ->where('schedule_scope', 'weekly')
            ->where('period_start', '<=', $end)
            ->where('period_end', '>=', $start)
            ->with([
                'visitable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    Outlet::class => ['badanusaha', 'divisi', 'region', 'cluster'],
                    Register::class => ['badanusaha', 'divisi', 'region', 'cluster'],
                ]),
                'realizedVisit',
            ])
            ->orderBy('period_start')
            ->get();

        return $plans->map(function (PlanVisit $p) {
            $targetType = '-';
            if ($p->isOutletVisit()) {
                $targetType = 'Outlet';
            } elseif ($p->isRegisterVisit()) {
                $registerType = strtoupper((string) ($p->visitable?->type ?? ''));
                $targetType = $registerType ? "Register ({$registerType})" : 'Register';
            }

            $periode = '-';
            if ($p->period_start && $p->period_end) {
                $periode = Carbon::parse($p->period_start)->format('Y-m-d').' s/d '.Carbon::parse($p->period_end)->format('Y-m-d');
            }

            return [
                'Minggu Ke' => $p->schedule_week ? "Week {$p->schedule_week} ({$p->schedule_year})" : '-',
                'Periode' => $periode,
                'Jenis Target' => $targetType,
                'Kode Outlet' => $p->visitable?->kode_outlet ?? '-',
                'Nama Outlet' => $p->visitable?->nama_outlet ?? '-',
                'Distrik' => $p->visitable?->distric ?? '-',
                'Region' => $p->visitable?->region?->name ?? '-',
                'Cluster' => $p->visitable?->cluster?->name ?? '-',
                'Badan Usaha' => $p->visitable?->badanusaha?->name ?? '-',
                'Divisi' => $p->visitable?->divisi?->name ?? '-',
                'Status Realisasi' => $p->realized_at ? 'Sudah Realisasi' : 'Belum Realisasi',
                'Tanggal Realisasi' => $p->realized_at ? Carbon::parse($p->realized_at)->format('Y-m-d H:i') : '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Minggu Ke',
            'Periode',
            'Jenis Target',
            'Kode Outlet',
            'Nama Outlet',
            'Distrik',
            'Region',
            'Cluster',
            'Badan Usaha',
            'Divisi',
            'Status Realisasi',
            'Tanggal Realisasi',
        ];
    }

    public function title(): string
    {
        return sprintf('Plan Weekly (%04d-%02d)', $this->year, $this->month);
    }

    protected function textColumns(): array
    {
        return ['D'];
    }
}
