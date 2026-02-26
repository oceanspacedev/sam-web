<?php

use App\Filament\Resources\Roles\Pages\CreateRole as CreateRolePage;
use App\Filament\Resources\Roles\Pages\EditRole as EditRolePage;
use App\Filament\Resources\Users\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

it('syncs selected permissions when creating and editing a role', function (): void {
    Filament::setCurrentPanel('admin');

    $adminPermissions = collect([
        'Create:Role',
        'ViewAny:Role',
        'View:Role',
        'Update:Role',
        'Delete:Role',
    ])->map(fn (string $name): Permission => Permission::firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]));

    $adminRole = Role::factory()->create([
        'name' => 'SUPER ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);

    $adminRole->syncPermissions($adminPermissions);

    $user = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);

    $user->assignRole($adminRole);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user);

    $resourceKey = UserResource::class;
    $initialPermissions = [
        'Create:User',
        'ViewAny:User',
    ];
    $customPermissions = ['Impersonate'];

    Livewire::test(CreateRolePage::class)
        ->fillForm([
            'name' => 'Regional Manager',
            'parent_role_id' => null,
            'can_access_web' => true,
            'organizational_scope_level' => 'region',
            $resourceKey => $initialPermissions,
            'custom_permissions_tab' => $customPermissions,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $createdRole = Role::where('name', 'Regional Manager')->firstOrFail();

    expect($createdRole->permissions()->pluck('name')->all())
        ->toMatchArray(array_merge($initialPermissions, $customPermissions));

    Livewire::test(EditRolePage::class, ['record' => $createdRole->getKey()])
        ->fillForm([
            'name' => 'Regional Manager',
            'parent_role_id' => null,
            'can_access_web' => true,
            'organizational_scope_level' => 'cluster',
            $resourceKey => ['Update:User'],
            'custom_permissions_tab' => [],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $createdRole->refresh();

    expect($createdRole->permissions()->pluck('name')->all())
        ->toMatchArray(['Update:User']);
});
