<?php

namespace App\Exports\Visit;

use App\Exports\Concerns\PreservesTextColumns;
use App\Models\Outlet;
use App\Models\Register;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class VisitsThisMonthSheet implements FromCollection, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithTitle
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

        $visits = Visit::query()
            ->with([
                'realizedPlanVisit',
                'visitable' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                    Outlet::class => ['region:id,name', 'cluster:id,name'],
                    Register::class => ['region:id,name', 'cluster:id,name'],
                ]),
            ])
            ->where('user_id', $this->user->id)
            ->whereBetween('tanggal_visit', [$start, $end])
            ->orderBy('tanggal_visit')
            ->get();

        return $visits->map(function (Visit $v) {
            $targetType = '-';
            if ($v->isOutletVisit()) {
                $targetType = 'Outlet';
            } elseif ($v->isRegisterVisit()) {
                $registerType = strtoupper((string) ($v->visitable?->type ?? ''));
                $targetType = $registerType ? "Register ({$registerType})" : 'Register';
            }

            return [
                'Tanggal Visit' => Carbon::parse($v->tanggal_visit)->format('Y-m-d'),
                'Jenis Target' => $targetType,
                'Kode Outlet' => $v->visitable?->kode_outlet ?? '-',
                'Nama Outlet' => $v->visitable?->nama_outlet ?? '-',
                'Distrik' => $v->visitable?->distric ?? '-',
                'Region' => $v->visitable?->region?->name ?? '-',
                'Cluster' => $v->visitable?->cluster?->name ?? '-',
                'Tipe Visit' => $v->tipe_visit,
                'Jadwal' => $v->plannedScheduleLabel(),
                'Durasi (mnt)' => $v->durasi_visit,
                'Check In' => $v->check_in_time ? Carbon::parse($v->check_in_time)->format('Y-m-d H:i') : null,
                'Check Out' => $v->check_out_time ? Carbon::parse($v->check_out_time)->format('Y-m-d H:i') : null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tanggal Visit', 'Jenis Target', 'Kode Outlet', 'Nama Outlet', 'Distrik', 'Region', 'Cluster', 'Tipe Visit', 'Jadwal', 'Durasi (mnt)', 'Check In', 'Check Out',
        ];
    }

    public function title(): string
    {
        return sprintf('Visits (%04d-%02d)', $this->year, $this->month);
    }

    protected function textColumns(): array
    {
        return ['C'];
    }
}
