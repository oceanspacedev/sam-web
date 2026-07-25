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
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
    $this->withoutMiddleware(\App\Http\Middleware\RateLimitUploads::class);
});

function weeklyOnceHierarchy(string $suffix = ''): array
{
    $suffix = $suffix ?: uniqid('weekly-once-', true);

    $bu = BadanUsaha::create(['name' => 'BU-'.$suffix]);
    $div = Division::create(['name' => 'DIV-'.$suffix, 'badanusaha_id' => $bu->id]);
    $reg = Region::create(['name' => 'REG-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = Cluster::create(['name' => 'CLUS-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

    return ['bu' => $bu, 'div' => $div, 'reg' => $reg, 'clus' => $clus];
}

function weeklyOnceUser(array $hierarchy): User
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

function weeklyOnceOutlet(array $hierarchy, string $code): Outlet
{
    return Outlet::create([
        'kode_outlet' => $code,
        'nama_outlet' => 'Outlet '.$code,
        'alamat_outlet' => 'Alamat '.$code,
        'nama_pemilik_outlet' => 'Pemilik '.$code,
        'nomer_tlp_outlet' => '081234567890',
        'distric' => 'D01',
        'latlong' => '-6.2000,106.8166',
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status_outlet' => 'MAINTAIN',
    ]);
}

function weeklyOnceCheckin(int $outletId, string $tipeVisit = 'PLANNED'): array
{
    return [
        'outlet_id' => $outletId,
        'latlong_in' => '-6.2000,106.8166',
        'tipe_visit' => $tipeVisit,
        'picture_visit' => UploadedFile::fake()->image('checkin.jpg', 800, 600),
    ];
}

test('first weekly planned check-in is PLANNED and second same-outlet visit in week is EXTRACALL', function () {
    $hierarchy = weeklyOnceHierarchy();
    $user = weeklyOnceUser($hierarchy);
    $outlet = weeklyOnceOutlet($hierarchy, 'ONCE-001');

    $monday = Carbon::parse('2026-07-20')->startOfDay();
    Carbon::setTestNow($monday->copy()->setTime(10, 0));

    PlanVisit::create(array_merge(
        PlanVisit::schedulePayload($monday, 'weekly'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    ));

    $first = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', weeklyOnceCheckin($outlet->id, 'EXTRACALL'));

    $first->assertOk();
    expect($first->json('data.tipe_visit'))->toBe('PLANNED');

    $plan = PlanVisit::query()
        ->where('user_id', $user->id)
        ->where('visitable_id', $outlet->id)
        ->first();

    expect($plan->realized_at)->not->toBeNull()
        ->and($plan->realized_visit_id)->toBe($first->json('data.id'));

    Carbon::setTestNow($monday->copy()->addDays(3)->setTime(14, 0));

    $second = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', weeklyOnceCheckin($outlet->id, 'PLANNED'));

    $second->assertOk();
    expect($second->json('data.tipe_visit'))->toBe('EXTRACALL');

    $plannedCount = Visit::query()
        ->where('user_id', $user->id)
        ->where('visitable_id', $outlet->id)
        ->where('tipe_visit', 'PLANNED')
        ->whereBetween('tanggal_visit', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
        ->count();

    expect($plannedCount)->toBe(1);

    Carbon::setTestNow();
});

test('orphan PLANNED visit blocks second PLANNED in same weekly period', function () {
    $hierarchy = weeklyOnceHierarchy('orphan');
    $user = weeklyOnceUser($hierarchy);
    $outlet = weeklyOnceOutlet($hierarchy, 'ONCE-ORPH');

    $monday = Carbon::parse('2026-07-20')->startOfDay();
    Carbon::setTestNow($monday->copy()->setTime(10, 0));

    PlanVisit::create(array_merge(
        PlanVisit::schedulePayload($monday, 'weekly'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    ));

    // Legacy/orphan: PLANNED label without consuming the weekly plan.
    Visit::withoutEvents(function () use ($user, $outlet, $monday): void {
        Visit::create([
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
            'tanggal_visit' => $monday->toDateString(),
            'tipe_visit' => 'PLANNED',
            'check_in_time' => $monday->copy()->setTime(10, 0),
            'latlong_in' => '-6.2000,106.8166',
        ]);
    });

    expect(
        PlanVisit::query()
            ->where('user_id', $user->id)
            ->where('visitable_id', $outlet->id)
            ->whereNull('realized_at')
            ->count()
    )->toBe(1);

    Carbon::setTestNow($monday->copy()->addDays(3)->setTime(14, 0));

    $second = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', weeklyOnceCheckin($outlet->id, 'PLANNED'));

    $second->assertOk();
    expect($second->json('data.tipe_visit'))->toBe('EXTRACALL');

    $plannedCount = Visit::query()
        ->where('user_id', $user->id)
        ->where('visitable_id', $outlet->id)
        ->where('tipe_visit', 'PLANNED')
        ->whereBetween('tanggal_visit', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
        ->count();

    expect($plannedCount)->toBe(1);

    Carbon::setTestNow();
});

test('daily plans still allow multiple PLANNED visits within one week', function () {
    $hierarchy = weeklyOnceHierarchy('daily-multi');
    $user = weeklyOnceUser($hierarchy);
    $outlet = weeklyOnceOutlet($hierarchy, 'ONCE-DAILY');

    $monday = Carbon::parse('2026-07-20')->startOfDay();
    $thursday = $monday->copy()->addDays(3);

    foreach ([$monday, $thursday] as $planDate) {
        PlanVisit::create(array_merge(
            PlanVisit::schedulePayload($planDate, 'daily'),
            [
                'user_id' => $user->id,
                'visitable_type' => Outlet::class,
                'visitable_id' => $outlet->id,
            ]
        ));
    }

    Carbon::setTestNow($monday->copy()->setTime(9, 0));
    $first = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', weeklyOnceCheckin($outlet->id, 'PLANNED'));
    $first->assertOk();
    expect($first->json('data.tipe_visit'))->toBe('PLANNED');

    Carbon::setTestNow($thursday->copy()->setTime(9, 0));
    $second = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', weeklyOnceCheckin($outlet->id, 'PLANNED'));
    $second->assertOk();
    expect($second->json('data.tipe_visit'))->toBe('PLANNED');

    $plannedCount = Visit::query()
        ->where('user_id', $user->id)
        ->where('visitable_id', $outlet->id)
        ->where('tipe_visit', 'PLANNED')
        ->whereBetween('tanggal_visit', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])
        ->count();

    expect($plannedCount)->toBe(2)
        ->and(
            PlanVisit::query()
                ->where('user_id', $user->id)
                ->where('visitable_id', $outlet->id)
                ->whereNull('realized_at')
                ->count()
        )->toBe(0);

    Carbon::setTestNow();
});

test('daily plan can be recreated for the same date after it was deleted', function () {
    $hierarchy = weeklyOnceHierarchy('daily-recreate');
    $user = weeklyOnceUser($hierarchy);
    $outlet = weeklyOnceOutlet($hierarchy, 'ONCE-RECR');

    $planDate = now()->startOfDay()->addDays(5)->toDateString();

    $created = $this->actingAs($user, 'sanctum')
        ->postJson('/api/planvisit', [
            'outlet_id' => $outlet->id,
            'tanggal_visit' => $planDate,
        ]);

    $created->assertOk();
    $planId = $created->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/planvisit/'.$planId)
        ->assertOk();

    $recreated = $this->actingAs($user, 'sanctum')
        ->postJson('/api/planvisit', [
            'outlet_id' => $outlet->id,
            'tanggal_visit' => $planDate,
        ]);

    $recreated->assertOk();

    expect(
        PlanVisit::query()
            ->where('user_id', $user->id)
            ->where('visitable_id', $outlet->id)
            ->whereDate('period_start', $planDate)
            ->count()
    )->toBe(1);
});

test('creating a duplicate weekly plan for same user outlet period is rejected', function () {
    $hierarchy = weeklyOnceHierarchy('create-dup');
    $user = weeklyOnceUser($hierarchy);
    $outlet = weeklyOnceOutlet($hierarchy, 'ONCE-CRT');
    $monday = Carbon::parse('2026-07-20')->startOfDay();

    $payload = array_merge(
        PlanVisit::schedulePayload($monday, 'weekly'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    );

    PlanVisit::create($payload);

    expect(fn () => PlanVisit::create($payload))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('createOrRestoreForPeriod restores soft-deleted plan and clears realization', function () {
    $hierarchy = weeklyOnceHierarchy('restore-reuse');
    $user = weeklyOnceUser($hierarchy);
    $outlet = weeklyOnceOutlet($hierarchy, 'ONCE-RST');
    $planDate = now()->startOfDay()->addDays(5);

    $payload = array_merge(
        PlanVisit::schedulePayload($planDate, 'daily'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    );

    $plan = PlanVisit::create($payload);
    $visit = Visit::withoutEvents(fn () => Visit::create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $planDate->toDateString(),
        'tipe_visit' => 'PLANNED',
        'check_in_time' => $planDate->copy()->setTime(10, 0),
        'latlong_in' => '-6.2000,106.8166',
    ]));
    $plan->forceFill([
        'realized_at' => now(),
        'realized_visit_id' => $visit->id,
    ])->save();
    $plan->delete();

    $restored = PlanVisit::createOrRestoreForPeriod($payload);

    expect($restored->id)->toBe($plan->id)
        ->and($restored->trashed())->toBeFalse()
        ->and($restored->realized_at)->toBeNull()
        ->and($restored->realized_visit_id)->toBeNull()
        ->and(
            PlanVisit::withTrashed()
                ->where('user_id', $user->id)
                ->where('visitable_id', $outlet->id)
                ->whereDate('period_start', $payload['period_start'])
                ->count()
        )->toBe(1);
});

test('createOrRestoreForPeriod rejects an active duplicate', function () {
    $hierarchy = weeklyOnceHierarchy('restore-dup');
    $user = weeklyOnceUser($hierarchy);
    $outlet = weeklyOnceOutlet($hierarchy, 'ONCE-RDUP');
    $planDate = now()->startOfDay()->addDays(6);

    $payload = array_merge(
        PlanVisit::schedulePayload($planDate, 'daily'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    );

    PlanVisit::create($payload);

    expect(fn () => PlanVisit::createOrRestoreForPeriod($payload))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('updateOrCreateForPeriod restores soft-deleted plan instead of failing', function () {
    $hierarchy = weeklyOnceHierarchy('upsert-trashed');
    $user = weeklyOnceUser($hierarchy);
    $outlet = weeklyOnceOutlet($hierarchy, 'ONCE-UPS');
    $monday = Carbon::parse('2026-07-20')->startOfDay();

    $payload = array_merge(
        PlanVisit::schedulePayload($monday, 'weekly'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    );

    $plan = PlanVisit::create($payload);
    $plan->delete();

    [$updated, $created] = PlanVisit::updateOrCreateForPeriod($payload);

    expect($created)->toBeFalse()
        ->and($updated->id)->toBe($plan->id)
        ->and($updated->trashed())->toBeFalse()
        ->and(
            PlanVisit::query()
                ->where('user_id', $user->id)
                ->where('visitable_id', $outlet->id)
                ->whereDate('period_start', $payload['period_start'])
                ->count()
        )->toBe(1);
});
