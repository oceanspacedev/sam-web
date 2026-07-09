<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationalEffectiveGrants;

test('effective grants keep full divisi when sibling regions are partial', function () {
    $bu = BadanUsaha::factory()->create();
    $samsung = Division::factory()->create(['badanusaha_id' => $bu->id, 'name' => 'Samsung']);
    $oraimo = Division::factory()->create(['badanusaha_id' => $bu->id, 'name' => 'Oraimo']);

    $regionA = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);
    $regionB = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);
    $regionC = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);
    $regionOutside = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);
    $oraimoRegion = Region::factory()->create(['divisi_id' => $oraimo->id, 'badanusaha_id' => $bu->id]);

    $grants = OrganizationalEffectiveGrants::fromAssignments([
        'badanusaha' => [$bu->id],
        'divisi' => [$samsung->id, $oraimo->id],
        'region' => [$regionA->id, $regionB->id, $regionC->id],
        'cluster' => [],
    ]);

    expect($grants['divisi'])->toEqual([$oraimo->id])
        ->and($grants['region'])->toEqualCanonicalizing([$regionA->id, $regionB->id, $regionC->id])
        ->and($grants['cluster'])->toBe([])
        ->and($grants['badanusaha'])->toBe([]);
});

test('region-scoped user with mixed samsung regions and full oraimo sees OR outlets', function () {
    $bu = BadanUsaha::factory()->create();
    $samsung = Division::factory()->create(['badanusaha_id' => $bu->id, 'name' => 'Samsung']);
    $oraimo = Division::factory()->create(['badanusaha_id' => $bu->id, 'name' => 'Oraimo']);

    $regionA = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);
    $regionB = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);
    $regionC = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);
    $regionD = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);
    $regionOutside = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);

    $oraimoRegion1 = Region::factory()->create(['divisi_id' => $oraimo->id, 'badanusaha_id' => $bu->id]);
    $oraimoRegion2 = Region::factory()->create(['divisi_id' => $oraimo->id, 'badanusaha_id' => $bu->id]);
    $oraimoCluster1 = Cluster::factory()->create([
        'region_id' => $oraimoRegion1->id,
        'divisi_id' => $oraimo->id,
        'badanusaha_id' => $bu->id,
    ]);
    $oraimoCluster2 = Cluster::factory()->create([
        'region_id' => $oraimoRegion2->id,
        'divisi_id' => $oraimo->id,
        'badanusaha_id' => $bu->id,
    ]);

    $role = Role::factory()->create(['organizational_scope_level' => 'region']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->badanUsahas()->attach($bu->id);
    $user->divisis()->attach([$samsung->id, $oraimo->id]);
    $user->regions()->attach([$regionA->id, $regionB->id, $regionC->id, $regionD->id]);
    $user->forgetOrganizationalIdsCache();

    $outletA = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $samsung->id,
        'region_id' => $regionA->id,
    ]);
    $outletOutside = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $samsung->id,
        'region_id' => $regionOutside->id,
    ]);
    $outletOraimo1 = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $oraimo->id,
        'region_id' => $oraimoRegion1->id,
        'cluster_id' => $oraimoCluster1->id,
    ]);
    $outletOraimo2 = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $oraimo->id,
        'region_id' => $oraimoRegion2->id,
        'cluster_id' => $oraimoCluster2->id,
    ]);

    $visible = Outlet::query()->visibleTo($user)->get();

    expect($visible->contains($outletA))->toBeTrue()
        ->and($visible->contains($outletOraimo1))->toBeTrue()
        ->and($visible->contains($outletOraimo2))->toBeTrue()
        ->and($visible->contains($outletOutside))->toBeFalse()
        ->and($outletA->isVisibleTo($user))->toBeTrue()
        ->and($outletOutside->isVisibleTo($user))->toBeFalse()
        ->and($outletOraimo1->isVisibleTo($user))->toBeTrue();
});

test('region grant includes all child clusters under those regions', function () {
    $bu = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create(['badanusaha_id' => $bu->id]);
    $regionA = Region::factory()->create(['divisi_id' => $divisi->id, 'badanusaha_id' => $bu->id]);
    $regionB = Region::factory()->create(['divisi_id' => $divisi->id, 'badanusaha_id' => $bu->id]);
    $regionOutside = Region::factory()->create(['divisi_id' => $divisi->id, 'badanusaha_id' => $bu->id]);

    $clusterA1 = Cluster::factory()->create([
        'region_id' => $regionA->id,
        'divisi_id' => $divisi->id,
        'badanusaha_id' => $bu->id,
    ]);
    $clusterA2 = Cluster::factory()->create([
        'region_id' => $regionA->id,
        'divisi_id' => $divisi->id,
        'badanusaha_id' => $bu->id,
    ]);
    $clusterB1 = Cluster::factory()->create([
        'region_id' => $regionB->id,
        'divisi_id' => $divisi->id,
        'badanusaha_id' => $bu->id,
    ]);
    $clusterOutside = Cluster::factory()->create([
        'region_id' => $regionOutside->id,
        'divisi_id' => $divisi->id,
        'badanusaha_id' => $bu->id,
    ]);

    $role = Role::factory()->create(['organizational_scope_level' => 'region']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->badanUsahas()->attach($bu->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach([$regionA->id, $regionB->id]);
    $user->forgetOrganizationalIdsCache();

    $outletA1 = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $divisi->id,
        'region_id' => $regionA->id,
        'cluster_id' => $clusterA1->id,
    ]);
    $outletA2 = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $divisi->id,
        'region_id' => $regionA->id,
        'cluster_id' => $clusterA2->id,
    ]);
    $outletB1 = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $divisi->id,
        'region_id' => $regionB->id,
        'cluster_id' => $clusterB1->id,
    ]);
    $outletOutside = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $divisi->id,
        'region_id' => $regionOutside->id,
        'cluster_id' => $clusterOutside->id,
    ]);

    $visible = Outlet::query()->visibleTo($user)->get();
    $expanded = $user->getExpandedOrganizationalIds();
    $visibleClusters = Cluster::query()
        ->tap(fn ($q) => \App\Support\OrganizationalManagementScope::applyClusterQuery($q, $user))
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($visible->contains($outletA1))->toBeTrue()
        ->and($visible->contains($outletA2))->toBeTrue()
        ->and($visible->contains($outletB1))->toBeTrue()
        ->and($visible->contains($outletOutside))->toBeFalse()
        ->and($expanded['cluster'])->toEqualCanonicalizing([$clusterA1->id, $clusterA2->id, $clusterB1->id])
        ->and($visibleClusters)->toEqualCanonicalizing([$clusterA1->id, $clusterA2->id, $clusterB1->id]);
});

test('cluster-scoped user still only sees assigned clusters', function () {
    $bu = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create(['badanusaha_id' => $bu->id]);
    $region = Region::factory()->create(['divisi_id' => $divisi->id, 'badanusaha_id' => $bu->id]);
    $cluster1 = Cluster::factory()->create([
        'region_id' => $region->id,
        'divisi_id' => $divisi->id,
        'badanusaha_id' => $bu->id,
    ]);
    $cluster2 = Cluster::factory()->create([
        'region_id' => $region->id,
        'divisi_id' => $divisi->id,
        'badanusaha_id' => $bu->id,
    ]);

    $role = Role::factory()->create(['organizational_scope_level' => 'cluster']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->badanUsahas()->attach($bu->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster1->id);
    $user->forgetOrganizationalIdsCache();

    $outlet1 = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $divisi->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster1->id,
    ]);
    $outlet2 = Outlet::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $divisi->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster2->id,
    ]);

    $visible = Outlet::query()->visibleTo($user)->get();

    expect($visible->contains($outlet1))->toBeTrue()
        ->and($visible->contains($outlet2))->toBeFalse();
});

test('prune hierarchy preserves mixed full divisi and partial regions', function () {
    $bu = BadanUsaha::factory()->create();
    $samsung = Division::factory()->create(['badanusaha_id' => $bu->id]);
    $oraimo = Division::factory()->create(['badanusaha_id' => $bu->id]);
    $regionA = Region::factory()->create(['divisi_id' => $samsung->id, 'badanusaha_id' => $bu->id]);

    $role = Role::factory()->create(['organizational_scope_level' => 'region']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->badanUsahas()->attach($bu->id);
    $user->divisis()->attach([$samsung->id, $oraimo->id]);
    $user->regions()->attach([$regionA->id]);

    \App\Filament\Resources\Users\UserResource::pruneInconsistentOrganizationalHierarchy($user);

    expect($user->divisis()->pluck('divisions.id')->map(fn ($id) => (int) $id)->sort()->values()->all())
        ->toEqualCanonicalizing([$samsung->id, $oraimo->id])
        ->and($user->regions()->pluck('regions.id')->map(fn ($id) => (int) $id)->all())
        ->toEqual([$regionA->id])
        ->and($user->badanUsahas()->pluck('badan_usahas.id')->map(fn ($id) => (int) $id)->all())
        ->toEqual([$bu->id]);
});
