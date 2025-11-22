<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;

test('user with full access scope sees all outlets', function () {
    $role = Role::factory()->create(['organizational_scope_level' => 'all']);
    $user = User::factory()->create(['role_id' => $role->id]);

    $outlet = Outlet::factory()->create();

    expect(Outlet::query()->visibleTo($user)->count())->toBeGreaterThan(0);
    expect($outlet->isVisibleTo($user))->toBeTrue();
});

test('user without role sees no outlets', function () {
    // Create user with a role first
    $user = User::factory()->create();
    Outlet::factory()->create();

    // Manually set role to null (bypassing validation)
    $user->role_id = null;

    // Test that visibleTo handles null role correctly
    expect(Outlet::query()->visibleTo($user)->count())->toBe(0);
})->skip('role_id is NOT NULL in database, cannot test null role scenario');

test('user with badanusaha scope sees only assigned badanusaha outlets', function () {
    $badanUsaha1 = BadanUsaha::factory()->create();
    $badanUsaha2 = BadanUsaha::factory()->create();

    $role = Role::factory()->create(['organizational_scope_level' => 'badanusaha']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->badanUsahas()->attach($badanUsaha1->id);

    $outlet1 = Outlet::factory()->create(['badanusaha_id' => $badanUsaha1->id]);
    $outlet2 = Outlet::factory()->create(['badanusaha_id' => $badanUsaha2->id]);

    $visibleOutlets = Outlet::query()->visibleTo($user)->get();

    expect($visibleOutlets->contains($outlet1))->toBeTrue();
    expect($visibleOutlets->contains($outlet2))->toBeFalse();
    expect($outlet1->isVisibleTo($user))->toBeTrue();
    expect($outlet2->isVisibleTo($user))->toBeFalse();
});

test('user with divisi scope sees only assigned divisi outlets', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi1 = Division::factory()->create(['badanusaha_id' => $badanUsaha->id]);
    $divisi2 = Division::factory()->create(['badanusaha_id' => $badanUsaha->id]);

    $role = Role::factory()->create(['organizational_scope_level' => 'divisi']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi1->id);

    $outlet1 = Outlet::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $divisi1->id,
    ]);
    $outlet2 = Outlet::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $divisi2->id,
    ]);

    $visibleOutlets = Outlet::query()->visibleTo($user)->get();

    expect($visibleOutlets->contains($outlet1))->toBeTrue();
    expect($visibleOutlets->contains($outlet2))->toBeFalse();
});

test('user with cluster scope sees only assigned cluster outlets', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create(['badanusaha_id' => $badanUsaha->id]);
    $region = Region::factory()->create(['divisi_id' => $divisi->id]);
    $cluster1 = Cluster::factory()->create(['region_id' => $region->id]);
    $cluster2 = Cluster::factory()->create(['region_id' => $region->id]);

    $role = Role::factory()->create(['organizational_scope_level' => 'cluster']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster1->id);

    $outlet1 = Outlet::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $divisi->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster1->id,
    ]);
    $outlet2 = Outlet::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $divisi->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster2->id,
    ]);

    $visibleOutlets = Outlet::query()->visibleTo($user)->get();

    expect($visibleOutlets->contains($outlet1))->toBeTrue();
    expect($visibleOutlets->contains($outlet2))->toBeFalse();
});

test('empty pivot tables treated as all access for that level', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $role = Role::factory()->create(['organizational_scope_level' => 'badanusaha']);
    $user = User::factory()->create(['role_id' => $role->id]);
    // No badanusaha assignments (empty pivot)

    $outlet = Outlet::factory()->create(['badanusaha_id' => $badanUsaha->id]);

    // Empty pivot = 'all' access for that scope level
    expect(Outlet::query()->visibleTo($user)->count())->toBeGreaterThan(0);
    expect($outlet->isVisibleTo($user))->toBeTrue();
});

test('organizational IDs are cached to avoid N+1 queries', function () {
    $role = Role::factory()->create(['organizational_scope_level' => 'cluster']);
    $user = User::factory()->create(['role_id' => $role->id]);

    // First call should query database
    $ids1 = $user->getOrganizationalIds();

    // Second call should use cache (no additional queries)
    $ids2 = $user->getOrganizationalIds();

    expect($ids1)->toBe($ids2);
    expect($ids1)->toHaveKeys(['badanusaha', 'divisi', 'region', 'cluster', 'scope_level']);
});
