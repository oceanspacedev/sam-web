<?php

use App\Filament\Resources\Visits\Pages\ListVisits;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function visitSearchAdmin(): User
{
    Filament::setCurrentPanel('admin');

    $permission = Permission::firstOrCreate([
        'name' => 'ViewAny:Visit',
        'guard_name' => 'web',
    ]);

    $role = Role::factory()->create([
        'name' => 'VISIT SEARCH ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);
    $role->syncPermissions([$permission]);

    $admin = User::factory()->create(['role_id' => $role->id]);
    $admin->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $admin;
}

it('searches visits by user name without error', function (): void {
    $admin = visitSearchAdmin();
    $this->actingAs($admin);

    $matchedUser = User::factory()->create([
        'role_id' => $admin->role_id,
        'nama_lengkap' => 'SYAHWILDAN',
    ]);
    $otherUser = User::factory()->create([
        'role_id' => $admin->role_id,
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

    Livewire::test(ListVisits::class)
        ->loadTable()
        ->searchTable('SYAHWILDAN')
        ->assertCanSeeTableRecords([$matchedVisit])
        ->assertCanNotSeeTableRecords([$otherVisit]);
});

it('searches visits by outlet and register names', function (): void {
    $admin = visitSearchAdmin();
    $this->actingAs($admin);

    $user = User::factory()->create(['role_id' => $admin->role_id]);

    $outlet = Outlet::factory()->create([
        'nama_outlet' => 'TOKO SYAH CELL',
        'kode_outlet' => 'OUT-SYAH-1',
    ]);
    $register = Register::factory()->create([
        'nama_outlet' => 'REGISTER WILDAN',
        'kode_outlet' => 'REG-WILDAN-1',
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

    Livewire::test(ListVisits::class)
        ->loadTable()
        ->searchTable('SYAH CELL')
        ->assertCanSeeTableRecords([$outletVisit])
        ->assertCanNotSeeTableRecords([$registerVisit]);

    Livewire::test(ListVisits::class)
        ->loadTable()
        ->searchTable('WILDAN')
        ->assertCanSeeTableRecords([$registerVisit])
        ->assertCanNotSeeTableRecords([$outletVisit]);
});
