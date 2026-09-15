<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Spatie\Permission\PermissionRegistrar;

function visitDetailHierarchy(string $suffix = ''): array
{
    $business = BadanUsaha::factory()->create(['name' => trim('Visit Detail BU '.$suffix)]);
    $division = Division::factory()->create([
        'name' => trim('Visit Detail DIV '.$suffix),
        'badanusaha_id' => $business->id,
    ]);
    $region = Region::factory()->create([
        'name' => trim('Visit Detail REG '.$suffix),
        'badanusaha_id' => $business->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'name' => trim('Visit Detail CLUS '.$suffix),
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

function visitDetailUser(array $roleAttributes = []): User
{
    $role = Role::factory()->create(array_merge([
        'organizational_scope_level' => 'cluster',
    ], $roleAttributes));

    return User::factory()->create(['role_id' => $role->id]);
}

function visitDetailMonitorUser(): User
{
    $permission = Permission::firstOrCreate([
        'name' => 'ViewAny:Visit',
        'guard_name' => 'web',
    ]);

    $role = Role::factory()->create([
        'name' => 'VISIT DETAIL MONITOR',
        'organizational_scope_level' => 'all',
    ]);
    $role->syncPermissions([$permission]);

    $user = User::factory()->create(['role_id' => $role->id]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

test('visit owner can fetch visit detail', function (): void {
    $user = visitDetailUser();
    $outlet = Outlet::factory()->create(visitDetailHierarchy('Owner') + [
        'kode_outlet' => 'VISIT-DETAIL-001',
        'nama_outlet' => 'Outlet Visit Detail',
        'alamat_outlet' => 'Alamat Visit Detail',
    ]);
    $visit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => Carbon::parse('2026-04-28')->toDateString(),
        'check_in_time' => Carbon::parse('2026-04-28 09:15:00'),
        'check_out_time' => Carbon::parse('2026-04-28 10:00:00'),
        'latlong_in' => '-6.100000,106.800000',
        'latlong_out' => '-6.100500,106.800500',
        'laporan_visit' => 'Laporan detail visit',
        'transaksi' => 3,
        'durasi_visit' => 45,
        'picture_visit_in' => 'vi-test-in.jpg',
        'picture_visit_out' => 'vo-test-out.jpg',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/visit/{$visit->id}")
        ->assertOk()
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.id', $visit->id)
        ->assertJsonPath('data.visitable_type', 'outlet')
        ->assertJsonPath('data.outlet.id', $outlet->id)
        ->assertJsonPath('data.outlet.kode_outlet', 'VISIT-DETAIL-001')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.laporan_visit', 'Laporan detail visit')
        ->assertJsonPath('data.picture_visit_in', 'vi-test-in.jpg')
        ->assertJsonPath('data.picture_visit_in_url', \App\Support\StorageDisk::url('vi-test-in.jpg'))
        ->assertJsonPath('data.picture_visit_out_url', \App\Support\StorageDisk::url('vo-test-out.jpg'));
});

test('non owner without monitor permission cannot fetch another user visit detail', function (): void {
    $owner = visitDetailUser(['organizational_scope_level' => 'all']);
    $otherUser = visitDetailUser(['organizational_scope_level' => 'all']);
    $outlet = Outlet::factory()->create(visitDetailHierarchy('Forbidden'));
    $visit = Visit::factory()->create([
        'user_id' => $owner->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
    ]);

    $this->actingAs($otherUser, 'sanctum')
        ->getJson("/api/visit/{$visit->id}")
        ->assertStatus(404)
        ->assertJsonPath('meta.code', 404);
});

test('monitor user can fetch another user visit detail', function (): void {
    $monitor = visitDetailMonitorUser();
    $owner = visitDetailUser(['organizational_scope_level' => 'cluster']);
    $outlet = Outlet::factory()->create(visitDetailHierarchy('Monitor') + [
        'kode_outlet' => 'VISIT-DETAIL-002',
        'nama_outlet' => 'Outlet Monitor Detail',
    ]);
    $visit = Visit::factory()->create([
        'user_id' => $owner->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
    ]);

    $this->actingAs($monitor, 'sanctum')
        ->getJson("/api/visit/{$visit->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $visit->id)
        ->assertJsonPath('data.user.id', $owner->id)
        ->assertJsonPath('data.outlet.kode_outlet', 'VISIT-DETAIL-002');
});

test('monitor visit detail lookup ignores current cluster assignments', function (): void {
    $permission = Permission::firstOrCreate([
        'name' => 'ViewAny:Visit',
        'guard_name' => 'web',
    ]);

    $role = Role::factory()->create([
        'name' => 'VISIT DETAIL CLUSTER MONITOR',
        'organizational_scope_level' => 'cluster',
    ]);
    $role->syncPermissions([$permission]);

    $monitorHierarchy = visitDetailHierarchy('Monitor Scope');
    $visitHierarchy = visitDetailHierarchy('Historical Target');

    $monitor = User::factory()->create(['role_id' => $role->id]);
    $monitor->assignRole($role);
    $monitor->clusters()->attach($monitorHierarchy['cluster_id']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $owner = visitDetailUser(['organizational_scope_level' => 'cluster']);
    $outlet = Outlet::factory()->create($visitHierarchy + [
        'kode_outlet' => 'VISIT-HISTORY-001',
        'nama_outlet' => 'Historical Visit Outlet',
    ]);
    $visit = Visit::factory()->create([
        'user_id' => $owner->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
    ]);

    $this->actingAs($monitor, 'sanctum')
        ->getJson("/api/visit/{$visit->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $visit->id)
        ->assertJsonPath('data.outlet.kode_outlet', 'VISIT-HISTORY-001');
});
