<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Support\FilamentOrganizationalScope;
use App\Support\OperationalDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts outlet needsAttention as the union, not max', function () {
    $role = Role::factory()->create(['organizational_scope_level' => 'all']);
    $user = User::factory()->create(['role_id' => $role->id]);

    // Outlet A: hanya lokasi hilang (media lengkap).
    Outlet::factory()->create([
        'latlong' => null,
        'poto_shop_sign' => 'x.jpg',
        'poto_depan' => 'x.jpg',
        'poto_kiri' => 'x.jpg',
        'poto_kanan' => 'x.jpg',
        'poto_ktp' => 'x.jpg',
    ]);

    // Outlet B: hanya media hilang (lokasi ada).
    Outlet::factory()->create([
        'latlong' => '-6.2,106.8',
        'poto_shop_sign' => '-',
        'poto_depan' => 'x.jpg',
        'poto_kiri' => 'x.jpg',
        'poto_kanan' => 'x.jpg',
        'poto_ktp' => 'x.jpg',
    ]);

    $data = app(OperationalDashboardData::class);

    $method = new ReflectionMethod($data, 'outletSummary');
    $method->setAccessible(true);
    $outlets = $method->invoke($data, $user);

    expect($outlets['missingLocation'])->toBe(1)
        ->and($outlets['missingMedia'])->toBe(1)
        ->and($outlets['needsAttention'])->toBe(2); // union, bukan max(1,1)=1
});

it('does not over-restrict outlets by stale finer-level pivot for a coarser scope', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create(['badanusaha_id' => $badanUsaha->id]);
    $region = Region::factory()->create(['divisi_id' => $divisi->id]);
    $cluster1 = Cluster::factory()->create(['region_id' => $region->id]);
    $cluster2 = Cluster::factory()->create(['region_id' => $region->id]);

    // Region-scoped user yang masih punya baris cluster pivot (cluster1) yang tertinggal.
    $role = Role::factory()->create(['organizational_scope_level' => 'region']);
    $user = User::factory()->create(['role_id' => $role->id]);
    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster1->id); // stale finer-level pivot

    $outletInCluster1 = Outlet::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $divisi->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster1->id,
    ]);
    $outletInCluster2 = Outlet::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $divisi->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster2->id,
    ]);

    // Path dashboard (applyDirectColumns) harus melihat kedua outlet (region scope),
    // tidak terpatok oleh pivot cluster1.
    $count = FilamentOrganizationalScope::applyDirectColumns(Outlet::query(), $user, 'outlets')->count();

    expect($count)->toBe(2);
});