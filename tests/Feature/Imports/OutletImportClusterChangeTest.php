<?php

use App\Imports\OutletImport;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Region;
use App\Models\User;
use Carbon\Carbon;

/**
 * @return array{0: Outlet, 1: Cluster, 2: Cluster, 3: array<string, string>}
 */
function outletClusterChangeFixture(): array
{
    $badanUsaha = BadanUsaha::factory()->create(['name' => 'BU-TEST']);
    $division = Division::factory()->create([
        'name' => 'DIV-TEST',
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'name' => 'REG-TEST',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);

    $clusterOld = Cluster::factory()->create([
        'name' => 'CLUSTER-OLD',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    $clusterNew = Cluster::factory()->create([
        'name' => 'CLUSTER-NEW',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    $outlet = Outlet::factory()->create([
        'kode_outlet' => 'OUT-001',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $clusterOld->id,
    ]);

    $row = [
        'badan_usaha' => 'BU-TEST',
        'divisi' => 'DIV-TEST',
        'region' => 'REG-TEST',
        'cluster' => 'CLUSTER-OLD',
        'cluster_baru' => 'CLUSTER-NEW',
        'kode_outlet' => 'OUT-001',
    ];

    return [$outlet, $clusterOld, $clusterNew, $row];
}

it('blocks outlet cluster changes on import when there are unrealized plan visits in the current week', function () {
    [$outlet, $clusterOld, , $row] = outletClusterChangeFixture();
    $user = User::factory()->create();
    $payload = PlanVisit::schedulePayload(now(), 'weekly');

    PlanVisit::create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'realized_at' => null,
        ...$payload,
    ]);

    $import = new OutletImport('update');

    expect(fn () => $import->model($row, 2))
        ->toThrow(Exception::class, 'Perubahan cluster ditolak');

    expect($outlet->refresh()->cluster_id)->toBe($clusterOld->id);
});

it('allows outlet cluster changes when unrealized plan visits are only in past weeks', function () {
    [$outlet, , $clusterNew, $row] = outletClusterChangeFixture();
    $user = User::factory()->create();
    $pastWeek = now()->startOfWeek(Carbon::MONDAY)->subWeek();
    $payload = PlanVisit::schedulePayload($pastWeek, 'weekly');

    PlanVisit::create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'realized_at' => null,
        ...$payload,
    ]);

    $import = new OutletImport('update');
    $import->model($row, 2);

    expect($outlet->refresh()->cluster_id)->toBe($clusterNew->id);
});

it('blocks outlet cluster changes when unrealized plan visits are in a future week', function () {
    [$outlet, $clusterOld, , $row] = outletClusterChangeFixture();
    $user = User::factory()->create();
    $nextWeek = now()->startOfWeek(Carbon::MONDAY)->addWeek();
    $payload = PlanVisit::schedulePayload($nextWeek, 'weekly');

    PlanVisit::create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'realized_at' => null,
        ...$payload,
    ]);

    $import = new OutletImport('update');

    expect(fn () => $import->model($row, 2))
        ->toThrow(Exception::class, 'Perubahan cluster ditolak');

    expect($outlet->refresh()->cluster_id)->toBe($clusterOld->id);
});

it('does not validate formula-looking existing values missing from update import rows', function () {
    $badanUsaha = BadanUsaha::factory()->create(['name' => 'BU-TEST']);
    $division = Division::factory()->create([
        'name' => 'DIV-TEST',
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'name' => 'REG-TEST',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'name' => 'CLUSTER-TEST',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    $outlet = Outlet::factory()->create([
        'kode_outlet' => 'OUT-001',
        'alamat_outlet' => '=ALAMAT LAMA',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
    ]);

    $import = new OutletImport('update');

    $import->model([
        'badan_usaha' => 'BU-TEST',
        'divisi' => 'DIV-TEST',
        'region' => 'REG-TEST',
        'cluster' => 'CLUSTER-TEST',
        'kode_outlet' => 'OUT-001',
    ], 2);

    expect($outlet->refresh()->alamat_outlet)->toBe('=ALAMAT LAMA');
});

it('treats dash placeholders from old error reports as blank update values', function () {
    $badanUsaha = BadanUsaha::factory()->create(['name' => 'BU-TEST']);
    $division = Division::factory()->create([
        'name' => 'DIV-TEST',
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'name' => 'REG-TEST',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'name' => 'CLUSTER-TEST',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    $outlet = Outlet::factory()->create([
        'kode_outlet' => 'OUT-001',
        'nama_outlet' => 'TOKO LAMA',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
    ]);

    $import = new OutletImport('update');

    $import->model([
        'badan_usaha' => 'BU-TEST',
        'divisi' => 'DIV-TEST',
        'region' => 'REG-TEST',
        'cluster' => 'CLUSTER-TEST',
        'kode_outlet' => 'OUT-001',
        'badan_usaha_baru' => '-',
        'divisi_baru' => '-',
        'region_baru' => '-',
        'cluster_baru' => '-',
        'nama_outlet_baru' => '-',
    ], 2);

    expect($outlet->refresh()->nama_outlet)->toBe('TOKO LAMA');
});

it('rejects invalid numeric outlet import values instead of silently defaulting them', function () {
    $badanUsaha = BadanUsaha::factory()->create(['name' => 'BU-TEST']);
    $division = Division::factory()->create([
        'name' => 'DIV-TEST',
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'name' => 'REG-TEST',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    Cluster::factory()->create([
        'name' => 'CLUSTER-TEST',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    $import = new OutletImport('create');

    expect(fn () => $import->model([
        'badan_usaha' => 'BU-TEST',
        'divisi' => 'DIV-TEST',
        'region' => 'REG-TEST',
        'cluster' => 'CLUSTER-TEST',
        'kode_outlet' => 'OUT-001',
        'nama_outlet' => 'TOKO BARU',
        'limit' => 'SALAH',
    ], 2))->toThrow(Exception::class, 'Kolom limit harus berupa angka.');
});
