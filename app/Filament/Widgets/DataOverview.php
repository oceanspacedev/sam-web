<?php

namespace App\Filament\Widgets;

use App\Models\Outlet;
use App\Models\Register;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

class DataOverview extends StatsOverviewWidget implements HasActions
{
    use InteractsWithActions;

    protected ?string $heading = 'Statistik Data';

    #[Url(as: 'range', history: true)]
    public ?string $filter = null;

    protected int|string|array $columnSpan = 'full';

    public function mount(): void
    {
        $this->filter ??= $this->getDefaultFilter();
    }

    public static function canView(): bool
    {
        return Gate::allows('View:DataOverview');
    }

    public function getSectionContentComponent(): Component
    {
        return Section::make()
            ->heading($this->getHeading())
            ->description($this->getDescription())
            ->headerActions($this->getFilterActions())
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer();
    }

    /**
     * @return array<Action>
     */
    protected function getFilterActions(): array
    {
        $filter = $this->filter ?? $this->getDefaultFilter();

        $options = [
            'today' => 'Harian',
            'week' => 'Mingguan',
            'month' => 'Bulanan',
        ];

        return collect($options)
            ->map(function (string $label, string $value) use ($filter): Action {
                $isActive = $filter === $value;

                return Action::make("filter_{$value}")
                    ->label($label)
                    ->color($isActive ? 'primary' : 'gray')
                    ->outlined(! $isActive)
                    ->button()
                    ->size('sm')
                    ->action(fn () => $this->applyFilter($value));
            })
            ->values()
            ->all();
    }

    protected function applyFilter(string $filter): void
    {
        if ($this->filter === $filter) {
            return;
        }

        $this->filter = $filter;
        $this->cachedStats = null;
    }

    protected function getCards(): array
    {
        $range = $this->resolveRange();

        $metrics = [
            [
                'label' => 'User',
                'model' => User::class,
                'dateColumn' => 'created_at',
                'permission' => 'ViewAny:User',
            ],
            [
                'label' => 'Outlet',
                'model' => Outlet::class,
                'dateColumn' => 'created_at',
                'permission' => 'ViewAny:Outlet',
            ],
            [
                'label' => 'Register NOO',
                'model' => Register::class,
                'dateColumn' => 'updated_at', // capture upgrades from lead → NOO
                'constraint' => fn (Builder $query) => $query->where('type', 'NOO'),
                'permission' => 'ViewAny:Register',
            ],
            [
                'label' => 'Register Lead',
                'model' => Register::class,
                'dateColumn' => 'created_at',
                'constraint' => fn (Builder $query) => $query->where('type', 'LEAD'),
                'permission' => 'ViewAny:Register',
            ],
            [
                'label' => 'Visit',
                'model' => Visit::class,
                'dateColumn' => 'tanggal_visit',
                'permission' => 'ViewAny:Visit',
            ],
        ];

        return collect($metrics)
            ->filter(function (array $metric): bool {
                if (! isset($metric['permission'])) {
                    return true;
                }

                return Gate::allows($metric['permission']);
            })
            ->map(function (array $metric) use ($range): Stat {
                [$current, $previous, $trend] = $this->calculateMetric(
                    $metric['model'],
                    $metric['dateColumn'],
                    $range,
                    $metric['constraint'] ?? null,
                );

                return $this->makeCard(
                    $metric['label'],
                    $current,
                    $previous,
                    $trend,
                );
            })
            ->toArray();
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Harian',
            'week' => 'Mingguan',
            'month' => 'Bulanan',
        ];
    }

    protected function getDefaultFilter(): ?string
    {
        return 'week';
    }

    /**
     * @param  class-string  $modelClass
     */
    protected function calculateMetric(string $modelClass, string $dateColumn, array $range, ?callable $constraint = null): array
    {
        $query = $modelClass::query();

        $query = $this->applyVisibility($query, $modelClass);

        if ($constraint) {
            $constraint($query);
        }

        $currentCount = (clone $query)
            ->whereBetween($dateColumn, [$range['currentStart'], $range['currentEnd']])
            ->count();

        $previousCount = (clone $query)
            ->whereBetween($dateColumn, [$range['previousStart'], $range['previousEnd']])
            ->count();

        $trend = $this->buildTrend(
            clone $query,
            $dateColumn,
            $range['trendStart'],
            $range['trendEnd'],
        );

        return [$currentCount, $previousCount, $trend];
    }

    protected function applyVisibility(Builder $query, string $modelClass): Builder
    {
        /** @var User|null $viewer */
        $viewer = Auth::user();

        if (! $viewer) {
            return $query;
        }

        if (in_array($modelClass, [Outlet::class, Register::class], true)) {
            return $query->visibleTo($viewer);
        }

        if ($modelClass === Visit::class) {
            return $this->scopeVisits($query, $viewer);
        }

        if ($modelClass === User::class) {
            return $this->scopeUsers($query, $viewer);
        }

        return $query;
    }

    protected function scopeVisits(Builder $query, User $viewer): Builder
    {
        $role = $viewer->role;
        $scopeLevel = $role->organizational_scope_level ?? 'cluster';

        if ($scopeLevel === 'all') {
            return $query;
        }

        $badanUsahaIds = $viewer->badanUsahas()->pluck('badan_usahas.id')->toArray();
        $divisiIds = $viewer->divisis()->pluck('divisions.id')->toArray();
        $regionIds = $viewer->regions()->pluck('regions.id')->toArray();
        $clusterIds = $viewer->clusters()->pluck('clusters.id')->toArray();

        if (! empty($badanUsahaIds)) {
            $query->whereHas('user.badanUsahas', fn ($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
        }

        if (! empty($divisiIds)) {
            $query->whereHas('user.divisis', fn ($q) => $q->whereIn('divisions.id', $divisiIds));
        }

        if (! empty($regionIds)) {
            $query->whereHas('user.regions', fn ($q) => $q->whereIn('regions.id', $regionIds));
        }

        if (! empty($clusterIds)) {
            $query->whereHas('user.clusters', fn ($q) => $q->whereIn('clusters.id', $clusterIds));
        }

        return $query;
    }

    protected function scopeUsers(Builder $query, User $viewer): Builder
    {
        $role = $viewer->role;

        if (! $role) {
            return $query;
        }

        $scopeLevel = $role->organizational_scope_level ?? 'cluster';

        if ($scopeLevel === 'all') {
            return $query;
        }

        $badanUsahaIds = $viewer->badanUsahas()->pluck('badan_usahas.id')->toArray();
        $divisiIds = $viewer->divisis()->pluck('divisions.id')->toArray();
        $regionIds = $viewer->regions()->pluck('regions.id')->toArray();
        $clusterIds = $viewer->clusters()->pluck('clusters.id')->toArray();

        if (! empty($badanUsahaIds)) {
            $query->whereHas('badanUsahas', fn ($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
        }

        if (! empty($divisiIds)) {
            $query->whereHas('divisis', fn ($q) => $q->whereIn('divisions.id', $divisiIds));
        }

        if ($scopeLevel === 'cluster') {
            if (! empty($regionIds)) {
                $query->whereHas('regions', fn ($q) => $q->whereIn('regions.id', $regionIds));
            }

            if (! empty($clusterIds)) {
                $query->whereHas('clusters', fn ($q) => $q->whereIn('clusters.id', $clusterIds));
            }
        }

        return $query;
    }

    protected function buildTrend(Builder $query, string $dateColumn, Carbon $start, Carbon $end): array
    {
        $table = $query->getModel()->getTable();

        $results = (clone $query)
            ->whereBetween($dateColumn, [$start, $end])
            ->selectRaw("DATE({$table}.{$dateColumn}) as day")
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day');

        $period = CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->endOfDay());

        return collect($period)->map(
            fn (Carbon $date) => (int) ($results[$date->format('Y-m-d')] ?? 0)
        )->all();
    }

    protected function makeCard(string $label, int $current, int $previous, array $trend): Stat
    {
        [$description, $icon, $color] = $this->formatChange($current, $previous);

        $card = Stat::make($label, $current)
            ->description($description)
            ->chart($trend)
            ->color($color);

        if ($icon) {
            $card->descriptionIcon($icon);
        }

        return $card;
    }

    protected function formatChange(int $current, int $previous): array
    {
        if ($previous === 0) {
            if ($current === 0) {
                return ['Periode sebelumnya: 0', null, 'gray'];
            }

            return ["Periode sebelumnya: 0, selisih: +{$current}", 'heroicon-o-arrow-up', 'success'];
        }

        $difference = $current - $previous;
        $percentage = round(($difference / $previous) * 100, 1);
        $sign = $difference >= 0 ? '+' : '';

        $icon = $difference > 0 ? 'heroicon-o-arrow-up' : ($difference < 0 ? 'heroicon-o-arrow-down' : null);
        $color = $difference > 0 ? 'success' : ($difference < 0 ? 'danger' : 'gray');

        return [
            "Periode sebelumnya: {$previous}, selisih: {$sign}{$difference} ({$sign}{$percentage}%)",
            $icon,
            $color,
        ];
    }

    protected function resolveRange(): array
    {
        $filter = $this->filter ?? $this->getDefaultFilter();
        $now = Carbon::now();

        return match ($filter) {
            'today' => [
                'currentStart' => $now->copy()->startOfDay(),
                'currentEnd' => $now->copy()->endOfDay(),
                'previousStart' => $now->copy()->subDay()->startOfDay(),
                'previousEnd' => $now->copy()->subDay()->endOfDay(),
                'trendStart' => $now->copy()->subDays(6)->startOfDay(),
                'trendEnd' => $now->copy()->endOfDay(),
            ],
            'month' => [
                'currentStart' => $now->copy()->subDays(29)->startOfDay(),
                'currentEnd' => $now->copy()->endOfDay(),
                'previousStart' => $now->copy()->subDays(59)->startOfDay(),
                'previousEnd' => $now->copy()->subDays(30)->endOfDay(),
                'trendStart' => $now->copy()->subDays(29)->startOfDay(),
                'trendEnd' => $now->copy()->endOfDay(),
            ],
            default => [
                'currentStart' => $now->copy()->subDays(6)->startOfDay(),
                'currentEnd' => $now->copy()->endOfDay(),
                'previousStart' => $now->copy()->subDays(13)->startOfDay(),
                'previousEnd' => $now->copy()->subDays(7)->endOfDay(),
                'trendStart' => $now->copy()->subDays(6)->startOfDay(),
                'trendEnd' => $now->copy()->endOfDay(),
            ],
        };
    }
}
