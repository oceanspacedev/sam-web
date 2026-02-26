<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;

function createVisitPaginationHierarchy(string $suffix = ''): array
{
    $suffix = $suffix ?: uniqid('visit-pagination-', true);

    $bu = BadanUsaha::create(['name' => 'BU-'.$suffix]);
    $div = Division::create(['name' => 'DIV-'.$suffix, 'badanusaha_id' => $bu->id]);
    $reg = Region::create(['name' => 'REG-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = Cluster::create(['name' => 'CLUS-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

    return ['bu' => $bu, 'div' => $div, 'reg' => $reg, 'clus' => $clus];
}

function createVisitPaginationUser(array $hierarchy): User
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

function createVisitPaginationOutlet(array $hierarchy, int $index): Outlet
{
    return Outlet::create([
        'kode_outlet' => 'PAG-OUT-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'nama_outlet' => 'Pagination Outlet '.$index,
        'alamat_outlet' => 'Alamat '.$index,
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

test('visit endpoint returns paginated response when per_page is provided', function () {
    $hierarchy = createVisitPaginationHierarchy();
    $user = createVisitPaginationUser($hierarchy);

    $monthStart = now()->startOfMonth();

    for ($i = 1; $i <= 25; $i++) {
        $outlet = createVisitPaginationOutlet($hierarchy, $i);
        $visitDate = $monthStart->copy()->addDays(($i - 1) % 25)->toDateString();

        Visit::create([
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
            'tanggal_visit' => $visitDate,
            'tipe_visit' => 'EXTRACALL',
            'check_in_time' => now()->subMinutes($i),
            'check_out_time' => now(),
            'transaksi' => 'NO',
            'durasi_visit' => 15,
        ]);
    }

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/visit?compact=1&period=month&per_page=10&page=2');

    $response
        ->assertStatus(200)
        ->assertJsonPath('meta.pagination.current_page', 2)
        ->assertJsonPath('meta.pagination.per_page', 10)
        ->assertJsonPath('meta.pagination.total', 25)
        ->assertJsonPath('meta.pagination.last_page', 3);

    expect($response->json('data'))
        ->toBeArray()
        ->and(count($response->json('data')))->toBe(10);
});

test('plan visit endpoint returns paginated response when per_page is provided', function () {
    $hierarchy = createVisitPaginationHierarchy('plan');
    $user = createVisitPaginationUser($hierarchy);

    $monthStart = now()->startOfMonth();

    for ($i = 1; $i <= 25; $i++) {
        $outlet = createVisitPaginationOutlet($hierarchy, 1000 + $i);
        $planDate = $monthStart->copy()->addDays(($i - 1) % 25);

        PlanVisit::create(array_merge(
            PlanVisit::schedulePayload($planDate, 'daily'),
            [
                'user_id' => $user->id,
                'visitable_type' => Outlet::class,
                'visitable_id' => $outlet->id,
                'realized_at' => null,
                'realized_visit_id' => null,
            ]
        ));
    }

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/planvisit?compact=1&period=month&per_page=10&page=2');

    $response
        ->assertStatus(200)
        ->assertJsonPath('meta.pagination.current_page', 2)
        ->assertJsonPath('meta.pagination.per_page', 10)
        ->assertJsonPath('meta.pagination.total', 25)
        ->assertJsonPath('meta.pagination.last_page', 3);

    expect($response->json('data'))
        ->toBeArray()
        ->and(count($response->json('data')))->toBe(10);
});
