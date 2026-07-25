<?php

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('downgrades planned visits that are not linked to any plan visit', function () {
    $user = User::factory()->create([
        'role_id' => Role::factory()->create(['can_access_web' => false])->id,
    ]);
    $outlet = Outlet::factory()->create();

    $orphan = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => '2026-07-20',
        'tipe_visit' => 'PLANNED',
    ]);

    $linked = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => '2026-07-21',
        'tipe_visit' => 'PLANNED',
    ]);

    PlanVisit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'realized_visit_id' => $linked->id,
        'realized_at' => '2026-07-21 10:00:00',
    ]);

    Artisan::call('visits:clean-orphan-planned');

    expect($orphan->fresh()->tipe_visit)->toBe('EXTRACALL')
        ->and($linked->fresh()->tipe_visit)->toBe('PLANNED');
});

it('dry-run does not change orphan planned visits', function () {
    $user = User::factory()->create([
        'role_id' => Role::factory()->create(['can_access_web' => false])->id,
    ]);
    $outlet = Outlet::factory()->create();

    $orphan = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => '2026-07-20',
        'tipe_visit' => 'PLANNED',
    ]);

    Artisan::call('visits:clean-orphan-planned', ['--dry-run' => true]);

    expect($orphan->fresh()->tipe_visit)->toBe('PLANNED');
});
