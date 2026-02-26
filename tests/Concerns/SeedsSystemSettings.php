<?php

namespace Tests\Concerns;

use App\Models\SystemSetting;

trait SeedsSystemSettings
{
    protected function seedGlobalSystemSetting(array $overrides = []): SystemSetting
    {
        return SystemSetting::factory()->global()->create(array_merge([
            'allow_register_visit' => true,
            'default_register_radius' => 100,
            'plan_visit_min_days' => 3,
        ], $overrides));
    }
}
