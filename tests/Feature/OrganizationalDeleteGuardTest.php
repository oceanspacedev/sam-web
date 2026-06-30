<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Register;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\OrganizationalDeleteGuard;
use Illuminate\Database\Eloquent\Collection;

function createHierarchy(): array
{
    $badanUsaha = BadanUsaha::factory()->create();
    $division = Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    return compact('badanUsaha', 'division', 'region', 'cluster');
}

it('allows deleting organizational records without dependencies', function (): void {
    $badanUsaha = BadanUsaha::factory()->create();
    $division = Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    expect(OrganizationalDeleteGuard::hasDependencies($cluster))->toBeFalse()
        ->and(OrganizationalDeleteGuard::hasDependencies($region))->toBeTrue()
        ->and(OrganizationalDeleteGuard::hasDependencies($division))->toBeTrue()
        ->and(OrganizationalDeleteGuard::hasDependencies($badanUsaha))->toBeTrue();

    OrganizationalDeleteGuard::deleteRecord($cluster);
    expect($cluster->fresh()->trashed())->toBeTrue()
        ->and(OrganizationalDeleteGuard::hasDependencies($region))->toBeFalse();

    OrganizationalDeleteGuard::deleteRecord($region);
    expect($region->fresh()->trashed())->toBeTrue()
        ->and(OrganizationalDeleteGuard::hasDependencies($division))->toBeFalse();

    OrganizationalDeleteGuard::deleteRecord($division);
    expect($division->fresh()->trashed())->toBeTrue()
        ->and(OrganizationalDeleteGuard::hasDependencies($badanUsaha))->toBeFalse();

    OrganizationalDeleteGuard::deleteRecord($badanUsaha);
    expect($badanUsaha->fresh()->trashed())->toBeTrue();
});

it('blocks badan usaha delete when child hierarchy or domain data exists', function (): void {
    ['badanUsaha' => $badanUsaha, 'division' => $division, 'region' => $region, 'cluster' => $cluster] = createHierarchy();

    expect(OrganizationalDeleteGuard::dependencies($badanUsaha))->toMatchArray([
        'Division' => 1,
        'Region' => 1,
        'Cluster' => 1,
    ]);

    $user = User::factory()->create(['role_id' => Role::factory()->create()->id]);
    $user->badanUsahas()->attach($badanUsaha->id);

    Outlet::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
    ]);

    Register::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
    ]);

    SystemSetting::factory()->forBadanUsaha($badanUsaha)->create();

    expect(OrganizationalDeleteGuard::dependencies($badanUsaha))->toMatchArray([
        'Division' => 1,
        'Region' => 1,
        'Cluster' => 1,
        'Outlet' => 1,
        'Register' => 1,
        'User' => 1,
        'System Setting' => 1,
    ])->and(OrganizationalDeleteGuard::blockMessage($badanUsaha))
        ->toContain('Tidak bisa menghapus')
        ->toContain('1 Division')
        ->toContain('1 Outlet');

    OrganizationalDeleteGuard::deleteRecord($badanUsaha);

    expect($badanUsaha->fresh()->trashed())->toBeFalse();
});

it('blocks division delete when related records exist', function (): void {
    ['badanUsaha' => $badanUsaha, 'division' => $division, 'region' => $region, 'cluster' => $cluster] = createHierarchy();

    $user = User::factory()->create(['role_id' => Role::factory()->create()->id]);
    $user->divisis()->attach($division->id);

    SystemSetting::factory()->forDivision($division)->create([
        'badanusaha_id' => $badanUsaha->id,
    ]);

    expect(OrganizationalDeleteGuard::dependencies($division))->toMatchArray([
        'Region' => 1,
        'Cluster' => 1,
        'User' => 1,
        'System Setting' => 1,
    ]);

    OrganizationalDeleteGuard::deleteRecord($division);

    expect($division->fresh()->trashed())->toBeFalse();
});

it('blocks region delete when clusters or domain data exist', function (): void {
    ['badanUsaha' => $badanUsaha, 'division' => $division, 'region' => $region, 'cluster' => $cluster] = createHierarchy();

    $user = User::factory()->create(['role_id' => Role::factory()->create()->id]);
    $user->regions()->attach($region->id);

    SystemSetting::factory()->forRegion($region)->create([
        'badanusaha_id' => $badanUsaha->id,
        'division_id' => $division->id,
    ]);

    expect(OrganizationalDeleteGuard::dependencies($region))->toMatchArray([
        'Cluster' => 1,
        'User' => 1,
        'System Setting' => 1,
    ]);

    OrganizationalDeleteGuard::deleteRecord($region);

    expect($region->fresh()->trashed())->toBeFalse();
});

it('blocks cluster delete when outlets registers users or settings exist', function (): void {
    ['badanUsaha' => $badanUsaha, 'division' => $division, 'region' => $region, 'cluster' => $cluster] = createHierarchy();

    $user = User::factory()->create(['role_id' => Role::factory()->create()->id]);
    $user->clusters()->attach($cluster->id);

    Outlet::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
    ]);

    Register::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
    ]);

    SystemSetting::factory()->forCluster($cluster)->create([
        'badanusaha_id' => $badanUsaha->id,
        'division_id' => $division->id,
        'region_id' => $region->id,
    ]);

    expect(OrganizationalDeleteGuard::dependencies($cluster))->toMatchArray([
        'Outlet' => 1,
        'Register' => 1,
        'User' => 1,
        'System Setting' => 1,
    ]);

    OrganizationalDeleteGuard::deleteRecord($cluster);

    expect($cluster->fresh()->trashed())->toBeFalse();
});

it('blocks bulk delete when any selected record still has dependencies', function (): void {
    $emptyBadanUsaha = BadanUsaha::factory()->create();
    ['badanUsaha' => $blockedBadanUsaha, 'division' => $division] = createHierarchy();

    $records = new Collection([$emptyBadanUsaha, $blockedBadanUsaha]);

    OrganizationalDeleteGuard::deleteRecords($records);

    expect($emptyBadanUsaha->fresh()->trashed())->toBeFalse()
        ->and($blockedBadanUsaha->fresh()->trashed())->toBeFalse()
        ->and(OrganizationalDeleteGuard::bulkBlockMessage(new Collection([$blockedBadanUsaha])))
        ->toContain(OrganizationalDeleteGuard::recordLabel($blockedBadanUsaha))
        ->toContain('1 Division');
});

it('blocks force delete when dependencies still exist', function (): void {
    ['badanUsaha' => $badanUsaha, 'division' => $division] = createHierarchy();

    $badanUsaha->delete();

    $records = new Collection([$badanUsaha->fresh()]);

    OrganizationalDeleteGuard::forceDeleteRecords($records);

    expect(BadanUsaha::withTrashed()->find($badanUsaha->id))->not->toBeNull()
        ->and(BadanUsaha::withTrashed()->find($badanUsaha->id)?->trashed())->toBeTrue()
        ->and(Division::query()->whereKey($division->id)->exists())->toBeTrue();
});
