<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;

function userWithScope(string $scopeLevel): User
{
    $role = Role::factory()->create([
        'organizational_scope_level' => $scopeLevel,
        'can_access_web' => true,
    ]);

    return User::factory()->create([
        'role_id' => $role->id,
    ]);
}

it('attaches a created division to a divisi scoped creator and refreshes cached ids', function (): void {
    $creator = userWithScope('divisi');
    $badanUsaha = BadanUsaha::factory()->create();

    $creator->badanUsahas()->sync([$badanUsaha->id]);
    expect($creator->getOrganizationalIds()['divisi'])->toBe([]);

    $this->actingAs($creator);

    $division = Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
    ]);

    expect($creator->divisis()->pluck('divisions.id')->all())->toEqual([$division->id])
        ->and($creator->getOrganizationalIds()['divisi'])->toEqual([$division->id]);
});

it('attaches created hierarchy records to a cluster scoped creator at all relevant levels', function (): void {
    $creator = userWithScope('cluster');

    $this->actingAs($creator);

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

    expect($creator->badanUsahas()->pluck('badan_usahas.id')->all())->toEqual([$badanUsaha->id])
        ->and($creator->divisis()->pluck('divisions.id')->all())->toEqual([$division->id])
        ->and($creator->regions()->pluck('regions.id')->all())->toEqual([$region->id])
        ->and($creator->clusters()->pluck('clusters.id')->all())->toEqual([$cluster->id]);
});

it('does not attach lower level records to a broader badan usaha scoped creator', function (): void {
    $creator = userWithScope('badanusaha');
    $badanUsaha = BadanUsaha::factory()->create();

    $creator->badanUsahas()->sync([$badanUsaha->id]);

    $this->actingAs($creator);

    Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
    ]);

    expect($creator->badanUsahas()->pluck('badan_usahas.id')->all())->toEqual([$badanUsaha->id])
        ->and($creator->divisis()->count())->toBe(0);
});

it('does not attach any created hierarchy records to an all scoped creator', function (): void {
    $creator = userWithScope('all');

    $this->actingAs($creator);

    $badanUsaha = BadanUsaha::factory()->create();
    $division = Division::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
    ]);
    $region = Region::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
    ]);
    Cluster::factory()->create([
        'badanusaha_id' => $badanUsaha->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    expect($creator->badanUsahas()->count())->toBe(0)
        ->and($creator->divisis()->count())->toBe(0)
        ->and($creator->regions()->count())->toBe(0)
        ->and($creator->clusters()->count())->toBe(0);
});

it('removes direct hierarchy pivots from all users when records are deleted', function (): void {
    $firstUser = userWithScope('cluster');
    $secondUser = userWithScope('cluster');

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

    foreach ([$firstUser, $secondUser] as $user) {
        $user->badanUsahas()->syncWithoutDetaching([$badanUsaha->id]);
        $user->divisis()->syncWithoutDetaching([$division->id]);
        $user->regions()->syncWithoutDetaching([$region->id]);
        $user->clusters()->syncWithoutDetaching([$cluster->id]);
    }

    $this->assertDatabaseHas('user_badan_usaha', [
        'user_id' => $firstUser->id,
        'badanusaha_id' => $badanUsaha->id,
    ]);

    $badanUsaha->delete();
    $division->delete();
    $region->delete();
    $cluster->delete();

    foreach ([$firstUser, $secondUser] as $user) {
        $this->assertDatabaseMissing('user_badan_usaha', [
            'user_id' => $user->id,
            'badanusaha_id' => $badanUsaha->id,
        ]);
        $this->assertDatabaseMissing('user_divisi', [
            'user_id' => $user->id,
            'divisi_id' => $division->id,
        ]);
        $this->assertDatabaseMissing('user_regions', [
            'user_id' => $user->id,
            'region_id' => $region->id,
        ]);
        $this->assertDatabaseMissing('user_clusters', [
            'user_id' => $user->id,
            'cluster_id' => $cluster->id,
        ]);
    }
});
