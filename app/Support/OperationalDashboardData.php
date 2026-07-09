<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class OperationalDashboardData
{
    private const RANGES = [
        'today' => 'Hari ini',
        'week' => '7 hari',
        'month' => '30 hari',
    ];

    /**
     * @return array<string, mixed>
     */
    public function for(User $viewer, string $rangeKey): array
    {
        $rangeKey = array_key_exists($rangeKey, self::RANGES) ? $rangeKey : 'week';
        $range = $this->resolveRange($rangeKey);
        $permissions = $this->permissions($viewer);

        $data = [
            'viewer' => [
                'name' => $viewer->nama_lengkap,
                'role' => $viewer->role?->name ?? '-',
                'scope' => $this->scopeLabel($viewer->role?->organizational_scope_level),
            ],
            'range' => [
                'key' => $rangeKey,
                'label' => self::RANGES[$rangeKey],
                'current' => $this->periodLabel($range['currentStart'], $range['currentEnd']),
                'previous' => $this->periodLabel($range['previousStart'], $range['previousEnd']),
            ],
            'rangeOptions' => self::RANGES,
            'permissions' => $permissions,
            'cards' => [],
            'plan' => null,
            'registers' => null,
            'outlets' => null,
            'visits' => null,
            'team' => null,
        ];

        if ($permissions['plan_visits']) {
            $data['plan'] = $this->planSummary($viewer, $range);
            $data['cards'][] = $this->card(
                'Realisasi plan',
                $data['plan']['realizedRate'].'%',
                "{$this->number($data['plan']['realized'])} dari {$this->number($data['plan']['due'])} plan jatuh tempo",
                'success',
                '/admin/plan-visits',
            );
            $data['cards'][] = $this->card(
                'Plan belum realisasi',
                $this->number($data['plan']['unrealizedDue']),
                "{$this->number($data['plan']['overdue'])} lewat tanggal (akumulasi semua periode)",
                $data['plan']['overdue'] > 0 ? 'danger' : 'neutral',
                '/admin/plan-visits',
            );
        }

        if ($permissions['visits']) {
            $data['visits'] = $this->visitSummary($viewer, $range);
            $data['cards'][] = $this->card(
                'Visit terealisasi',
                $this->number($data['visits']['current']),
                $this->changeLabel($data['visits']['current'], $data['visits']['previous']),
                $this->changeTone($data['visits']['current'], $data['visits']['previous']),
                '/admin/visits',
            );
        }

        if ($permissions['registers']) {
            $data['registers'] = $this->registerSummary($viewer, $range);
            $data['cards'][] = $this->card(
                'Register butuh validasi',
                $this->number($data['registers']['needsValidation']),
                "{$this->number($data['registers']['pending'])} pending, {$this->number($data['registers']['confirmed'])} confirmed",
                $data['registers']['needsValidation'] > 0 ? 'warning' : 'neutral',
                '/admin/registers',
                true,
            );
            $data['cards'][] = $this->card(
                'NOO approved',
                $this->number($data['registers']['approvedNoo']),
                $this->changeLabel($data['registers']['approvedNoo'], $data['registers']['previousApprovedNoo']),
                $this->changeTone($data['registers']['approvedNoo'], $data['registers']['previousApprovedNoo']),
                '/admin/registers',
            );
        }

        if ($permissions['outlets']) {
            $data['outlets'] = $this->outletSummary($viewer);
            $data['cards'][] = $this->card(
                'Outlet perlu dibersihkan',
                $this->number($data['outlets']['needsAttention']),
                "{$this->number($data['outlets']['missingLocation'])} tanpa lokasi, {$this->number($data['outlets']['missingMedia'])} media belum lengkap",
                $data['outlets']['needsAttention'] > 0 ? 'warning' : 'neutral',
                '/admin/outlets',
                true,
            );
        }

        if ($permissions['users']) {
            $data['team'] = $this->teamSummary($viewer, $range, $permissions['visits']);
            $data['cards'][] = $this->card(
                'Team terlihat',
                $this->number($data['team']['visibleUsers']),
                $permissions['visits']
                    ? "{$this->number($data['team']['activeUsers'])} user punya visit di periode ini"
                    : 'Mengikuti scope organisasi role ini',
                'neutral',
                '/admin/users',
                true,
            );
        }

        $data['cards'] = array_slice($data['cards'], 0, 8);

        return $data;
    }

    /**
     * @return array{
     *     users: bool,
     *     outlets: bool,
     *     registers: bool,
     *     visits: bool,
     *     plan_visits: bool,
     *     approve_register: bool,
     *     reset_outlet: bool
     * }
     */
    private function permissions(User $viewer): array
    {
        return [
            'users' => Gate::forUser($viewer)->allows('ViewAny:User'),
            'outlets' => Gate::forUser($viewer)->allows('ViewAny:Outlet'),
            'registers' => Gate::forUser($viewer)->allows('ViewAny:Register'),
            'visits' => Gate::forUser($viewer)->allows('ViewAny:Visit'),
            'plan_visits' => Gate::forUser($viewer)->allows('ViewAny:PlanVisit'),
            'approve_register' => Gate::forUser($viewer)->allows('Approve:Register')
                || Gate::forUser($viewer)->allows('Confirm:Register')
                || Gate::forUser($viewer)->allows('Reject:Register'),
            'reset_outlet' => Gate::forUser($viewer)->allows('Reset:Outlet')
                || Gate::forUser($viewer)->allows('ResetLocation:Outlet'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planSummary(User $viewer, array $range): array
    {
        $query = $this->scopedQuery(PlanVisit::class, $viewer);
        $dueRange = (clone $query)->whereBetween('plan_visits.tanggal_visit', [
            $range['currentStart'],
            $range['currentEnd'],
        ]);
        $due = (clone $dueRange)->count();
        $realized = (clone $dueRange)->whereNotNull('plan_visits.realized_at')->count();
        $unrealizedDue = (clone $dueRange)->whereNull('plan_visits.realized_at')->count();
        $overdue = (clone $query)
            ->whereNull('plan_visits.realized_at')
            ->where('plan_visits.tanggal_visit', '<', Carbon::today()->startOfDay())
            ->count();

        return [
            'due' => $due,
            'realized' => $realized,
            'unrealizedDue' => $unrealizedDue,
            'overdue' => $overdue,
            'realizedRate' => $this->percent($realized, $due),
            'upcoming' => $this->upcomingPlans($viewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function visitSummary(User $viewer, array $range): array
    {
        $query = $this->scopedQuery(Visit::class, $viewer);
        $current = (clone $query)->whereBetween('visits.tanggal_visit', [
            $range['currentStart'],
            $range['currentEnd'],
        ])->count();
        $previous = (clone $query)->whereBetween('visits.tanggal_visit', [
            $range['previousStart'],
            $range['previousEnd'],
        ])->count();
        $openCheckouts = (clone $query)
            ->whereNotNull('visits.check_in_time')
            ->whereNull('visits.check_out_time')
            ->count();

        return [
            'current' => $current,
            'previous' => $previous,
            'openCheckouts' => $openCheckouts,
            'topUsers' => $this->topVisitUsers($viewer, $range),
            'recent' => $this->recentVisits($viewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registerSummary(User $viewer, array $range): array
    {
        $query = $this->scopedQuery(Register::class, $viewer);
        $pending = (clone $query)->where('registers.status', 'PENDING')->count();
        $confirmed = (clone $query)->where('registers.status', 'CONFIRMED')->count();
        $approvedNoo = (clone $query)
            ->where('registers.type', 'NOO')
            ->where('registers.status', 'APPROVED')
            ->whereBetween('registers.approved_at', [$range['currentStart'], $range['currentEnd']])
            ->count();
        $previousApprovedNoo = (clone $query)
            ->where('registers.type', 'NOO')
            ->where('registers.status', 'APPROVED')
            ->whereBetween('registers.approved_at', [$range['previousStart'], $range['previousEnd']])
            ->count();
        $newLeads = (clone $query)
            ->where('registers.type', 'LEAD')
            ->whereBetween('registers.created_at', [$range['currentStart'], $range['currentEnd']])
            ->count();

        return [
            'pending' => $pending,
            'confirmed' => $confirmed,
            'needsValidation' => $pending + $confirmed,
            'approvedNoo' => $approvedNoo,
            'previousApprovedNoo' => $previousApprovedNoo,
            'newLeads' => $newLeads,
            'pipeline' => $this->registerPipeline($viewer),
            'recent' => $this->recentRegisters($viewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outletSummary(User $viewer): array
    {
        $query = $this->scopedQuery(Outlet::class, $viewer);
        $total = (clone $query)->count();
        $missingLocation = $this->missingValue((clone $query), 'outlets.latlong')->count();
        $missingMedia = $this->missingAnyMedia((clone $query))->count();
        // Union: outlet butuh perhatian jika lokasi hilang ATAU media tidak lengkap.
        // max() undercount saat kedua himpunan bukan subset satu sama lain.
        $needsAttention = (clone $query)
            ->where(function (Builder $q): void {
                $this->missingValue($q, 'outlets.latlong');
                $q->orWhere(function (Builder $mq): void {
                    $this->missingAnyMedia($mq);
                });
            })
            ->count();

        return [
            'total' => $total,
            'missingLocation' => $missingLocation,
            'missingMedia' => $missingMedia,
            'needsAttention' => $needsAttention,
            'health' => [
                [
                    'label' => 'Lokasi tersimpan',
                    'value' => max(0, $total - $missingLocation),
                    'total' => $total,
                    'percent' => $this->percent(max(0, $total - $missingLocation), $total),
                ],
                [
                    'label' => 'Media lengkap',
                    'value' => max(0, $total - $missingMedia),
                    'total' => $total,
                    'percent' => $this->percent(max(0, $total - $missingMedia), $total),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamSummary(User $viewer, array $range, bool $canViewVisits): array
    {
        $users = $this->scopedQuery(User::class, $viewer);
        $visibleUsers = (clone $users)->count();

        if (! $canViewVisits) {
            return [
                'visibleUsers' => $visibleUsers,
                'activeUsers' => 0,
                'inactiveUsers' => 0,
            ];
        }

        $visits = $this->scopedQuery(Visit::class, $viewer)
            ->whereBetween('visits.tanggal_visit', [$range['currentStart'], $range['currentEnd']]);
        $activeUsers = (clone $visits)->distinct('visits.user_id')->count('visits.user_id');

        return [
            'visibleUsers' => $visibleUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => max(0, $visibleUsers - $activeUsers),
        ];
    }

    /**
     * @return array<int, array{label: string, count: int, percent: int, tone: string}>
     */
    private function registerPipeline(User $viewer): array
    {
        $rows = (clone $this->scopedQuery(Register::class, $viewer))
            ->selectRaw('registers.status, COUNT(*) as aggregate')
            ->groupBy('registers.status')
            ->pluck('aggregate', 'status');
        $total = (int) $rows->sum();

        return collect(['PENDING', 'CONFIRMED', 'APPROVED', 'REJECTED'])
            ->map(fn (string $status): array => [
                'label' => $status,
                'count' => (int) ($rows[$status] ?? 0),
                'percent' => $this->percent((int) ($rows[$status] ?? 0), $total),
                'tone' => match ($status) {
                    'APPROVED' => 'success',
                    'REJECTED' => 'danger',
                    'CONFIRMED' => 'warning',
                    default => 'neutral',
                },
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, role: string, visits: int, targets: int}>
     */
    private function topVisitUsers(User $viewer, array $range): array
    {
        return (clone $this->scopedQuery(Visit::class, $viewer))
            ->join('users', 'users.id', '=', 'visits.user_id')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->whereBetween('visits.tanggal_visit', [$range['currentStart'], $range['currentEnd']])
            ->selectRaw('users.nama_lengkap as name, COALESCE(roles.name, "-") as role_name, COUNT(*) as visits_count, COUNT(DISTINCT CONCAT(visits.visitable_type, "#", visits.visitable_id)) as target_count')
            ->groupBy('users.id', 'users.nama_lengkap', 'roles.name')
            ->orderByDesc('visits_count')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->name,
                'role' => $row->role_name,
                'visits' => (int) $row->visits_count,
                'targets' => (int) $row->target_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentRegisters(User $viewer): array
    {
        return (clone $this->scopedQuery(Register::class, $viewer))
            ->leftJoin('users', 'users.id', '=', 'registers.created_by_id')
            ->whereIn('registers.status', ['PENDING', 'CONFIRMED'])
            ->select([
                'registers.id',
                'registers.nama_outlet',
                'registers.type',
                'registers.status',
                'registers.updated_at',
                'users.nama_lengkap as owner_name',
            ])
            ->orderByDesc('registers.updated_at')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => $row->nama_outlet ?: 'Register #'.$row->id,
                'meta' => trim(($row->type ?: '-').' / '.($row->status ?: '-')),
                'owner' => $row->owner_name ?: '-',
                'date' => $this->dateTime($row->updated_at),
                'url' => "/admin/registers/{$row->id}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentVisits(User $viewer): array
    {
        return (clone $this->scopedQuery(Visit::class, $viewer))
            ->join('users', 'users.id', '=', 'visits.user_id')
            ->select([
                'visits.id',
                'visits.tanggal_visit',
                'visits.tipe_visit',
                'visits.check_in_time',
                'visits.check_out_time',
                'users.nama_lengkap as user_name',
            ])
            ->orderByDesc('visits.tanggal_visit')
            ->orderByDesc('visits.id')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => $row->user_name ?: 'User #'.$row->id,
                'meta' => $row->tipe_visit ?: 'Visit',
                'status' => $row->check_out_time ? 'Selesai' : ($row->check_in_time ? 'Belum checkout' : 'Belum checkin'),
                'date' => $this->dateTime($row->tanggal_visit),
                'url' => "/admin/visits/{$row->id}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingPlans(User $viewer): array
    {
        return (clone $this->scopedQuery(PlanVisit::class, $viewer))
            ->join('users', 'users.id', '=', 'plan_visits.user_id')
            ->whereNull('plan_visits.realized_at')
            ->where('plan_visits.tanggal_visit', '>=', Carbon::today()->startOfDay())
            ->select([
                'plan_visits.id',
                'plan_visits.tanggal_visit',
                'plan_visits.schedule_scope',
                'plan_visits.visitable_type',
                'plan_visits.visitable_id',
                'users.nama_lengkap as user_name',
            ])
            ->orderBy('plan_visits.tanggal_visit')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => $row->user_name ?: 'User #'.$row->id,
                'meta' => class_basename((string) $row->visitable_type).' #'.$row->visitable_id.' / '.$row->schedule_scope,
                'date' => $this->dateTime($row->tanggal_visit),
                'url' => '/admin/plan-visits',
            ])
            ->all();
    }

    /**
     * @param  class-string  $modelClass
     */
    private function scopedQuery(string $modelClass, User $viewer): Builder
    {
        $query = $modelClass::query();
        $table = (new $modelClass)->getTable();

        return match ($modelClass) {
            Outlet::class, Register::class => FilamentOrganizationalScope::applyDirectColumns($query, $viewer, $table),
            Visit::class, PlanVisit::class => FilamentOrganizationalScope::applyViaUserForeignKey($query, $viewer),
            User::class => FilamentOrganizationalScope::applyUserScope($query, $viewer),
            default => $query,
        };
    }

    private function missingValue(Builder $query, string $column): Builder
    {
        return $query->where(function (Builder $query) use ($column): void {
            $query
                ->whereNull($column)
                ->orWhere($column, '')
                ->orWhere($column, '-');
        });
    }

    private function missingAnyMedia(Builder $query): Builder
    {
        $fields = [
            'poto_shop_sign',
            'poto_depan',
            'poto_kiri',
            'poto_kanan',
            'poto_ktp',
        ];

        return $query->where(function (Builder $query) use ($fields): void {
            foreach ($fields as $field) {
                $column = "outlets.{$field}";
                $query
                    ->orWhereNull($column)
                    ->orWhere($column, '')
                    ->orWhere($column, '-');
            }
        });
    }

    /**
     * @return array{
     *     currentStart: Carbon,
     *     currentEnd: Carbon,
     *     previousStart: Carbon,
     *     previousEnd: Carbon
     * }
     */
    private function resolveRange(string $range): array
    {
        $now = Carbon::now();

        return match ($range) {
            'today' => [
                'currentStart' => $now->copy()->startOfDay(),
                'currentEnd' => $now->copy()->endOfDay(),
                'previousStart' => $now->copy()->subDay()->startOfDay(),
                'previousEnd' => $now->copy()->subDay()->endOfDay(),
            ],
            'month' => [
                'currentStart' => $now->copy()->subDays(29)->startOfDay(),
                'currentEnd' => $now->copy()->endOfDay(),
                'previousStart' => $now->copy()->subDays(59)->startOfDay(),
                'previousEnd' => $now->copy()->subDays(30)->endOfDay(),
            ],
            default => [
                'currentStart' => $now->copy()->subDays(6)->startOfDay(),
                'currentEnd' => $now->copy()->endOfDay(),
                'previousStart' => $now->copy()->subDays(13)->startOfDay(),
                'previousEnd' => $now->copy()->subDays(7)->endOfDay(),
            ],
        };
    }

    private function card(string $label, string $value, string $description, string $tone, string $href, bool $cumulative = false): array
    {
        return compact('label', 'value', 'description', 'tone', 'href', 'cumulative');
    }

    private function scopeLabel(?string $scope): string
    {
        return match ($scope) {
            'all' => 'Semua data',
            'badanusaha' => 'Badan usaha',
            'divisi' => 'Divisi',
            'region' => 'Region',
            'cluster' => 'Cluster',
            default => 'Tidak terdefinisi',
        };
    }

    private function periodLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->translatedFormat('d M Y');
        }

        // Sertakan tahun di tanggal mulai jika berbeda tahun dengan tanggal akhir,
        // agar label rentang lintas tahun (mis. "29 Des 2025 - 04 Jan 2026") tidak menyesatkan.
        $startFormat = $start->format('Y') !== $end->format('Y') ? 'd M Y' : 'd M';

        return $start->translatedFormat($startFormat).' - '.$end->translatedFormat('d M Y');
    }

    private function dateTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->translatedFormat('d M Y H:i') : '-';
    }

    private function number(int|float $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function percent(int|float $part, int|float $whole): int
    {
        if ((float) $whole <= 0) {
            return 0;
        }

        return (int) round(((float) $part / (float) $whole) * 100);
    }

    private function changeLabel(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current === 0
                ? 'Sama dengan periode sebelumnya'
                : "+{$this->number($current)} dibanding periode sebelumnya";
        }

        $difference = $current - $previous;
        $percentage = round(($difference / $previous) * 100, 1);
        $sign = $difference >= 0 ? '+' : '';

        return "{$sign}{$this->number($difference)} ({$sign}{$percentage}%) dari periode sebelumnya";
    }

    private function changeTone(int $current, int $previous): string
    {
        if ($current > $previous) {
            return 'success';
        }

        if ($current < $previous) {
            return 'danger';
        }

        return 'neutral';
    }
}
