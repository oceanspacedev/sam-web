<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Support\ImportOrganizationalResolver;

it('resolves organizational hierarchy with normalized names and caches lookups', function () {
    $badanUsaha = BadanUsaha::factory()->create(['code' => 'MSI', 'name' => 'MSI Group']);
    $division = Division::factory()->create([
        'code' => 'GROSIR',
        'name' => 'Grosir',
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'code' => 'JKT',
        'name' => 'Jakarta',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'code' => 'JKT-UTARA',
        'name' => 'Jakarta Utara',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    $resolver = new ImportOrganizationalResolver;

    expect($resolver->resolveBadanUsahaId('msi group'))->toBe($badanUsaha->id)
        ->and($resolver->resolveDivisionId('Grosir', $badanUsaha->id))->toBe($division->id)
        ->and($resolver->resolveRegionId('J K T', $division->id, $badanUsaha->id))->toBe($region->id)
        ->and($resolver->resolveClusterId('jakarta_utara', $badanUsaha->id, $division->id, $region->id))->toBe($cluster->id)
        ->and($resolver->resolveDivisionModel('GROSIR', $badanUsaha->id)->id)->toBe($division->id);
});

it('requires badan usaha when division name is ambiguous', function () {
    $buOne = BadanUsaha::factory()->create(['code' => 'BU1', 'name' => 'BU One']);
    $buTwo = BadanUsaha::factory()->create(['code' => 'BU2', 'name' => 'BU Two']);

    Division::factory()->create([
        'code' => 'SALES',
        'name' => 'Sales',
        'badanusaha_id' => $buOne->id,
    ]);

    Division::factory()->create([
        'code' => 'SALES',
        'name' => 'Sales',
        'badanusaha_id' => $buTwo->id,
    ]);

    $resolver = new ImportOrganizationalResolver;

    expect(fn () => $resolver->resolveDivisionModel('Sales'))
        ->toThrow(Exception::class, 'ditemukan di 2 badan usaha');
});

it('throws a descriptive error when organizational lookup fails', function () {
    $resolver = new ImportOrganizationalResolver;

    expect(fn () => $resolver->resolveBadanUsahaId('TIDAK ADA'))
        ->toThrow(Exception::class, "Badan usaha 'TIDAK ADA' tidak ditemukan.");
});
