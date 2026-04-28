<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Register;

function outletObserverHierarchy(): array
{
    $business = BadanUsaha::factory()->create();
    $division = Division::factory()->create(['badanusaha_id' => $business->id]);
    $region = Region::factory()->create([
        'badanusaha_id' => $business->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'badanusaha_id' => $business->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    return [
        'badanusaha_id' => $business->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
        'cluster_id' => $cluster->id,
    ];
}

test('outlet kode sync only updates the linked approved register', function () {
    $hierarchy = outletObserverHierarchy();

    $linkedRegister = Register::factory()->create($hierarchy + [
        'status' => 'APPROVED',
        'kode_outlet' => 'OLD-CODE',
    ]);

    $unrelatedRegisters = Register::factory()
        ->count(3)
        ->create($hierarchy + [
            'status' => 'APPROVED',
            'kode_outlet' => 'OLD-CODE',
        ]);

    $outlet = Outlet::withoutEvents(fn () => Outlet::factory()->create($hierarchy + [
        'register_id' => $linkedRegister->id,
        'kode_outlet' => 'OLD-CODE',
    ]));

    $outlet->update(['kode_outlet' => 'NEW-CODE']);

    expect($linkedRegister->refresh()->kode_outlet)->toBe('NEW-CODE');

    $unrelatedRegisters->each(function (Register $register): void {
        expect($register->refresh()->kode_outlet)->toBe('OLD-CODE');
    });
});

test('outlet without register id does not sync registers by matching kode outlet', function () {
    $hierarchy = outletObserverHierarchy();

    $register = Register::factory()->create($hierarchy + [
        'status' => 'APPROVED',
        'kode_outlet' => 'OLD-CODE',
    ]);

    $outlet = Outlet::withoutEvents(fn () => Outlet::factory()->create($hierarchy + [
        'register_id' => null,
        'kode_outlet' => 'OLD-CODE',
    ]));

    $outlet->update(['kode_outlet' => 'NEW-CODE']);

    expect($register->refresh()->kode_outlet)->toBe('OLD-CODE');
});
