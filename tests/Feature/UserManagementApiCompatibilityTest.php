<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

function createOrgHierarchy(): array
{
    $badanUsaha = BadanUsaha::factory()->create();
    $division = Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    return [
        'bu' => $badanUsaha,
        'div' => $division,
        'reg' => $region,
        'clus' => $cluster,
    ];
}

test('create user API menerima payload frontend minimal dengan fallback assignment dari actor', function (): void {
    Gate::define('Create:User', fn (User $user): bool => true);

    $hierarchy = createOrgHierarchy();

    $actorRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
    ]);
    $targetRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
    ]);

    $actor = User::factory()->create([
        'role_id' => $actorRole->id,
    ]);
    $actor->badanUsahas()->attach($hierarchy['bu']->id);
    $actor->divisis()->attach($hierarchy['div']->id);
    $actor->regions()->attach($hierarchy['reg']->id);
    $actor->clusters()->attach($hierarchy['clus']->id);

    $response = $this->actingAs($actor, 'sanctum')
        ->postJson('/api/users', [
            'nama_lengkap' => 'User Mobile',
            'username' => 'user_mobile',
            'password' => 'password123',
            'role_id' => $targetRole->id,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.username', 'user_mobile')
        ->assertJsonPath('data.role_id', $targetRole->id)
        ->assertJsonPath('data.tm_id', $actor->id);

    $createdUser = User::query()->where('username', 'user_mobile')->firstOrFail();

    expect($createdUser->badanUsahas()->pluck('badan_usahas.id')->all())->toBe([$hierarchy['bu']->id])
        ->and($createdUser->divisis()->pluck('divisions.id')->all())->toBe([$hierarchy['div']->id])
        ->and($createdUser->regions()->pluck('regions.id')->all())->toBe([$hierarchy['reg']->id])
        ->and($createdUser->clusters()->pluck('clusters.id')->all())->toBe([$hierarchy['clus']->id]);
});

test('update user API menerima perubahan role tanpa org arrays dan fallback assignment dengan aman', function (): void {
    Gate::define('Update:User', fn (User $user): bool => true);

    $hierarchy = createOrgHierarchy();

    $actorRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
    ]);
    $targetOldRole = Role::factory()->create([
        'organizational_scope_level' => 'all',
    ]);
    $targetNewRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
    ]);

    $actor = User::factory()->create([
        'role_id' => $actorRole->id,
    ]);
    $actor->badanUsahas()->attach($hierarchy['bu']->id);
    $actor->divisis()->attach($hierarchy['div']->id);
    $actor->regions()->attach($hierarchy['reg']->id);
    $actor->clusters()->attach($hierarchy['clus']->id);

    $targetUser = User::factory()->create([
        'role_id' => $targetOldRole->id,
    ]);

    $response = $this->actingAs($actor, 'sanctum')
        ->putJson("/api/users/{$targetUser->id}", [
            'nama_lengkap' => 'User Update',
            'username' => 'user_update',
            'role_id' => $targetNewRole->id,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.role_id', $targetNewRole->id)
        ->assertJsonPath('data.username', 'user_update');

    $targetUser->refresh();

    expect($targetUser->badanUsahas()->pluck('badan_usahas.id')->all())->toBe([$hierarchy['bu']->id])
        ->and($targetUser->divisis()->pluck('divisions.id')->all())->toBe([$hierarchy['div']->id])
        ->and($targetUser->regions()->pluck('regions.id')->all())->toBe([$hierarchy['reg']->id])
        ->and($targetUser->clusters()->pluck('clusters.id')->all())->toBe([$hierarchy['clus']->id]);
});

test('create user API tetap menolak role berscope tanpa assignment fallback', function (): void {
    Gate::define('Create:User', fn (User $user): bool => true);

    $actorRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
    ]);
    $targetRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
    ]);

    $actor = User::factory()->create([
        'role_id' => $actorRole->id,
    ]);

    $response = $this->actingAs($actor, 'sanctum')
        ->postJson('/api/users', [
            'nama_lengkap' => 'User Invalid',
            'username' => 'user_invalid',
            'password' => 'password123',
            'role_id' => $targetRole->id,
        ]);

    $response
        ->assertStatus(400)
        ->assertJsonPath('meta.status', 'error')
        ->assertJsonPath('meta.message', 'Organizational assignment wajib diisi untuk role ini');

    expect($response->json('data'))->toHaveKeys([
        'badanusaha_ids',
        'divisi_ids',
        'region_ids',
        'cluster_ids',
    ]);
});
