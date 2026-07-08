<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\DashboardMetricsCalculator;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

class DataOverview extends StatsOverviewWidget implements HasActions
{
    use InteractsWithActions;

    protected static bool $isLazy = true;

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
        return false;
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
        /** @var User|null $viewer */
        $viewer = Auth::user();

        if (! $viewer) {
            return [];
        }

        $filter = $this->filter ?? $this->getDefaultFilter();
        $range = $this->resolveRange();
        $metrics = $this->resolveMetrics($viewer, $filter, $range);

        $definitions = [
            [
                'label' => 'User',
                'key' => 'users',
                'permission' => 'ViewAny:User',
            ],
            [
                'label' => 'Outlet',
                'key' => 'outlets',
                'permission' => 'ViewAny:Outlet',
            ],
            [
                'label' => 'Register NOO',
                'key' => 'register_noo',
                'permission' => 'ViewAny:Register',
            ],
            [
                'label' => 'Register Lead',
                'key' => 'register_lead',
                'permission' => 'ViewAny:Register',
            ],
            [
                'label' => 'Visit',
                'key' => 'visits',
                'permission' => 'ViewAny:Visit',
            ],
        ];

        return collect($definitions)
            ->filter(fn (array $definition): bool => Gate::allows($definition['permission']))
            ->map(function (array $definition) use ($metrics): Stat {
                $metric = $metrics[$definition['key']];

                return $this->makeCard(
                    $definition['label'],
                    $metric['current'],
                    $metric['previous'],
                    $metric['trend'],
                );
            })
            ->toArray();
    }

    /**
     * @param  array{
     *     currentStart: Carbon,
     *     currentEnd: Carbon,
     *     previousStart: Carbon,
     *     previousEnd: Carbon,
     *     trendStart: Carbon,
     *     trendEnd: Carbon
     * }  $range
     * @return array<string, array{current: int, previous: int, trend: array<int>}>
     */
    protected function resolveMetrics(User $viewer, string $filter, array $range): array
    {
        $cacheSeconds = (int) config('filament.dashboard_metrics_cache_seconds', 0);

        if ($cacheSeconds <= 0) {
            return $this->computeMetrics($viewer, $range);
        }

        $cacheKey = sprintf(
            'dashboard_metrics:%d:%s',
            $viewer->id,
            $filter,
        );

        return Cache::remember(
            $cacheKey,
            $cacheSeconds,
            fn (): array => $this->computeMetrics($viewer, $range),
        );
    }

    /**
     * @param  array{
     *     currentStart: Carbon,
     *     currentEnd: Carbon,
     *     previousStart: Carbon,
     *     previousEnd: Carbon,
     *     trendStart: Carbon,
     *     trendEnd: Carbon
     * }  $range
     * @return array<string, array{current: int, previous: int, trend: array<int>}>
     */
    protected function computeMetrics(User $viewer, array $range): array
    {
        $calculator = new DashboardMetricsCalculator($viewer, $range);
        $registers = $calculator->registers();

        return [
            'users' => $calculator->users(),
            'outlets' => $calculator->outlets(),
            'register_noo' => $registers['noo'],
            'register_lead' => $registers['lead'],
            'visits' => $calculator->visits(),
        ];
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
