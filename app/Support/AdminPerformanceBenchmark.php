<?php

namespace App\Support;

use App\Filament\Resources\BadanUsahas\BadanUsahaResource;
use App\Filament\Resources\Clusters\ClusterResource;
use App\Filament\Resources\Divisions\DivisionResource;
use App\Filament\Resources\Outlets\OutletResource;
use App\Filament\Resources\Registers\RegisterResource;
use App\Filament\Resources\Regions\RegionResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Visits\VisitResource;
use App\Filament\Widgets\DataOverview;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Spatie\Permission\PermissionRegistrar;

class AdminPerformanceBenchmark
{
    public const DATASET = [
        'registers' => 60,
        'visits' => 60,
        'outlets' => 30,
        'field_users' => 20,
    ];

    /**
     * @return array<string, mixed>
     */
    public function run(int $iterations = 3): array
    {
        $this->seedDataset();

        $scenarios = [
            'register_list_page' => fn () => $this->simulateRegisterListPage(),
            'visit_list_page' => fn () => $this->simulateVisitListPage(),
            'user_list_page' => fn () => $this->simulateUserListPage(),
            'outlet_list_page' => fn () => $this->simulateOutletListPage(),
            'division_index' => fn () => $this->simulateDivisionIndex(),
            'region_index' => fn () => $this->simulateRegionIndex(),
            'cluster_index' => fn () => $this->simulateClusterIndex(),
            'dashboard_stats' => fn () => $this->simulateDashboardStats(),
            'badan_usaha_index' => fn () => $this->simulateBadanUsahaIndex(),
        ];

        $results = [];

        foreach ($scenarios as $name => $callback) {
            $results[$name] = $this->measureScenario($name, $callback, $iterations);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'iterations' => $iterations,
            'dataset' => self::DATASET,
            'scenarios' => $results,
            'totals' => [
                'queries' => collect($results)->sum('queries.median'),
                'duration_ms' => round(collect($results)->sum('duration_ms.median'), 2),
            ],
        ];
    }

    /**
     * @param  callable(): void  $callback
     * @return array<string, mixed>
     */
    protected function measureScenario(string $name, callable $callback, int $iterations): array
    {
        $queryCounts = [];
        $durations = [];

        for ($i = 0; $i < $iterations; $i++) {
            $viewer = $this->viewer();
            Auth::login($viewer);
            $viewer->forgetOrganizationalIdsCache();
            $viewer->load('role');

            DB::flushQueryLog();
            DB::enableQueryLog();

            $startedAt = hrtime(true);
            $callback();
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

            $queryCounts[] = count(DB::getQueryLog());
            $durations[] = $durationMs;

            Auth::logout();
        }

        return [
            'queries' => $this->summarizeNumbers($queryCounts),
            'duration_ms' => $this->summarizeNumbers($durations),
        ];
    }

    /**
     * @param  array<int, float|int>  $values
     * @return array{min: float|int, median: float|int, max: float|int}
     */
    protected function summarizeNumbers(array $values): array
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        $median = $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];

        return [
            'min' => round($values[0], 2),
            'median' => round($median, 2),
            'max' => round($values[$count - 1], 2),
        ];
    }

    protected function seedDataset(): void
    {
        $bu = BadanUsaha::factory()->create(['code' => 'BU-BENCH', 'name' => 'Benchmark BU']);
        $div = Division::factory()->create(['code' => 'DIV-BENCH', 'name' => 'Benchmark DIV', 'badanusaha_id' => $bu->id]);
        $reg = Region::factory()->create(['code' => 'REG-BENCH', 'name' => 'Benchmark REG', 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
        $clus = Cluster::factory()->create(['code' => 'CLUS-BENCH', 'name' => 'Benchmark CLUS', 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

        $fieldRole = Role::factory()->create([
            'name' => 'BENCH FIELD',
            'can_access_web' => false,
            'organizational_scope_level' => 'cluster',
        ]);

        $fieldUsers = User::factory()
            ->count(self::DATASET['field_users'])
            ->create(['role_id' => $fieldRole->id])
            ->each(function (User $user) use ($bu, $div, $reg, $clus): void {
                $user->badanUsahas()->sync([$bu->id]);
                $user->divisis()->sync([$div->id]);
                $user->regions()->sync([$reg->id]);
                $user->clusters()->sync([$clus->id]);
            });

        $outlet = Outlet::factory()->create([
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
        ]);

        Outlet::factory()->count(self::DATASET['outlets'] - 1)->create([
            'badanusaha_id' => $bu->id,
            'divisi_id' => $div->id,
            'region_id' => $reg->id,
            'cluster_id' => $clus->id,
        ]);

        $statuses = ['PENDING', 'CONFIRMED', 'APPROVED', 'REJECTED'];

        for ($i = 0; $i < self::DATASET['registers']; $i++) {
            Register::factory()->create([
                'badanusaha_id' => $bu->id,
                'divisi_id' => $div->id,
                'region_id' => $reg->id,
                'cluster_id' => $clus->id,
                'created_by_id' => $fieldUsers[$i % $fieldUsers->count()]->id,
                'tm_id' => $fieldUsers[$i % $fieldUsers->count()]->id,
                'status' => $statuses[$i % count($statuses)],
                'type' => $i % 5 === 0 ? 'LEAD' : 'NOO',
            ]);
        }

        for ($i = 0; $i < self::DATASET['visits']; $i++) {
            Visit::factory()->create([
                'user_id' => $fieldUsers[$i % $fieldUsers->count()]->id,
                'visitable_type' => Outlet::class,
                'visitable_id' => $outlet->id,
                'tipe_visit' => $i % 2 === 0 ? 'PLANNED' : 'EXTRACALL',
                'tanggal_visit' => Carbon::now()->subDays($i % 14)->toDateString(),
            ]);
        }

        $viewerRole = Role::factory()->create([
            'name' => 'BENCH ADMIN',
            'can_access_web' => true,
            'organizational_scope_level' => 'cluster',
        ]);

        $permissions = collect([
            'ViewAny:User',
            'ViewAny:Outlet',
            'ViewAny:Register',
            'ViewAny:Visit',
            'View:DataOverview',
        ])->map(fn (string $name): Permission => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        $viewerRole->syncPermissions($permissions);

        $viewer = User::factory()->create(['role_id' => $viewerRole->id]);
        $viewer->assignRole($viewerRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $viewer->badanUsahas()->sync([$bu->id]);
        $viewer->divisis()->sync([$div->id]);
        $viewer->regions()->sync([$reg->id]);
        $viewer->clusters()->sync([$clus->id]);

        cache()->put($this->viewerCacheKey(), $viewer->id);
    }

    protected function viewer(): User
    {
        $viewerId = cache()->get($this->viewerCacheKey());

        return User::query()->with('role')->findOrFail($viewerId);
    }

    protected function viewerCacheKey(): string
    {
        return 'admin-performance-benchmark:viewer-id';
    }

    protected function simulateRegisterListPage(): void
    {
        $query = RegisterResource::getEloquentQuery();

        $this->simulateRegisterTabBadges($query);

        RegisterResource::getEloquentQuery()
            ->limit(10)
            ->get()
            ->each(function (Register $register): void {
                $register->createdBy?->nama_lengkap;
                $register->divisi?->name;
                $register->badanusaha?->name;
                $register->region?->name;
                $register->cluster?->name;
            });
    }

    protected function simulateRegisterTabBadges(Builder $query): void
    {
        FilamentTabBadgeCounts::registerStatusCounts($query);
    }

    protected function simulateVisitTabBadges(Builder $query): void
    {
        FilamentTabBadgeCounts::visitTypeCounts($query);
    }

    protected function simulateVisitListPage(): void
    {
        $query = VisitResource::getEloquentQuery();

        $this->simulateVisitTabBadges($query);

        VisitResource::getEloquentQuery()
            ->limit(10)
            ->get()
            ->each(function (Visit $visit): void {
                $visit->user?->nama_lengkap;
                $visit->visitable?->nama_outlet;
            });
    }

    protected function simulateUserListPage(): void
    {
        UserResource::getEloquentQuery()
            ->limit(10)
            ->get()
            ->each(function (User $user): void {
                $user->role?->name;
                $user->tm?->nama_lengkap;
                $user->badanUsahas->pluck('name');
                $user->divisis->pluck('name');
                $user->regions->pluck('name');
                $user->clusters->pluck('name');
            });
    }

    protected function simulateOutletListPage(): void
    {
        OutletResource::getEloquentQuery()
            ->limit(10)
            ->get()
            ->each(function (Outlet $outlet): void {
                $outlet->badanusaha?->name;
                $outlet->divisi?->name;
                $outlet->region?->name;
                $outlet->cluster?->name;
            });
    }

    protected function simulateDivisionIndex(): void
    {
        DivisionResource::getEloquentQuery()
            ->limit(10)
            ->get()
            ->each(fn (Division $division) => $division->badanusaha?->name);
    }

    protected function simulateRegionIndex(): void
    {
        RegionResource::getEloquentQuery()
            ->limit(10)
            ->get()
            ->each(function (Region $region): void {
                $region->badanusaha?->name;
                $region->divisi?->name;
            });
    }

    protected function simulateClusterIndex(): void
    {
        ClusterResource::getEloquentQuery()
            ->limit(10)
            ->get()
            ->each(function (Cluster $cluster): void {
                $cluster->badanusaha?->name;
                $cluster->divisi?->name;
                $cluster->region?->name;
            });
    }

    protected function simulateDashboardStats(): void
    {
        Auth::user()?->getOrganizationalIds();

        $widget = app(DataOverview::class);
        $method = new ReflectionMethod(DataOverview::class, 'getCards');
        $method->setAccessible(true);
        $method->invoke($widget);
    }

    protected function simulateBadanUsahaIndex(): void
    {
        BadanUsahaResource::getEloquentQuery()
            ->limit(10)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    public static function compare(array $baseline, array $current): array
    {
        $rows = [];

        foreach ($baseline['scenarios'] as $name => $baselineScenario) {
            $currentScenario = $current['scenarios'][$name] ?? null;

            if ($currentScenario === null) {
                continue;
            }

            $baselineQueries = (float) $baselineScenario['queries']['median'];
            $currentQueries = (float) $currentScenario['queries']['median'];
            $baselineDuration = (float) $baselineScenario['duration_ms']['median'];
            $currentDuration = (float) $currentScenario['duration_ms']['median'];

            $rows[$name] = [
                'queries' => [
                    'before' => $baselineQueries,
                    'after' => $currentQueries,
                    'delta' => $currentQueries - $baselineQueries,
                    'pct' => self::percentChange($baselineQueries, $currentQueries),
                ],
                'duration_ms' => [
                    'before' => $baselineDuration,
                    'after' => $currentDuration,
                    'delta' => round($currentDuration - $baselineDuration, 2),
                    'pct' => self::percentChange($baselineDuration, $currentDuration),
                ],
            ];
        }

        $baselineTotals = $baseline['totals'];
        $currentTotals = $current['totals'];

        return [
            'baseline_generated_at' => $baseline['generated_at'] ?? null,
            'current_generated_at' => $current['generated_at'] ?? null,
            'scenarios' => $rows,
            'totals' => [
                'queries' => [
                    'before' => $baselineTotals['queries'],
                    'after' => $currentTotals['queries'],
                    'delta' => $currentTotals['queries'] - $baselineTotals['queries'],
                    'pct' => self::percentChange($baselineTotals['queries'], $currentTotals['queries']),
                ],
                'duration_ms' => [
                    'before' => $baselineTotals['duration_ms'],
                    'after' => $currentTotals['duration_ms'],
                    'delta' => round($currentTotals['duration_ms'] - $baselineTotals['duration_ms'], 2),
                    'pct' => self::percentChange($baselineTotals['duration_ms'], $currentTotals['duration_ms']),
                ],
            ],
        ];
    }

    protected static function percentChange(float $before, float $after): ?float
    {
        if ($before === 0.0) {
            return null;
        }

        return round((($after - $before) / $before) * 100, 1);
    }
}
