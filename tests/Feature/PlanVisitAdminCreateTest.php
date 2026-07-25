<?php

use App\Filament\Resources\PlanVisits\Pages\CreatePlanVisit as CreatePlanVisitPage;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\PlanVisit;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function planVisitAdminActor(): array
{
    Filament::setCurrentPanel('admin');

    $permission = Permission::firstOrCreate([
        'name' => 'Create:PlanVisit',
        'guard_name' => 'web',
    ]);

    $adminRole = Role::factory()->create([
        'name' => 'SUPER ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);
    $adminRole->syncPermissions([$permission]);

    $actor = User::factory()->create(['role_id' => $adminRole->id]);
    $actor->assignRole($adminRole);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $bu = BadanUsaha::create(['name' => 'BU-ADM-'.uniqid()]);
    $div = Division::create(['name' => 'DIV-ADM-'.uniqid(), 'badanusaha_id' => $bu->id]);
    $reg = Region::create(['name' => 'REG-ADM-'.uniqid(), 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = Cluster::create([
        'name' => 'CLUS-ADM-'.uniqid(),
        'badanusaha_id' => $bu->id,
        'divisi_id' => $div->id,
        'region_id' => $reg->id,
    ]);

    $sales = User::factory()->create(['role_id' => $adminRole->id]);
    $sales->badanUsahas()->attach($bu->id);
    $sales->divisis()->attach($div->id);
    $sales->regions()->attach($reg->id);
    $sales->clusters()->attach($clus->id);

    $outlet = Outlet::create([
        'kode_outlet' => 'ADM-'.uniqid(),
        'nama_outlet' => 'Outlet Admin',
        'alamat_outlet' => 'Alamat Admin',
        'nama_pemilik_outlet' => 'Pemilik',
        'nomer_tlp_outlet' => '081234567890',
        'distric' => 'D01',
        'latlong' => '-6.2000,106.8166',
        'badanusaha_id' => $bu->id,
        'divisi_id' => $div->id,
        'region_id' => $reg->id,
        'cluster_id' => $clus->id,
        'status_outlet' => 'MAINTAIN',
    ]);

    return [$actor, $sales->fresh(), $outlet];
}

it('reuses a soft-deleted plan when admin recreates the same period', function () {
    [$actor, $sales, $outlet] = planVisitAdminActor();
    $this->actingAs($actor);

    $planDate = now()->startOfDay()->addDays(5);
    $payload = array_merge(
        PlanVisit::schedulePayload($planDate, 'daily'),
        [
            'user_id' => $sales->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    );

    $plan = PlanVisit::create($payload);
    $plan->delete();

    Livewire::test(CreatePlanVisitPage::class)
        ->fillForm([
            'user_id' => $sales->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
            'schedule_scope' => 'daily',
            'tanggal_visit' => $planDate->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(
        PlanVisit::withTrashed()
            ->where('user_id', $sales->id)
            ->where('visitable_id', $outlet->id)
            ->whereDate('period_start', $planDate->toDateString())
            ->count()
    )->toBe(1)
        ->and(PlanVisit::find($plan->id))->not->toBeNull();
});

it('shows a form error instead of failing silently on duplicate admin create', function () {
    [$actor, $sales, $outlet] = planVisitAdminActor();
    $this->actingAs($actor);

    $planDate = now()->startOfDay()->addDays(4);

    PlanVisit::create(array_merge(
        PlanVisit::schedulePayload($planDate, 'daily'),
        [
            'user_id' => $sales->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    ));

    Livewire::test(CreatePlanVisitPage::class)
        ->fillForm([
            'user_id' => $sales->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
            'schedule_scope' => 'daily',
            'tanggal_visit' => $planDate->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_visit']);

    expect(
        PlanVisit::query()
            ->where('user_id', $sales->id)
            ->where('visitable_id', $outlet->id)
            ->whereDate('period_start', $planDate->toDateString())
            ->count()
    )->toBe(1);
});
