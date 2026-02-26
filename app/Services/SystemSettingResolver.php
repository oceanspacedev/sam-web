<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Register;
use App\Models\SystemSetting;

class SystemSettingResolver
{
    private bool $isLoaded = false;

    /**
     * @var array<string, array<int, SystemSetting>>
     */
    private array $settingsByScope = [
        SystemSetting::SCOPE_BADANUSAHA => [],
        SystemSetting::SCOPE_DIVISION => [],
        SystemSetting::SCOPE_REGION => [],
        SystemSetting::SCOPE_CLUSTER => [],
    ];

    private ?SystemSetting $globalSetting = null;

    public function resolveForModel(Outlet|Register $target): ?SystemSetting
    {
        return $this->resolveForIds(
            $target->badanusaha_id ? (int) $target->badanusaha_id : null,
            $target->divisi_id ? (int) $target->divisi_id : null,
            $target->region_id ? (int) $target->region_id : null,
            $target->cluster_id ? (int) $target->cluster_id : null,
        );
    }

    public function resolveForIds(?int $badanusahaId, ?int $divisionId, ?int $regionId, ?int $clusterId): ?SystemSetting
    {
        $this->load();

        if ($clusterId && isset($this->settingsByScope[SystemSetting::SCOPE_CLUSTER][$clusterId])) {
            return $this->settingsByScope[SystemSetting::SCOPE_CLUSTER][$clusterId];
        }

        if ($regionId && isset($this->settingsByScope[SystemSetting::SCOPE_REGION][$regionId])) {
            return $this->settingsByScope[SystemSetting::SCOPE_REGION][$regionId];
        }

        if ($divisionId && isset($this->settingsByScope[SystemSetting::SCOPE_DIVISION][$divisionId])) {
            return $this->settingsByScope[SystemSetting::SCOPE_DIVISION][$divisionId];
        }

        if ($badanusahaId && isset($this->settingsByScope[SystemSetting::SCOPE_BADANUSAHA][$badanusahaId])) {
            return $this->settingsByScope[SystemSetting::SCOPE_BADANUSAHA][$badanusahaId];
        }

        return $this->globalSetting;
    }

    public function allowsRegisterVisitForIds(?int $badanusahaId, ?int $divisionId, ?int $regionId, ?int $clusterId): bool
    {
        return (bool) ($this->resolveForIds($badanusahaId, $divisionId, $regionId, $clusterId)?->allow_register_visit ?? false);
    }

    public function allowsRegisterVisitForModel(Register $register): bool
    {
        return $this->allowsRegisterVisitForIds(
            $register->badanusaha_id ? (int) $register->badanusaha_id : null,
            $register->divisi_id ? (int) $register->divisi_id : null,
            $register->region_id ? (int) $register->region_id : null,
            $register->cluster_id ? (int) $register->cluster_id : null,
        );
    }

    public function defaultRegisterRadiusForIds(
        ?int $badanusahaId,
        ?int $divisionId,
        ?int $regionId,
        ?int $clusterId,
        int $fallback = 100,
    ): int {
        $radius = (int) ($this->resolveForIds($badanusahaId, $divisionId, $regionId, $clusterId)?->default_register_radius ?? $fallback);

        return $radius > 0 ? $radius : $fallback;
    }

    public function planVisitMinDaysForIds(
        ?int $badanusahaId,
        ?int $divisionId,
        ?int $regionId,
        ?int $clusterId,
        int $fallback = 3,
    ): int {
        $days = (int) ($this->resolveForIds($badanusahaId, $divisionId, $regionId, $clusterId)?->plan_visit_min_days ?? $fallback);

        return max(0, $days);
    }

    public function hasAnyAllowRegisterVisit(): bool
    {
        return $this->hasAnyEnabled('allow_register_visit');
    }

    private function hasAnyEnabled(string $attribute): bool
    {
        $this->load();

        if ($this->globalSetting && (bool) $this->globalSetting->{$attribute}) {
            return true;
        }

        foreach ($this->settingsByScope as $settings) {
            foreach ($settings as $setting) {
                if ((bool) $setting->{$attribute}) {
                    return true;
                }
            }
        }

        return false;
    }

    private function load(): void
    {
        if ($this->isLoaded) {
            return;
        }

        $this->isLoaded = true;
        $this->globalSetting = null;
        $this->settingsByScope = [
            SystemSetting::SCOPE_BADANUSAHA => [],
            SystemSetting::SCOPE_DIVISION => [],
            SystemSetting::SCOPE_REGION => [],
            SystemSetting::SCOPE_CLUSTER => [],
        ];

        $settings = SystemSetting::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        foreach ($settings as $setting) {
            if ($setting->scope_level === SystemSetting::SCOPE_GLOBAL) {
                $this->globalSetting ??= $setting;

                continue;
            }

            $scopeId = $this->scopeIdForSetting($setting);
            if (! $scopeId) {
                continue;
            }

            if (! isset($this->settingsByScope[$setting->scope_level][$scopeId])) {
                $this->settingsByScope[$setting->scope_level][$scopeId] = $setting;
            }
        }
    }

    private function scopeIdForSetting(SystemSetting $setting): ?int
    {
        return match ($setting->scope_level) {
            SystemSetting::SCOPE_BADANUSAHA => $setting->badanusaha_id ? (int) $setting->badanusaha_id : null,
            SystemSetting::SCOPE_DIVISION => $setting->division_id ? (int) $setting->division_id : null,
            SystemSetting::SCOPE_REGION => $setting->region_id ? (int) $setting->region_id : null,
            SystemSetting::SCOPE_CLUSTER => $setting->cluster_id ? (int) $setting->cluster_id : null,
            default => null,
        };
    }
}
