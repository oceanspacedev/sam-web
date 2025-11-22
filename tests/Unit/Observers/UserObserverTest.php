<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;

test('user with all scope has all pivot assignments detached on create', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create();
    $region = Region::factory()->create();
    $cluster = Cluster::factory()->create();

    $role = Role::factory()->create(['organizational_scope_level' => 'all']);

    $user = User::factory()->create(['role_id' => $role->id]);

    // Try to attach (but observer should detach them)
    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster->id);

    // Trigger observer by updating
    $user->update(['nama_lengkap' => 'Test Updated']);

    $user->refresh();

    expect($user->badanUsahas()->count())->toBe(0);
    expect($user->divisis()->count())->toBe(0);
    expect($user->regions()->count())->toBe(0);
    expect($user->clusters()->count())->toBe(0);
});

test('user with badanusaha scope keeps badanusaha but detaches lower levels', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create();
    $region = Region::factory()->create();
    $cluster = Cluster::factory()->create();

    $role = Role::factory()->create(['organizational_scope_level' => 'badanusaha']);

    $user = User::factory()->create(['role_id' => $role->id]);

    // Attach all levels
    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster->id);

    // Trigger observer
    $user->update(['nama_lengkap' => 'Test Updated']);

    $user->refresh();

    // Should keep badanusaha, detach others
    expect($user->badanUsahas()->count())->toBeGreaterThan(0);
    expect($user->divisis()->count())->toBe(0);
    expect($user->regions()->count())->toBe(0);
    expect($user->clusters()->count())->toBe(0);
});

test('user with divisi scope keeps badanusaha and divisi but detaches lower levels', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create();
    $region = Region::factory()->create();
    $cluster = Cluster::factory()->create();

    $role = Role::factory()->create(['organizational_scope_level' => 'divisi']);

    $user = User::factory()->create(['role_id' => $role->id]);

    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster->id);

    // Trigger observer
    $user->update(['nama_lengkap' => 'Test Updated']);

    $user->refresh();

    expect($user->badanUsahas()->count())->toBeGreaterThan(0);
    expect($user->divisis()->count())->toBeGreaterThan(0);
    expect($user->regions()->count())->toBe(0);
    expect($user->clusters()->count())->toBe(0);
});

test('user with region scope keeps badanusaha, divisi, region but detaches clusters', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create();
    $region = Region::factory()->create();
    $cluster = Cluster::factory()->create();

    $role = Role::factory()->create(['organizational_scope_level' => 'region']);

    $user = User::factory()->create(['role_id' => $role->id]);

    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster->id);

    // Trigger observer
    $user->update(['nama_lengkap' => 'Test Updated']);

    $user->refresh();

    expect($user->badanUsahas()->count())->toBeGreaterThan(0);
    expect($user->divisis()->count())->toBeGreaterThan(0);
    expect($user->regions()->count())->toBeGreaterThan(0);
    expect($user->clusters()->count())->toBe(0);
});

test('user with cluster scope keeps all assignments', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create();
    $region = Region::factory()->create();
    $cluster = Cluster::factory()->create();

    $role = Role::factory()->create(['organizational_scope_level' => 'cluster']);

    $user = User::factory()->create(['role_id' => $role->id]);

    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster->id);

    // Trigger observer
    $user->update(['nama_lengkap' => 'Test Updated']);

    $user->refresh();

    expect($user->badanUsahas()->count())->toBeGreaterThan(0);
    expect($user->divisis()->count())->toBeGreaterThan(0);
    expect($user->regions()->count())->toBeGreaterThan(0);
    expect($user->clusters()->count())->toBeGreaterThan(0);
});

test('role transition from cluster to all detaches all assignments', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $divisi = Division::factory()->create();
    $region = Region::factory()->create();
    $cluster = Cluster::factory()->create();

    $clusterRole = Role::factory()->create(['organizational_scope_level' => 'cluster']);
    $allRole = Role::factory()->create(['organizational_scope_level' => 'all']);

    $user = User::factory()->create(['role_id' => $clusterRole->id]);

    $user->badanUsahas()->attach($badanUsaha->id);
    $user->divisis()->attach($divisi->id);
    $user->regions()->attach($region->id);
    $user->clusters()->attach($cluster->id);

    // Change role to 'all'
    $user->update(['role_id' => $allRole->id]);

    $user->refresh();

    expect($user->badanUsahas()->count())->toBe(0);
    expect($user->divisis()->count())->toBe(0);
    expect($user->regions()->count())->toBe(0);
    expect($user->clusters()->count())->toBe(0);
});

test('observer triggers on user creation', function () {
    $badanUsaha = BadanUsaha::factory()->create();
    $role = Role::factory()->create(['organizational_scope_level' => 'all']);

    $user = User::factory()->create(['role_id' => $role->id]);

    // Attach after creation
    $user->badanUsahas()->attach($badanUsaha->id);

    // Verify it's attached
    expect($user->badanUsahas()->count())->toBe(1);

    // Trigger observer by updating a field
    $user->update(['nama_lengkap' => 'Updated Name']);

    // Refresh relationship count
    $user->refresh();

    // Should be detached by observer
    expect($user->badanUsahas()->count())->toBe(0);
});
