<?php

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Support\FilamentMorphVisitableSearch;

it('finds visits by user name without querying distinct visitable types', function (): void {
    $role = Role::factory()->create(['can_access_web' => false]);
    $matchedUser = User::factory()->create([
        'role_id' => $role->id,
        'nama_lengkap' => 'SYAHWILDAN',
    ]);
    $otherUser = User::factory()->create([
        'role_id' => $role->id,
        'nama_lengkap' => 'BUDI SANTOSO',
    ]);

    $matchedVisit = Visit::factory()->create([
        'user_id' => $matchedUser->id,
        'tipe_visit' => 'EXTRACALL',
    ]);
    $otherVisit = Visit::factory()->create([
        'user_id' => $otherUser->id,
        'tipe_visit' => 'EXTRACALL',
    ]);

    $query = Visit::query();
    FilamentMorphVisitableSearch::apply($query, 'SYAHWILDAN');

    $ids = $query->pluck('id')->all();

    expect($query->toSql())->not->toContain('distinct')
        ->and($ids)->toContain($matchedVisit->id)
        ->and($ids)->not->toContain($otherVisit->id);
});

it('finds visits by outlet name or register name', function (): void {
    $role = Role::factory()->create(['can_access_web' => false]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $outlet = Outlet::factory()->create([
        'nama_outlet' => 'TOKO SYAH CELL',
        'kode_outlet' => 'OUT-SYAH-1',
    ]);
    $register = Register::factory()->create([
        'nama_outlet' => 'REGISTER WILDAN',
        'kode_outlet' => 'REG-WILDAN-1',
    ]);
    $otherOutlet = Outlet::factory()->create([
        'nama_outlet' => 'TOKO LAIN',
        'kode_outlet' => 'OUT-OTHER-1',
    ]);

    $outletVisit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tipe_visit' => 'EXTRACALL',
    ]);
    $registerVisit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Register::class,
        'visitable_id' => $register->id,
        'tipe_visit' => 'EXTRACALL',
    ]);
    $otherVisit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $otherOutlet->id,
        'tipe_visit' => 'EXTRACALL',
    ]);

    $outletQuery = Visit::query();
    FilamentMorphVisitableSearch::apply($outletQuery, 'SYAH CELL');

    expect($outletQuery->pluck('id')->all())
        ->toContain($outletVisit->id)
        ->and($outletQuery->pluck('id')->all())
        ->not->toContain($registerVisit->id)
        ->and($outletQuery->pluck('id')->all())
        ->not->toContain($otherVisit->id);

    $registerQuery = Visit::query();
    FilamentMorphVisitableSearch::apply($registerQuery, 'WILDAN');

    expect($registerQuery->pluck('id')->all())
        ->toContain($registerVisit->id)
        ->and($registerQuery->pluck('id')->all())
        ->not->toContain($outletVisit->id);
});

it('applies the same search to plan visits', function (): void {
    $role = Role::factory()->create(['can_access_web' => false]);
    $matchedUser = User::factory()->create([
        'role_id' => $role->id,
        'nama_lengkap' => 'SYAHWILDAN',
    ]);
    $otherUser = User::factory()->create([
        'role_id' => $role->id,
        'nama_lengkap' => 'BUDI SANTOSO',
    ]);

    $matchedPlan = PlanVisit::factory()->create(['user_id' => $matchedUser->id]);
    $otherPlan = PlanVisit::factory()->create(['user_id' => $otherUser->id]);

    $query = PlanVisit::query();
    FilamentMorphVisitableSearch::apply($query, 'SYAHWILDAN');

    expect($query->pluck('id')->all())
        ->toContain($matchedPlan->id)
        ->and($query->pluck('id')->all())
        ->not->toContain($otherPlan->id);
});
