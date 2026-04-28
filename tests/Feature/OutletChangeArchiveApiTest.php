<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\OutletChangeArchive;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

function outletArchiveHierarchy(): array
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

function outletArchiveUser(): User
{
    $permissions = collect(['Reset:Outlet', 'ResetLocation:Outlet', 'Update:Outlet'])
        ->map(fn (string $name): Permission => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

    $role = Role::factory()->create([
        'name' => 'OUTLET ARCHIVE ADMIN',
        'organizational_scope_level' => 'all',
    ]);
    $role->syncPermissions($permissions);

    $user = User::factory()->create(['role_id' => $role->id]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function outletArchiveViewerUser(): User
{
    $role = Role::factory()->create([
        'name' => 'OUTLET ARCHIVE VIEWER',
        'organizational_scope_level' => 'all',
    ]);

    return User::factory()->create(['role_id' => $role->id]);
}

test('reset data outlet requires reset permission', function (): void {
    $user = outletArchiveViewerUser();
    $outlet = Outlet::factory()->create(outletArchiveHierarchy() + [
        'last_reset_at' => null,
        'reset_count_yearly' => 0,
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/outlet/{$outlet->id}/reset")
        ->assertForbidden();

    expect(OutletChangeArchive::query()->count())->toBe(0);
});

test('reset location outlet requires reset location permission', function (): void {
    $user = outletArchiveViewerUser();
    $outlet = Outlet::factory()->create(outletArchiveHierarchy() + [
        'last_reset_at' => null,
        'reset_count_yearly' => 0,
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/outlet/{$outlet->id}/reset-location")
        ->assertForbidden();

    expect(OutletChangeArchive::query()->count())->toBe(0);
});

test('reset data outlet creates archive and can restore old values', function (): void {
    $user = outletArchiveUser();
    $hierarchy = outletArchiveHierarchy();
    $outlet = Outlet::factory()->create($hierarchy + [
        'kode_outlet' => 'ARCH-001',
        'alamat_outlet' => 'Alamat lama',
        'nama_pemilik_outlet' => 'Pemilik Lama',
        'nomer_tlp_outlet' => '081234567890',
        'latlong' => '-6.1,106.8',
        'poto_depan' => 'old-front.jpg',
        'video' => 'old-video.mp4',
        'last_reset_at' => null,
        'reset_count_yearly' => 0,
    ]);

    $resetResponse = $this->actingAs($user, 'sanctum')
        ->patchJson("/api/outlet/{$outlet->id}/reset");

    $resetResponse
        ->assertOk()
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('meta.archive_id', 1)
        ->assertJsonPath('data', null);

    $archive = OutletChangeArchive::query()->firstOrFail();

    expect($archive->action)->toBe(OutletChangeArchive::ACTION_RESET_DATA)
        ->and($archive->actor_user_id)->toBe($user->id)
        ->and($archive->old_values['nama_pemilik_outlet'])->toBe('Pemilik Lama')
        ->and($archive->old_values['poto_depan'])->toBe('old-front.jpg')
        ->and($archive->changed_fields)->toContain('nama_pemilik_outlet', 'latlong', 'poto_depan');

    $outlet->refresh();
    $resetAt = $outlet->last_reset_at;
    expect($outlet->nama_pemilik_outlet)->toBeNull()
        ->and($outlet->alamat_outlet)->toBe('-')
        ->and($outlet->latlong)->toBeNull()
        ->and($outlet->poto_depan)->toBeNull()
        ->and($outlet->reset_count_yearly)->toBe(1);

    $restoreResponse = $this->actingAs($user, 'sanctum')
        ->patchJson("/api/outlet/{$outlet->id}/archives/{$archive->id}/restore");

    $restoreResponse
        ->assertOk()
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('meta.restored_from_id', $archive->id)
        ->assertJsonPath('data.nama_pemilik_outlet', 'Pemilik Lama')
        ->assertJsonPath('data.alamat_outlet', 'Alamat lama')
        ->assertJsonPath('data.latlong', '-6.1,106.8')
        ->assertJsonPath('data.poto_depan', 'old-front.jpg');

    $archive->refresh();
    $outlet->refresh();
    expect($archive->restored_by_user_id)->toBe($user->id)
        ->and($archive->restored_at)->not->toBeNull()
        ->and(OutletChangeArchive::query()->where('action', OutletChangeArchive::ACTION_RESTORE)->exists())->toBeTrue()
        ->and($outlet->reset_count_yearly)->toBe(1)
        ->and($outlet->last_reset_at?->timestamp)->toBe($resetAt?->timestamp);
});

test('reset location outlet archives location and supporting media only', function (): void {
    $user = outletArchiveUser();
    $hierarchy = outletArchiveHierarchy();
    $outlet = Outlet::factory()->create($hierarchy + [
        'alamat_outlet' => 'Alamat GPS lama',
        'nama_pemilik_outlet' => 'Pemilik Tetap',
        'latlong' => '-6.2,106.9',
        'poto_shop_sign' => 'old-sign.jpg',
        'poto_ktp' => 'old-ktp.jpg',
        'last_reset_at' => null,
        'reset_count_yearly' => 0,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->patchJson("/api/outlet/{$outlet->id}/reset-location");

    $response
        ->assertOk()
        ->assertJsonPath('data.archive_id', 1);

    $archive = OutletChangeArchive::query()->firstOrFail();

    expect($archive->action)->toBe(OutletChangeArchive::ACTION_RESET_LOCATION)
        ->and($archive->old_values['latlong'])->toBe('-6.2,106.9')
        ->and($archive->old_values['poto_shop_sign'])->toBe('old-sign.jpg')
        ->and($archive->old_values['poto_ktp'])->toBe('old-ktp.jpg');

    $outlet->refresh();
    expect($outlet->latlong)->toBeNull()
        ->and($outlet->alamat_outlet)->toBe('-')
        ->and($outlet->nama_pemilik_outlet)->toBe('Pemilik Tetap')
        ->and($outlet->poto_shop_sign)->toBeNull()
        ->and($outlet->poto_ktp)->toBe('old-ktp.jpg');
});

test('outlet change archives endpoint returns paginated history', function (): void {
    $user = outletArchiveUser();
    $hierarchy = outletArchiveHierarchy();
    $outlet = Outlet::factory()->create($hierarchy);

    OutletChangeArchive::query()->create([
        'outlet_id' => $outlet->id,
        'kode_outlet' => $outlet->kode_outlet,
        'action' => OutletChangeArchive::ACTION_UPDATE,
        'actor_user_id' => $user->id,
        'actor_name' => $user->nama_lengkap,
        'old_values' => $outlet->changeArchiveSnapshot(),
        'new_values' => $outlet->changeArchiveSnapshot(),
        'changed_fields' => ['alamat_outlet'],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/outlet/{$outlet->id}/archives?per_page=10");

    $response
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.action', OutletChangeArchive::ACTION_UPDATE)
        ->assertJsonPath('data.0.actor.id', $user->id);
});

test('manual outlet update creates change archive', function (): void {
    $user = outletArchiveUser();
    $hierarchy = outletArchiveHierarchy();
    $outlet = Outlet::factory()->create($hierarchy + [
        'alamat_outlet' => 'Alamat sebelum edit',
        'nama_pemilik_outlet' => 'Pemilik sebelum edit',
        'status_outlet' => 'MAINTAIN',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/outlet/{$outlet->id}", [
            'alamat_outlet' => 'Alamat setelah edit',
            'nama_pemilik_outlet' => 'Pemilik Baru',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('meta.status', 'success');

    $archive = OutletChangeArchive::query()->firstOrFail();

    expect($archive->action)->toBe(OutletChangeArchive::ACTION_UPDATE)
        ->and($archive->old_values['alamat_outlet'])->toBe('Alamat sebelum edit')
        ->and($archive->new_values['alamat_outlet'])->toBe('Alamat setelah edit')
        ->and($archive->old_values['nama_pemilik_outlet'])->toBe('Pemilik sebelum edit')
        ->and($archive->new_values['nama_pemilik_outlet'])->toBe('PEMILIK BARU')
        ->and($archive->changed_fields)->toContain('alamat_outlet', 'nama_pemilik_outlet');
});
