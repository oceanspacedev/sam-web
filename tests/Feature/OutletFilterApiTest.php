<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;

function outletFilterHierarchy(string $suffix): array
{
    $business = BadanUsaha::factory()->create(['name' => 'BU Filter '.$suffix]);
    $division = Division::factory()->create([
        'name' => 'DIV Filter '.$suffix,
        'badanusaha_id' => $business->id,
    ]);
    $region = Region::factory()->create([
        'name' => 'REG Filter '.$suffix,
        'badanusaha_id' => $business->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'name' => 'CLUS Filter '.$suffix,
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

function outletFilterUser(): User
{
    $role = Role::factory()->create(['organizational_scope_level' => 'all']);

    return User::factory()->create(['role_id' => $role->id]);
}

test('outlet list applies search, status, and hierarchy filters with AND logic', function () {
    $user = outletFilterUser();
    $targetHierarchy = outletFilterHierarchy('Target');
    $otherHierarchy = outletFilterHierarchy('Other');

    $target = Outlet::factory()->create($targetHierarchy + [
        'kode_outlet' => '029.062',
        'nama_outlet' => 'AKMAL PONSEL',
        'alamat_outlet' => 'PADANG LAWAS',
        'status_outlet' => 'UNPRODUCTIVE',
    ]);

    Outlet::factory()->create($targetHierarchy + [
        'kode_outlet' => '029.063',
        'nama_outlet' => 'AKMAL ACTIVE',
        'alamat_outlet' => 'PADANG LAWAS',
        'status_outlet' => 'MAINTAIN',
    ]);

    Outlet::factory()->create($otherHierarchy + [
        'kode_outlet' => '029.064',
        'nama_outlet' => 'AKMAL OTHER',
        'alamat_outlet' => 'PADANG LAWAS',
        'status_outlet' => 'UNPRODUCTIVE',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/outlet?compact=0&page=1&per_page=10&search=akmal&status_outlet=UNPRODUCTIVE&badanusaha_id='.
            $targetHierarchy['badanusaha_id'].'&divisi_id='.$targetHierarchy['divisi_id'].'&region_id='.
            $targetHierarchy['region_id'].'&cluster_id='.$targetHierarchy['cluster_id']);

    $response->assertOk()
        ->assertJsonPath('meta.pagination.current_page', 1)
        ->assertJsonPath('meta.pagination.per_page', 10)
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $target->id)
        ->assertJsonPath('data.0.kode_outlet', '029.062')
        ->assertJsonPath('data.0.nama_outlet', 'AKMAL PONSEL')
        ->assertJsonPath('data.0.alamat_outlet', 'PADANG LAWAS')
        ->assertJsonPath('data.0.status_outlet', 'UNPRODUCTIVE');
});

test('outlet status filter accepts frontend spelling aliases', function () {
    $user = outletFilterUser();
    $hierarchy = outletFilterHierarchy('StatusAlias');

    $maintainOutlet = Outlet::factory()->create($hierarchy + [
        'kode_outlet' => 'MAINTAIN-001',
        'nama_outlet' => 'Maintain Outlet',
        'status_outlet' => 'MAINTAIN',
    ]);

    Outlet::factory()->create($hierarchy + [
        'kode_outlet' => 'UNPRODUCTIVE-001',
        'nama_outlet' => 'Unproductive Outlet',
        'status_outlet' => 'UNPRODUCTIVE',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/outlet?compact=1&status_outlet=MAINTANCE&per_page=10');

    $response->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $maintainOutlet->id)
        ->assertJsonPath('data.0.status_outlet', 'MAINTAIN');
});

test('outlet list rejects invalid status filters', function () {
    $user = outletFilterUser();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/outlet?status_outlet=INVALID');

    $response->assertStatus(422)
        ->assertJsonPath('meta.code', 422)
        ->assertJsonPath('meta.status', 'error')
        ->assertJsonValidationErrors(['status_outlet']);
});

test('outlet list caps per page at fifty', function () {
    $user = outletFilterUser();
    $hierarchy = outletFilterHierarchy('PaginationCap');

    Outlet::factory()
        ->count(55)
        ->create($hierarchy + ['status_outlet' => 'MAINTAIN']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/outlet?compact=1&page=1&per_page=100');

    $response->assertOk()
        ->assertJsonPath('meta.pagination.current_page', 1)
        ->assertJsonPath('meta.pagination.per_page', 50)
        ->assertJsonPath('meta.pagination.total', 55)
        ->assertJsonCount(50, 'data');
});
