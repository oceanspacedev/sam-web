<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;

function createVisitTargetsHierarchy(string $suffix = ''): array
{
    $suffix = $suffix ?: uniqid('visit-targets-', true);

    $bu = BadanUsaha::create(['name' => 'BU-'.$suffix]);
    $div = Division::create(['name' => 'DIV-'.$suffix, 'badanusaha_id' => $bu->id]);
    $reg = Region::create(['name' => 'REG-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = Cluster::create(['name' => 'CLUS-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

    return ['bu' => $bu, 'div' => $div, 'reg' => $reg, 'clus' => $clus];
}

function createVisitTargetsUser(array $hierarchy): User
{
    $role = Role::create([
        'name' => 'Role-'.uniqid(),
        'can_access_web' => true,
        'organizational_scope_level' => 'cluster',
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    $user->badanUsahas()->attach($hierarchy['bu']->id);
    $user->divisis()->attach($hierarchy['div']->id);
    $user->regions()->attach($hierarchy['reg']->id);
    $user->clusters()->attach($hierarchy['clus']->id);

    return $user->fresh();
}

function createOutletForVisitTarget(array $hierarchy, int $index): Outlet
{
    return Outlet::create([
        'kode_outlet' => 'OUT-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'nama_outlet' => 'Outlet '.$index,
        'alamat_outlet' => 'Alamat outlet '.$index,
        'nama_pemilik_outlet' => 'Pemilik '.$index,
        'nomer_tlp_outlet' => '08'.str_pad((string) $index, 10, '1', STR_PAD_LEFT),
        'distric' => 'D01',
        'latlong' => '-6.2000,106.8166',
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status_outlet' => 'MAINTAIN',
    ]);
}

test('visit targets without search returns default limited data', function () {
    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);

    // Seed more than requested limit to assert backend limit is applied.
    for ($i = 1; $i <= 25; $i++) {
        createOutletForVisitTarget($hierarchy, $i);
    }

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/visit/targets?limit=10');

    $response->assertStatus(200);
    $response->assertJsonPath('meta.code', 200);

    $data = $response->json('data');

    expect($data)->not->toBeEmpty()
        ->and(count($data))->toBeLessThanOrEqual(10)
        ->and(count($data))->toBeGreaterThanOrEqual(10);
});
