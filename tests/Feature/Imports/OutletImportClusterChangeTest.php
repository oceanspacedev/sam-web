<?php

use App\Imports\OutletImport;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Region;
use App\Models\User;

it('blocks outlet cluster changes on import when there are unrealized plan visits', function () {
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

    $user = User::factory()->create();

    PlanVisit::create([
        'user_id' => $user->id,
        'outlet_id' => $outlet->id,
        'tanggal_visit' => now(),
        'realized_at' => null,
    ]);

    $import = new OutletImport('update');

    $row = [
        'badan_usaha' => 'BU-TEST',
        'divisi' => 'DIV-TEST',
        'region' => 'REG-TEST',
        'cluster' => 'CLUSTER-OLD',
        'cluster_baru' => 'CLUSTER-NEW',
        'kode_outlet' => 'OUT-001',
    ];

    expect(fn () => $import->model($row, 2))
        ->toThrow(Exception::class, 'Perubahan cluster ditolak');

    expect($outlet->refresh()->cluster_id)->toBe($clusterOld->id);
});
