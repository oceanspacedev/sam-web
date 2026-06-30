<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\Register;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

class DashboardMetricsCalculator
{
    /**
     * @var array{noo: array{current: int, previous: int, trend: array<int>}, lead: array{current: int, previous: int, trend: array<int>}}|null
     */
    protected ?array $registerMetrics = null;

    /**
     * @param  array{
     *     currentStart: Carbon,
     *     currentEnd: Carbon,
     *     previousStart: Carbon,
     *     previousEnd: Carbon,
     *     trendStart: Carbon,
     *     trendEnd: Carbon
     * }  $range
     */
    public function __construct(
        protected User $viewer,
        protected array $range,
    ) {}

    /**
     * @return array{current: int, previous: int, trend: array<int>}
     */
    public function users(): array
    {
        return $this->forModel(User::class, 'created_at');
    }

    /**
     * @return array{current: int, previous: int, trend: array<int>}
     */
    public function outlets(): array
    {
        return $this->forModel(Outlet::class, 'created_at');
    }

    /**
     * @return array{noo: array{current: int, previous: int, trend: array<int>}, lead: array{current: int, previous: int, trend: array<int>}}
     */
    public function registers(): array
    {
        if ($this->registerMetrics !== null) {
            return $this->registerMetrics;
        }

        $query = $this->scopedQuery(Register::class);

        $counts = (clone $query)->selectRaw(
            "SUM(CASE WHEN registers.type = 'NOO' AND registers.updated_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as noo_current, ".
            "SUM(CASE WHEN registers.type = 'NOO' AND registers.updated_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as noo_previous, ".
            "SUM(CASE WHEN registers.type = 'LEAD' AND registers.created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as lead_current, ".
            "SUM(CASE WHEN registers.type = 'LEAD' AND registers.created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as lead_previous",
            [
                $this->range['currentStart'],
                $this->range['currentEnd'],
                $this->range['previousStart'],
                $this->range['previousEnd'],
                $this->range['currentStart'],
                $this->range['currentEnd'],
                $this->range['previousStart'],
                $this->range['previousEnd'],
            ],
        )->first();

        return $this->registerMetrics = [
            'noo' => [
                'current' => (int) ($counts->noo_current ?? 0),
                'previous' => (int) ($counts->noo_previous ?? 0),
                'trend' => $this->buildTrend(
                    (clone $query)->where('registers.type', 'NOO'),
                    'updated_at',
                ),
            ],
            'lead' => [
                'current' => (int) ($counts->lead_current ?? 0),
                'previous' => (int) ($counts->lead_previous ?? 0),
                'trend' => $this->buildTrend(
                    (clone $query)->where('registers.type', 'LEAD'),
                    'created_at',
                ),
            ],
        ];
    }

    /**
     * @return array{current: int, previous: int, trend: array<int>}
     */
    public function registerNoo(): array
    {
        return $this->registers()['noo'];
    }

    /**
     * @return array{current: int, previous: int, trend: array<int>}
     */
    public function registerLead(): array
    {
        return $this->registers()['lead'];
    }

    /**
     * @return array{current: int, previous: int, trend: array<int>}
     */
    public function visits(): array
    {
        return $this->forModel(Visit::class, 'tanggal_visit');
    }

    /**
     * @param  class-string  $modelClass
     * @return array{current: int, previous: int, trend: array<int>}
     */
    protected function forModel(string $modelClass, string $dateColumn): array
    {
        $query = $this->scopedQuery($modelClass);

        return $this->buildMetric($query, $dateColumn);
    }

    /**
     * @return array{current: int, previous: int, trend: array<int>}
     */
    protected function buildMetric(Builder $query, string $dateColumn): array
    {
        [$current, $previous] = $this->countPeriods($query, $dateColumn);
        $trend = $this->buildTrend($query, $dateColumn);

        return [
            'current' => $current,
            'previous' => $previous,
            'trend' => $trend,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function countPeriods(Builder $query, string $dateColumn): array
    {
        $table = $query->getModel()->getTable();
        $column = "{$table}.{$dateColumn}";

        $result = (clone $query)->selectRaw(
            "SUM(CASE WHEN {$column} BETWEEN ? AND ? THEN 1 ELSE 0 END) as current_count, ".
            "SUM(CASE WHEN {$column} BETWEEN ? AND ? THEN 1 ELSE 0 END) as previous_count",
            [
                $this->range['currentStart'],
                $this->range['currentEnd'],
                $this->range['previousStart'],
                $this->range['previousEnd'],
            ],
        )->first();

        return [
            (int) ($result->current_count ?? 0),
            (int) ($result->previous_count ?? 0),
        ];
    }

    /**
     * @return array<int>
     */
    protected function buildTrend(Builder $query, string $dateColumn): array
    {
        $table = $query->getModel()->getTable();

        $results = (clone $query)
            ->whereBetween("{$table}.{$dateColumn}", [$this->range['trendStart'], $this->range['trendEnd']])
            ->selectRaw("DATE({$table}.{$dateColumn}) as day")
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day');

        $period = CarbonPeriod::create(
            $this->range['trendStart']->copy()->startOfDay(),
            '1 day',
            $this->range['trendEnd']->copy()->endOfDay(),
        );

        return collect($period)->map(
            fn (Carbon $date) => (int) ($results[$date->format('Y-m-d')] ?? 0)
        )->all();
    }

    /**
     * @param  class-string  $modelClass
     */
    protected function scopedQuery(string $modelClass): Builder
    {
        $query = $modelClass::query();
        $table = (new $modelClass)->getTable();

        return match ($modelClass) {
            Outlet::class, Register::class => FilamentOrganizationalScope::applyDirectColumns(
                $query,
                $this->viewer,
                $table,
            ),
            Visit::class => FilamentOrganizationalScope::applyViaUserForeignKey($query, $this->viewer),
            User::class => FilamentOrganizationalScope::applyUserScope($query, $this->viewer),
            default => $query,
        };
    }
}
