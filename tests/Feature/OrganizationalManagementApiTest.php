<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

function orgManagementHierarchy(): array
{
    $badanUsaha = BadanUsaha::factory()->create(['code' => 'BU_A', 'name' => 'Badan Usaha A']);
    $otherBadanUsaha = BadanUsaha::factory()->create(['code' => 'BU_B', 'name' => 'Badan Usaha B']);
    $division = Division::factory()->create([
        'code' => 'DIV_A',
        'name' => 'Division A',
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'code' => 'REG_A',
        'name' => 'Region A',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'code' => 'CLUS_A',
        'name' => 'Cluster A',
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    return compact('badanUsaha', 'otherBadanUsaha', 'division', 'region', 'cluster');
}

function orgManagementPermissions(): array
{
    return [
        'ViewAny:BadanUsaha', 'Create:BadanUsaha', 'Update:BadanUsaha', 'Delete:BadanUsaha',
        'ViewAny:Division', 'Create:Division', 'Update:Division', 'Delete:Division',
        'ViewAny:Region', 'Create:Region', 'Update:Region', 'Delete:Region',
        'ViewAny:Cluster', 'Create:Cluster', 'Update:Cluster', 'Delete:Cluster',
    ];
}

function orgManagementAdminUser(string $scopeLevel = 'all'): User
{
    $permissions = collect(orgManagementPermissions())
        ->map(fn (string $name): Permission => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

    $role = Role::factory()->create([
        'name' => 'ORG MANAGEMENT ADMIN '.$scopeLevel,
        'organizational_scope_level' => $scopeLevel,
    ]);
    $role->syncPermissions($permissions);

    $user = User::factory()->create(['role_id' => $role->id]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function orgManagementScopedUser(array $hierarchy, string $scopeLevel): User
{
    $user = orgManagementAdminUser($scopeLevel);

    $user->badanUsahas()->sync([$hierarchy['badanUsaha']->id]);
    $user->divisis()->sync([$hierarchy['division']->id]);
    $user->regions()->sync([$hierarchy['region']->id]);
    $user->clusters()->sync([$hierarchy['cluster']->id]);
    $user->forgetOrganizationalIdsCache();

    return $user;
}

test('management endpoints require permissions', function (): void {
    $hierarchy = orgManagementHierarchy();
    $viewer = User::factory()->create([
        'role_id' => Role::factory()->create(['organizational_scope_level' => 'all'])->id,
    ]);

    $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/management/badanusaha')
        ->assertForbidden();

    $this->actingAs($viewer, 'sanctum')
        ->postJson('/api/management/divisi', [
            'badanusaha_id' => $hierarchy['badanUsaha']->id,
            'code' => 'NEW_DIV',
            'name' => 'New Division',
        ])
        ->assertForbidden();
});

test('org admin can list and show organizational records', function (): void {
    $hierarchy = orgManagementHierarchy();
    $user = orgManagementAdminUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/management/badanusaha')
        ->assertOk()
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.0.code', 'BU_A');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/management/cluster/'.$hierarchy['cluster']->id)
        ->assertOk()
        ->assertJsonPath('data.code', 'CLUS_A')
        ->assertJsonPath('data.region_id', $hierarchy['region']->id);
});

test('scoped user only sees records within organizational assignments', function (): void {
    $hierarchy = orgManagementHierarchy();
    $user = orgManagementScopedUser($hierarchy, 'badanusaha');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/management/badanusaha')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'BU_A');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/management/badanusaha/'.$hierarchy['otherBadanUsaha']->id)
        ->assertNotFound();
});

test('scoped user cannot create badan usaha outside all scope', function (): void {
    $user = orgManagementAdminUser('badanusaha');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/management/badanusaha', [
            'code' => 'BU_NEW',
            'name' => 'Badan Usaha Baru',
        ])
        ->assertStatus(400)
        ->assertJsonPath('meta.status', 'error');
});

test('org admin can create update and delete hierarchy records', function (): void {
    $hierarchy = orgManagementHierarchy();
    $user = orgManagementAdminUser();

    $divisionResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/api/management/divisi', [
            'badanusaha_id' => $hierarchy['badanUsaha']->id,
            'code' => 'div new',
            'name' => 'Division Baru',
        ]);

    $divisionResponse
        ->assertCreated()
        ->assertJsonPath('data.code', 'DIV_NEW')
        ->assertJsonPath('data.name', 'Division Baru');

    $divisionId = $divisionResponse->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/management/divisi/'.$divisionId, [
            'name' => 'Division Updated',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Division Updated');

    $regionResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/api/management/region', [
            'divisi_id' => $divisionId,
            'code' => 'reg new',
            'name' => 'Region Baru',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'REG_NEW')
        ->assertJsonPath('data.divisi_id', $divisionId);

    $regionId = $regionResponse->json('data.id');

    $clusterResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/api/management/cluster', [
            'region_id' => $regionId,
            'code' => 'clus new',
            'name' => 'Cluster Baru',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'CLUS_NEW')
        ->assertJsonPath('data.region_id', $regionId);

    $clusterId = $clusterResponse->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/management/cluster/'.$clusterId)
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/management/region/'.$regionId)
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/management/divisi/'.$divisionId)
        ->assertOk();

    expect(Cluster::withTrashed()->find($clusterId)?->trashed())->toBeTrue()
        ->and(Region::withTrashed()->find($regionId)?->trashed())->toBeTrue()
        ->and(Division::withTrashed()->find($divisionId)?->trashed())->toBeTrue();
});

test('create division fails when parent is outside scope', function (): void {
    $hierarchy = orgManagementHierarchy();
    $user = orgManagementScopedUser($hierarchy, 'badanusaha');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/management/divisi', [
            'badanusaha_id' => $hierarchy['otherBadanUsaha']->id,
            'code' => 'OUT_SCOPE',
            'name' => 'Out Scope Division',
        ])
        ->assertStatus(400)
        ->assertJsonPath('meta.status', 'error');
});

test('duplicate code within same parent is rejected', function (): void {
    $hierarchy = orgManagementHierarchy();
    $user = orgManagementAdminUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/management/divisi', [
            'badanusaha_id' => $hierarchy['badanUsaha']->id,
            'code' => 'div_a',
            'name' => 'Duplicate Division',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('delete is blocked when organizational dependencies exist', function (): void {
    $hierarchy = orgManagementHierarchy();
    $user = orgManagementAdminUser();

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/management/divisi/'.$hierarchy['division']->id)
        ->assertStatus(400)
        ->assertJsonPath('meta.status', 'error');

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/management/badanusaha/'.$hierarchy['badanUsaha']->id)
        ->assertStatus(400)
        ->assertJsonPath('meta.status', 'error');
});

test('user resource exposes organizational management permission flags', function (): void {
    $user = orgManagementAdminUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.user.permissions.can_manage_badan_usaha', true)
        ->assertJsonPath('data.user.permissions.can_create_division', true)
        ->assertJsonPath('data.user.permissions.can_update_region', true)
        ->assertJsonPath('data.user.permissions.can_delete_cluster', true);
});
