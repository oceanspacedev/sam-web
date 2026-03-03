<?php

use App\Filament\Resources\SystemSettings\SystemSettingResource;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;

it('filters system settings by organizational scope for non-all users', function (): void {
    $regionRole = Role::factory()->create([
        'organizational_scope_level' => 'region',
        'can_access_web' => true,
    ]);

    $user = User::factory()->create([
        'role_id' => $regionRole->id,
    ]);

    $buA = BadanUsaha::factory()->create();
    $buB = BadanUsaha::factory()->create();

    $divisionA = Division::factory()->create(['badanusaha_id' => $buA->id]);
    $divisionB = Division::factory()->create(['badanusaha_id' => $buB->id]);

    $regionA = Region::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
    ]);
    $regionB = Region::factory()->create([
        'badanusaha_id' => $buB->id,
        'divisi_id' => $divisionB->id,
    ]);

    $clusterA = Cluster::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
        'region_id' => $regionA->id,
    ]);
    $clusterB = Cluster::factory()->create([
        'badanusaha_id' => $buB->id,
        'divisi_id' => $divisionB->id,
        'region_id' => $regionB->id,
    ]);

    $globalSetting = SystemSetting::factory()->global()->create();

    $buASetting = SystemSetting::factory()->forBadanUsaha($buA)->create();
    $buBSetting = SystemSetting::factory()->forBadanUsaha($buB)->create();

    $divisionASetting = SystemSetting::factory()->forDivision($divisionA)->create([
        'badanusaha_id' => $buA->id,
    ]);
    $divisionBSetting = SystemSetting::factory()->forDivision($divisionB)->create([
        'badanusaha_id' => $buB->id,
    ]);

    $regionASetting = SystemSetting::factory()->forRegion($regionA)->create([
        'badanusaha_id' => $buA->id,
        'division_id' => $divisionA->id,
    ]);
    $regionBSetting = SystemSetting::factory()->forRegion($regionB)->create([
        'badanusaha_id' => $buB->id,
        'division_id' => $divisionB->id,
    ]);

    $clusterASetting = SystemSetting::factory()->forCluster($clusterA)->create([
        'badanusaha_id' => $buA->id,
        'division_id' => $divisionA->id,
        'region_id' => $regionA->id,
    ]);
    $clusterBSetting = SystemSetting::factory()->forCluster($clusterB)->create([
        'badanusaha_id' => $buB->id,
        'division_id' => $divisionB->id,
        'region_id' => $regionB->id,
    ]);

    $user->badanUsahas()->sync([$buA->id]);
    $user->divisis()->sync([$divisionA->id]);
    $user->regions()->sync([$regionA->id]);

    $this->actingAs($user);

    $visibleIds = SystemSettingResource::getEloquentQuery()
        ->pluck('system_settings.id')
        ->all();

    expect($visibleIds)
        ->toContain($buASetting->id, $divisionASetting->id, $regionASetting->id, $clusterASetting->id)
        ->not->toContain($globalSetting->id)
        ->not->toContain($buBSetting->id, $divisionBSetting->id, $regionBSetting->id, $clusterBSetting->id);
});

it('shows all system settings for all-scope users', function (): void {
    $allRole = Role::factory()->create([
        'organizational_scope_level' => 'all',
        'can_access_web' => true,
    ]);

    $user = User::factory()->create([
        'role_id' => $allRole->id,
    ]);

    $settingIds = SystemSetting::factory()->count(3)->create()->pluck('id')->all();
    $globalSetting = SystemSetting::factory()->global()->create();

    $this->actingAs($user);

    $visibleIds = SystemSettingResource::getEloquentQuery()
        ->pluck('system_settings.id')
        ->all();

    expect($visibleIds)
        ->toContain(...$settingIds)
        ->toContain($globalSetting->id);
});
