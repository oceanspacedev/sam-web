<?php

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Support\VisitTypeHealer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds extracall visits that are already linked to realized plans', function () {
    $user = User::factory()->create([
        'role_id' => Role::factory()->create(['can_access_web' => false])->id,
    ]);

    $outlet = Outlet::factory()->create();

    $visit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => '2026-07-25',
        'tipe_visit' => 'EXTRACALL',
    ]);

    PlanVisit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'realized_visit_id' => $visit->id,
        'realized_at' => now(),
    ]);

    expect(VisitTypeHealer::plannedVisitIds())->toContain($visit->id);
});

it('finds extracall visits by realized plan date when the link column is missing', function () {
    $user = User::factory()->create([
        'role_id' => Role::factory()->create(['can_access_web' => false])->id,
    ]);

    $outlet = Outlet::factory()->create();

    $visit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => '2026-07-25',
        'tipe_visit' => 'EXTRACALL',
    ]);

    PlanVisit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'realized_visit_id' => null,
        'realized_at' => '2026-07-25 10:30:00',
    ]);

    expect(VisitTypeHealer::plannedVisitIds())->toContain($visit->id);
});