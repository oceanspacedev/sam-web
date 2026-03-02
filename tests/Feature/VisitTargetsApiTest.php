<?php

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\Region;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

function createOutletForVisitTarget(array $hierarchy, int $index, string $latlong = '-6.2000,106.8166'): Outlet
{
    return Outlet::create([
        'kode_outlet' => 'OUT-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'nama_outlet' => 'Outlet '.$index,
        'alamat_outlet' => 'Alamat outlet '.$index,
        'nama_pemilik_outlet' => 'Pemilik '.$index,
        'nomer_tlp_outlet' => '08'.str_pad((string) $index, 10, '1', STR_PAD_LEFT),
        'distric' => 'D01',
        'latlong' => $latlong,
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status_outlet' => 'MAINTAIN',
    ]);
}

function createRegisterForVisitTarget(
    array $hierarchy,
    User $user,
    int $index,
    ?string $status = null,
    string $type = 'NOO'
): Register
{
    return Register::factory()->create([
        'kode_outlet' => 'REG-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'nama_outlet' => 'Register '.$index,
        'alamat_outlet' => 'Alamat register '.$index,
        'distric' => 'D01',
        'latlong' => '-6.2000,106.8166',
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'created_by_id' => $user->id,
        'tm_id' => $user->id,
        'status' => $status,
        'type' => $type,
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

test('visit targets with coordinates returns nearest locations sorted by distance for visit context', function () {
    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);

    $nearest = null;
    $farthest = null;

    for ($i = 1; $i <= 15; $i++) {
        $longitude = 106.8000 + (($i - 1) * 0.0010);
        $outlet = createOutletForVisitTarget($hierarchy, $i, sprintf('-6.2000,%.4f', $longitude));

        if ($i === 1) {
            $nearest = $outlet;
        }

        if ($i === 15) {
            $farthest = $outlet;
        }
    }

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/visit/targets?context=visit&lat=-6.2000&lng=106.8000');

    $response->assertStatus(200);
    $response->assertJsonPath('meta.code', 200);

    $data = collect($response->json('data'));

    expect($data)
        ->toHaveCount(15)
        ->and($data->first()['id'])->toBe($nearest?->id)
        ->and($data->last()['id'])->toBe($farthest?->id);
});

test('visit targets nearest recommendation excludes targets outside radius', function () {
    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);

    $near = createOutletForVisitTarget($hierarchy, 1, '-6.2000,106.8000');
    $mid = createOutletForVisitTarget($hierarchy, 2, '-6.2000,106.8700'); // ~7.7 km
    $far = createOutletForVisitTarget($hierarchy, 3, '-6.2000,107.7000'); // ~100 km

    $responseDefaultRadius = $this->actingAs($user, 'sanctum')
        ->getJson('/api/visit/targets?context=visit&lat=-6.2000&lng=106.8000');

    $responseDefaultRadius->assertStatus(200);
    $defaultData = collect($responseDefaultRadius->json('data'));

    expect($defaultData->contains(fn (array $target): bool => $target['id'] === $near->id))->toBeTrue()
        ->and($defaultData->contains(fn (array $target): bool => $target['id'] === $mid->id))->toBeTrue()
        ->and($defaultData->contains(fn (array $target): bool => $target['id'] === $far->id))->toBeFalse();

    $response5KmRadius = $this->actingAs($user, 'sanctum')
        ->getJson('/api/visit/targets?context=visit&lat=-6.2000&lng=106.8000&nearby_radius_km=5');

    $response5KmRadius->assertStatus(200);
    $radius5Data = collect($response5KmRadius->json('data'));

    expect($radius5Data)
        ->toHaveCount(1)
        ->and($radius5Data->first()['id'])->toBe($near->id)
        ->and($radius5Data->contains(fn (array $target): bool => $target['id'] === $mid->id))->toBeFalse();
});

test('visit targets excludes approved and rejected registers for visit', function () {
    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);

    SystemSetting::factory()->global()->allowRegisterVisit(true)->create();

    $pending = createRegisterForVisitTarget($hierarchy, $user, 1, 'PENDING', 'NOO');
    $rejected = createRegisterForVisitTarget($hierarchy, $user, 2, 'REJECTED', 'NOO');
    $approved = createRegisterForVisitTarget($hierarchy, $user, 3, 'APPROVED', 'NOO');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/visit/targets?context=visit&search=Register');

    $response->assertStatus(200);
    $response->assertJsonPath('meta.code', 200);

    $data = collect($response->json('data'));

    expect($data->contains(fn (array $target): bool => $target['type'] === 'register' && $target['id'] === $pending->id))->toBeTrue()
        ->and($data->contains(fn (array $target): bool => $target['type'] === 'register' && $target['id'] === $rejected->id))->toBeFalse()
        ->and($data->contains(fn (array $target): bool => $target['type'] === 'register' && $target['id'] === $approved->id))->toBeFalse();
});

test('visit targets still include lead registers for visit', function () {
    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);

    SystemSetting::factory()->global()->allowRegisterVisit(true)->create();

    $lead = createRegisterForVisitTarget($hierarchy, $user, 11, 'PENDING', 'LEAD');
    $noo = createRegisterForVisitTarget($hierarchy, $user, 12, 'PENDING', 'NOO');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/visit/targets?context=visit&search=Register');

    $response->assertStatus(200);
    $data = collect($response->json('data'));

    expect($data->contains(fn (array $target): bool => $target['type'] === 'register' && $target['id'] === $lead->id))->toBeTrue()
        ->and($data->contains(fn (array $target): bool => $target['type'] === 'register' && $target['id'] === $noo->id))->toBeTrue();
});

test('visit checkin allows lead register target when still below max limit', function () {
    Storage::fake('public');
    Storage::fake('s3');
    $this->withoutMiddleware(\App\Http\Middleware\RateLimitUploads::class);

    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);
    SystemSetting::factory()->global()->allowRegisterVisit(true)->create();

    $lead = createRegisterForVisitTarget($hierarchy, $user, 21, 'PENDING', 'LEAD');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', [
            'register_id' => $lead->id,
            'latlong_in' => '-6.2000,106.8000',
            'tipe_visit' => 'EXTRACALL',
            'picture_visit' => UploadedFile::fake()->image('checkin.jpg', 800, 600),
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('meta.code', 200);

    expect(Visit::query()
        ->where('user_id', $user->id)
        ->where('visitable_type', Register::class)
        ->where('visitable_id', $lead->id)
        ->exists())->toBeTrue();
});

test('visit checkin blocks lead register after max 4 visits in 30 day window', function () {
    Storage::fake('public');
    Storage::fake('s3');
    $this->withoutMiddleware(\App\Http\Middleware\RateLimitUploads::class);

    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);
    SystemSetting::factory()->global()->allowRegisterVisit(true)->create();

    $lead = createRegisterForVisitTarget($hierarchy, $user, 21, 'PENDING', 'LEAD');
    for ($i = 1; $i <= 4; $i++) {
        Visit::create([
            'tanggal_visit' => now()->subDays($i)->toDateString(),
            'user_id' => $user->id,
            'visitable_type' => Register::class,
            'visitable_id' => $lead->id,
            'tipe_visit' => 'EXTRACALL',
            'latlong_in' => '-6.2000,106.8000',
            'check_in_time' => now()->subDays($i)->setTime(9, 0),
            'check_out_time' => now()->subDays($i)->setTime(10, 0),
            'picture_visit_in' => 'tmp/checkin-'.$i.'.jpg',
            'picture_visit_out' => 'tmp/checkout-'.$i.'.jpg',
        ]);
    }

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', [
            'register_id' => $lead->id,
            'latlong_in' => '-6.2000,106.8000',
            'tipe_visit' => 'EXTRACALL',
            'picture_visit' => UploadedFile::fake()->image('checkin.jpg', 800, 600),
        ]);

    $response->assertStatus(400);
    $response->assertJsonPath('meta.message', 'Lead ini sudah di-visit 4x dalam 30 hari terakhir. Upgrade ke NOO atau update status lead terlebih dahulu.');
    $response->assertJsonPath('data.max_lead_visits_in_window', 4);
    $response->assertJsonPath('data.lead_visit_count_in_window', 4);
    $response->assertJsonPath('data.lead_visit_window_days', 30);

    expect(Visit::query()
        ->where('user_id', $user->id)
        ->where('visitable_type', Register::class)
        ->where('visitable_id', $lead->id)
        ->count())->toBe(4);
});

test('visit checkin allows lead register when previous visits are outside 30 day window', function () {
    Storage::fake('public');
    Storage::fake('s3');
    $this->withoutMiddleware(\App\Http\Middleware\RateLimitUploads::class);

    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);
    SystemSetting::factory()->global()->allowRegisterVisit(true)->create();

    $lead = createRegisterForVisitTarget($hierarchy, $user, 22, 'PENDING', 'LEAD');
    for ($i = 1; $i <= 3; $i++) {
        Visit::create([
            'tanggal_visit' => now()->subDays(40 + $i)->toDateString(),
            'user_id' => $user->id,
            'visitable_type' => Register::class,
            'visitable_id' => $lead->id,
            'tipe_visit' => 'EXTRACALL',
            'latlong_in' => '-6.2000,106.8000',
            'check_in_time' => now()->subDays(40 + $i)->setTime(9, 0),
            'check_out_time' => now()->subDays(40 + $i)->setTime(10, 0),
            'picture_visit_in' => 'tmp/old-checkin-'.$i.'.jpg',
            'picture_visit_out' => 'tmp/old-checkout-'.$i.'.jpg',
        ]);
    }

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', [
            'register_id' => $lead->id,
            'latlong_in' => '-6.2000,106.8000',
            'tipe_visit' => 'EXTRACALL',
            'picture_visit' => UploadedFile::fake()->image('checkin.jpg', 800, 600),
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('meta.code', 200);

    expect(Visit::query()
        ->where('user_id', $user->id)
        ->where('visitable_type', Register::class)
        ->where('visitable_id', $lead->id)
        ->count())->toBe(4);
});

test('plan visit creation allows lead register target', function () {
    $hierarchy = createVisitTargetsHierarchy();
    $user = createVisitTargetsUser($hierarchy);
    SystemSetting::factory()->global()->allowRegisterVisit(true)->create();

    $lead = createRegisterForVisitTarget($hierarchy, $user, 31, 'PENDING', 'LEAD');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/planvisit', [
            'register_id' => $lead->id,
            'tanggal_visit' => now()->addDays(3)->toDateString(),
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('meta.code', 200);

    expect(PlanVisit::query()
        ->where('user_id', $user->id)
        ->where('visitable_type', Register::class)
        ->where('visitable_id', $lead->id)
        ->exists())->toBeTrue();
});
