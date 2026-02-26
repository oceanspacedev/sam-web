<?php

namespace Database\Factories;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'scope_level' => SystemSetting::SCOPE_GLOBAL,
            'badanusaha_id' => null,
            'division_id' => null,
            'region_id' => null,
            'cluster_id' => null,
            'allow_register_visit' => true,
            'default_register_radius' => 100,
            'plan_visit_min_days' => 3,
        ];
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_level' => SystemSetting::SCOPE_GLOBAL,
            'badanusaha_id' => null,
            'division_id' => null,
            'region_id' => null,
            'cluster_id' => null,
        ]);
    }

    public function forBadanUsaha(BadanUsaha|int $badanUsaha): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_level' => SystemSetting::SCOPE_BADANUSAHA,
            'badanusaha_id' => $badanUsaha instanceof BadanUsaha ? $badanUsaha->id : $badanUsaha,
            'division_id' => null,
            'region_id' => null,
            'cluster_id' => null,
        ]);
    }

    public function forDivision(Division|int $division): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_level' => SystemSetting::SCOPE_DIVISION,
            'division_id' => $division instanceof Division ? $division->id : $division,
            'region_id' => null,
            'cluster_id' => null,
        ]);
    }

    public function forRegion(Region|int $region): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_level' => SystemSetting::SCOPE_REGION,
            'region_id' => $region instanceof Region ? $region->id : $region,
            'cluster_id' => null,
        ]);
    }

    public function forCluster(Cluster|int $cluster): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_level' => SystemSetting::SCOPE_CLUSTER,
            'cluster_id' => $cluster instanceof Cluster ? $cluster->id : $cluster,
        ]);
    }

    public function allowRegisterVisit(bool $allowed = true): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_register_visit' => $allowed,
        ]);
    }

    public function withRadius(int $radius): static
    {
        return $this->state(fn (array $attributes) => [
            'default_register_radius' => $radius,
        ]);
    }

    public function withMinDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_visit_min_days' => $days,
        ]);
    }
}
