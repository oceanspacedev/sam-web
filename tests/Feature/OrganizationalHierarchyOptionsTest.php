<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationalHierarchyOptions;
use App\Support\OrganizationalName;
use Illuminate\Database\QueryException;

it('returns scoped hierarchy options for non-all scope user', function (): void {
    $role = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
        'can_access_web' => true,
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $buA = BadanUsaha::factory()->create();
    $buB = BadanUsaha::factory()->create();

    $divisionA = Division::factory()->create([
        'badanusaha_id' => $buA->id,
    ]);
    Division::factory()->create([
        'badanusaha_id' => $buB->id,
    ]);

    $regionA = Region::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
    ]);
    Region::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
    ]);

    $clusterA = Cluster::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
        'region_id' => $regionA->id,
    ]);
    Cluster::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
        'region_id' => $regionA->id,
    ]);

    $user->badanUsahas()->sync([$buA->id]);
    $user->divisis()->sync([$divisionA->id]);
    $user->regions()->sync([$regionA->id]);
    $user->clusters()->sync([$clusterA->id]);

    $badanUsahaOptions = OrganizationalHierarchyOptions::badanUsaha($user);
    $divisionOptions = OrganizationalHierarchyOptions::division($buA->id, $user);
    $regionOptions = OrganizationalHierarchyOptions::region($divisionA->id, $user);
    $clusterOptions = OrganizationalHierarchyOptions::cluster($regionA->id, $user);

    expect($badanUsahaOptions)->toHaveKey($buA->id)
        ->and($badanUsahaOptions)->not->toHaveKey($buB->id)
        ->and($divisionOptions)->toEqual([$divisionA->id => OrganizationalName::label($divisionA)])
        ->and($regionOptions)->toEqual([$regionA->id => OrganizationalName::label($regionA)])
        ->and($clusterOptions)->toEqual([$clusterA->id => OrganizationalName::label($clusterA)]);
});

it('returns all active options for all scope user and unrestricted helpers', function (): void {
    $role = Role::factory()->create([
        'organizational_scope_level' => 'all',
        'can_access_web' => true,
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $activeBu = BadanUsaha::factory()->create();
    $deletedBu = BadanUsaha::factory()->create();
    $deletedBu->delete();

    $activeDivision = Division::factory()->create([
        'badanusaha_id' => $activeBu->id,
    ]);
    $activeRegion = Region::factory()->create([
        'badanusaha_id' => $activeBu->id,
        'divisi_id' => $activeDivision->id,
    ]);
    $activeCluster = Cluster::factory()->create([
        'badanusaha_id' => $activeBu->id,
        'divisi_id' => $activeDivision->id,
        'region_id' => $activeRegion->id,
    ]);

    expect(OrganizationalHierarchyOptions::badanUsaha($user))
        ->toHaveKey($activeBu->id)
        ->not->toHaveKey($deletedBu->id);

    expect(OrganizationalHierarchyOptions::activeBadanUsaha())
        ->toHaveKey($activeBu->id)
        ->not->toHaveKey($deletedBu->id);

    expect(OrganizationalHierarchyOptions::activeDivision($activeBu->id))
        ->toEqual([$activeDivision->id => OrganizationalName::label($activeDivision)]);

    expect(OrganizationalHierarchyOptions::activeRegion($activeDivision->id))
        ->toEqual([$activeRegion->id => OrganizationalName::label($activeRegion)]);

    expect(OrganizationalHierarchyOptions::activeCluster($activeRegion->id))
        ->toEqual([$activeCluster->id => OrganizationalName::label($activeCluster)]);
});

it('allows duplicate division names and codes in different parents', function (): void {
    $firstBadanUsaha = BadanUsaha::factory()->create();
    $secondBadanUsaha = BadanUsaha::factory()->create();

    $firstDivision = Division::factory()->create([
        'badanusaha_id' => $firstBadanUsaha->id,
        'code' => 'SALES',
        'name' => 'Sales',
    ]);

    $secondDivision = Division::factory()->create([
        'badanusaha_id' => $secondBadanUsaha->id,
        'code' => 'SALES',
        'name' => 'Sales',
    ]);

    expect($firstDivision->id)->not->toBe($secondDivision->id);
});

it('rejects duplicate division names or codes in the same parent', function (): void {
    $badanUsaha = BadanUsaha::factory()->create();

    Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'code' => 'SALES',
        'name' => 'Sales',
    ]);

    expect(fn () => Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'code' => 'SALES',
        'name' => 'Sales Barat',
    ]))->toThrow(QueryException::class);

    expect(fn () => Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'code' => 'SALES_TIMUR',
        'name' => 'Sales',
    ]))->toThrow(QueryException::class);
});

it('searches and labels options by code or descriptive name', function (): void {
    $role = Role::factory()->create([
        'organizational_scope_level' => 'all',
        'can_access_web' => true,
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $badanUsaha = BadanUsaha::factory()->create([
        'code' => 'PT_MAJU',
        'name' => 'PT Maju Bersama',
    ]);

    expect(OrganizationalHierarchyOptions::searchBadanUsaha('PT_MAJU', $user))
        ->toEqual([$badanUsaha->id => 'PT_MAJU - PT Maju Bersama']);

    expect(OrganizationalHierarchyOptions::searchBadanUsaha('Maju Bersama', $user))
        ->toEqual([$badanUsaha->id => 'PT_MAJU - PT Maju Bersama']);
});
