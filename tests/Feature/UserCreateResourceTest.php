<?php

use App\Filament\Resources\Users\Pages\CreateUser as CreateUserPage;
use App\Filament\Resources\Users\Pages\EditUser as EditUserPage;
use App\Filament\Resources\Users\UserResource;
use App\Jobs\SendUserWhatsAppRegisteredNotificationJob;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\FonnteWhatsAppService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

it('allows admin to set whatsapp number when creating user', function (): void {
    Filament::setCurrentPanel('admin');
    Queue::fake();

    $createUserPermission = Permission::firstOrCreate([
        'name' => 'Create:User',
        'guard_name' => 'web',
    ]);

    $adminRole = Role::factory()->create([
        'name' => 'SUPER ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);
    $adminRole->syncPermissions([$createUserPermission]);

    $actor = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);
    $actor->assignRole($adminRole);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($actor);

    Livewire::test(CreateUserPage::class)
        ->fillForm([
            'username' => 'user-whatsapp-create',
            'nama_lengkap' => 'USER WHATSAPP CREATE',
            'whatsapp_number' => '081234567890',
            'password' => 'Password123!',
            'role_id' => $adminRole->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $createdUser = User::query()->where('username', 'user-whatsapp-create')->firstOrFail();

    expect($createdUser->whatsapp_number)->toBe('6281234567890')
        ->and($createdUser->whatsapp_verified_at)->not->toBeNull();

    Queue::assertPushed(
        SendUserWhatsAppRegisteredNotificationJob::class,
        fn (SendUserWhatsAppRegisteredNotificationJob $job): bool => $job->userId === $createdUser->id
    );
});

it('sends whatsapp registered message for active whatsapp user', function (): void {
    $user = User::factory()->create([
        'nama_lengkap' => 'USER WHATSAPP READY',
        'whatsapp_number' => '6281234567890',
        'whatsapp_verified_at' => now(),
    ]);

    $this->mock(FonnteWhatsAppService::class)
        ->shouldReceive('sendAccountRegistered')
        ->once()
        ->with('6281234567890', 'USER WHATSAPP READY');

    app(SendUserWhatsAppRegisteredNotificationJob::class, ['userId' => $user->id])
        ->handle(app(FonnteWhatsAppService::class));
});

it('notifies user when admin adds whatsapp number on edit', function (): void {
    Filament::setCurrentPanel('admin');
    Queue::fake();

    $updateUserPermission = Permission::firstOrCreate([
        'name' => 'Update:User',
        'guard_name' => 'web',
    ]);

    $adminRole = Role::factory()->create([
        'name' => 'SUPER ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);
    $adminRole->syncPermissions([$updateUserPermission]);

    $actor = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);
    $actor->assignRole($adminRole);

    $targetUser = User::factory()->create([
        'role_id' => $adminRole->id,
        'whatsapp_number' => null,
        'whatsapp_verified_at' => null,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($actor);

    Livewire::test(EditUserPage::class, ['record' => $targetUser->getKey()])
        ->fillForm([
            'whatsapp_number' => '081234567890',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $targetUser->refresh();

    expect($targetUser->whatsapp_number)->toBe('6281234567890')
        ->and($targetUser->whatsapp_verified_at)->not->toBeNull();

    Queue::assertPushed(
        SendUserWhatsAppRegisteredNotificationJob::class,
        fn (SendUserWhatsAppRegisteredNotificationJob $job): bool => $job->userId === $targetUser->id
    );
});

it('clears stale organizational assignments when role changes on user create', function (): void {
    Filament::setCurrentPanel('admin');

    $createUserPermission = Permission::firstOrCreate([
        'name' => 'Create:User',
        'guard_name' => 'web',
    ]);

    $adminRole = Role::factory()->create([
        'name' => 'SUPER ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);
    $adminRole->syncPermissions([$createUserPermission]);

    $actor = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);
    $actor->assignRole($adminRole);

    $clusterRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
        'can_access_web' => true,
    ]);

    $badanUsahaRole = Role::factory()->create([
        'organizational_scope_level' => 'badanusaha',
        'can_access_web' => true,
    ]);

    $buA = BadanUsaha::factory()->create();
    $buB = BadanUsaha::factory()->create();

    $divisionA = Division::factory()->create(['badanusaha_id' => $buA->id]);
    $regionA = Region::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
    ]);
    $clusterA = Cluster::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
        'region_id' => $regionA->id,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($actor);

    Livewire::test(CreateUserPage::class)
        ->fillForm([
            'username' => 'user-create-switch',
            'nama_lengkap' => 'USER CREATE SWITCH',
            'password' => 'Password123!',
            'role_id' => $clusterRole->id,
            'badanUsahas' => [$buA->id],
            'divisis' => [$divisionA->id],
            'regions' => [$regionA->id],
            'clusters' => [$clusterA->id],
        ])
        ->fillForm([
            'role_id' => $badanUsahaRole->id,
            'badanUsahas' => [$buB->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $createdUser = User::query()->where('username', 'user-create-switch')->firstOrFail();

    expect($createdUser->badanUsahas()->pluck('badan_usahas.id')->all())
        ->toEqual([$buB->id])
        ->and($createdUser->divisis()->count())->toBe(0)
        ->and($createdUser->regions()->count())->toBe(0)
        ->and($createdUser->clusters()->count())->toBe(0);
});

it('prunes unrelated parent selections when adding multiple badan usaha on user create', function (): void {
    Filament::setCurrentPanel('admin');

    $createUserPermission = Permission::firstOrCreate([
        'name' => 'Create:User',
        'guard_name' => 'web',
    ]);

    $adminRole = Role::factory()->create([
        'name' => 'SUPER ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);
    $adminRole->syncPermissions([$createUserPermission]);

    $actor = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);
    $actor->assignRole($adminRole);

    $clusterRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
        'can_access_web' => true,
    ]);

    $buA = BadanUsaha::factory()->create();
    $buB = BadanUsaha::factory()->create();

    $divisionA = Division::factory()->create(['badanusaha_id' => $buA->id]);
    Division::factory()->create(['badanusaha_id' => $buB->id]);

    $regionA = Region::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
    ]);

    $clusterA = Cluster::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
        'region_id' => $regionA->id,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($actor);

    Livewire::test(CreateUserPage::class)
        ->fillForm([
            'username' => 'user-multi-bu',
            'nama_lengkap' => 'USER MULTI BU',
            'password' => 'Password123!',
            'role_id' => $clusterRole->id,
            'badanUsahas' => [$buA->id],
            'divisis' => [$divisionA->id],
            'regions' => [$regionA->id],
            'clusters' => [$clusterA->id],
        ])
        ->fillForm([
            'badanUsahas' => [$buA->id, $buB->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $createdUser = User::query()->where('username', 'user-multi-bu')->firstOrFail();

    expect($createdUser->badanUsahas()->pluck('badan_usahas.id')->all())
        ->toEqual([$buA->id])
        ->and($createdUser->divisis()->pluck('divisions.id')->all())->toEqual([$divisionA->id])
        ->and($createdUser->regions()->pluck('regions.id')->all())->toEqual([$regionA->id])
        ->and($createdUser->clusters()->pluck('clusters.id')->all())->toEqual([$clusterA->id]);
});

function createAdminActor(array $permissions, string $scopeLevel = 'all'): User
{
    Filament::setCurrentPanel('admin');

    $permissionModels = collect($permissions)
        ->map(fn (string $name): Permission => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]))
        ->all();

    $adminRole = Role::factory()->create([
        'name' => 'SUPER ADMIN',
        'can_access_web' => true,
        'organizational_scope_level' => $scopeLevel,
    ]);
    $adminRole->syncPermissions($permissionModels);

    $actor = User::factory()->create([
        'role_id' => $adminRole->id,
    ]);
    $actor->assignRole($adminRole);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $actor;
}

it('validateActorOrganizationalAssignments rejects unassigned scoped actor', function (): void {
    $actor = createAdminActor(['Create:User'], scopeLevel: 'divisi');
    $this->actingAs($actor);

    $clusterRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
        'can_access_web' => true,
    ]);

    UserResource::validateActorOrganizationalAssignments([
        'role_id' => $clusterRole->id,
    ]);
})->throws(\Illuminate\Validation\ValidationException::class);

it('clears stale organizational assignments when role changes on user edit', function (): void {
    $actor = createAdminActor(['Update:User']);
    $this->actingAs($actor);

    $clusterRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
        'can_access_web' => true,
    ]);

    $badanUsahaRole = Role::factory()->create([
        'organizational_scope_level' => 'badanusaha',
        'can_access_web' => true,
    ]);

    $buA = BadanUsaha::factory()->create();
    $buB = BadanUsaha::factory()->create();

    $divisionA = Division::factory()->create(['badanusaha_id' => $buA->id]);
    $regionA = Region::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
    ]);
    $clusterA = Cluster::factory()->create([
        'badanusaha_id' => $buA->id,
        'divisi_id' => $divisionA->id,
        'region_id' => $regionA->id,
    ]);

    $targetUser = User::factory()->create([
        'role_id' => $clusterRole->id,
    ]);
    $targetUser->badanUsahas()->sync([$buA->id]);
    $targetUser->divisis()->sync([$divisionA->id]);
    $targetUser->regions()->sync([$regionA->id]);
    $targetUser->clusters()->sync([$clusterA->id]);

    Livewire::test(EditUserPage::class, ['record' => $targetUser->getKey()])
        ->fillForm([
            'role_id' => $badanUsahaRole->id,
            'badanUsahas' => [$buB->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $targetUser->refresh();

    expect($targetUser->role_id)->toBe($badanUsahaRole->id)
        ->and($targetUser->badanUsahas()->pluck('badan_usahas.id')->all())->toEqual([$buB->id])
        ->and($targetUser->divisis()->count())->toBe(0)
        ->and($targetUser->regions()->count())->toBe(0)
        ->and($targetUser->clusters()->count())->toBe(0);
});

it('prunes unrelated parent selections when editing a cluster scoped user', function (): void {
    $actor = createAdminActor(['Update:User']);
    $this->actingAs($actor);

    $clusterRole = Role::factory()->create([
        'name' => 'DSF/DM',
        'organizational_scope_level' => 'cluster',
        'can_access_web' => true,
    ]);

    $buA = BadanUsaha::factory()->create(['name' => 'PT.MSI']);
    $buB = BadanUsaha::factory()->create(['name' => 'CV.TOP']);
    $division = Division::factory()->create([
        'name' => 'MSIS',
        'badanusaha_id' => $buA->id,
    ]);
    $region = Region::factory()->create([
        'name' => 'JABO',
        'badanusaha_id' => $buA->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'name' => 'JABO1',
        'badanusaha_id' => $buA->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    $targetUser = User::factory()->create([
        'role_id' => $clusterRole->id,
    ]);
    $targetUser->badanUsahas()->sync([$buA->id, $buB->id]);
    $targetUser->divisis()->sync([$division->id]);
    $targetUser->regions()->sync([$region->id]);
    $targetUser->clusters()->sync([$cluster->id]);

    Livewire::test(EditUserPage::class, ['record' => $targetUser->getKey()])
        ->fillForm([
            'role_id' => $clusterRole->id,
            'badanUsahas' => [$buB->id, $buA->id],
            'divisis' => [$division->id],
            'regions' => [$region->id],
            'clusters' => [$cluster->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $targetUser->refresh();

    expect($targetUser->badanUsahas()->pluck('badan_usahas.id')->all())->toEqual([$buA->id])
        ->and($targetUser->divisis()->pluck('divisions.id')->all())->toEqual([$division->id])
        ->and($targetUser->regions()->pluck('regions.id')->all())->toEqual([$region->id])
        ->and($targetUser->clusters()->pluck('clusters.id')->all())->toEqual([$cluster->id]);
});

it('detaches removed organization selections when editing a multi path cluster scoped user', function (): void {
    $actor = createAdminActor(['Update:User']);
    $this->actingAs($actor);

    $clusterRole = Role::factory()->create([
        'name' => 'DSF/DM',
        'organizational_scope_level' => 'cluster',
        'can_access_web' => true,
    ]);

    $ptMsi = BadanUsaha::factory()->create(['name' => 'PT.MSI']);
    $cvTop = BadanUsaha::factory()->create(['name' => 'CV.TOP']);

    $msis = Division::factory()->create([
        'name' => 'MSIS',
        'badanusaha_id' => $ptMsi->id,
    ]);
    $oraimo = Division::factory()->create([
        'name' => 'ORAIMO',
        'badanusaha_id' => $cvTop->id,
    ]);

    $aceh = Region::factory()->create([
        'name' => 'ACEH',
        'badanusaha_id' => $ptMsi->id,
        'divisi_id' => $msis->id,
    ]);
    $jabo = Region::factory()->create([
        'name' => 'JABO',
        'badanusaha_id' => $ptMsi->id,
        'divisi_id' => $msis->id,
    ]);
    $bekasi = Region::factory()->create([
        'name' => 'BEKASI',
        'badanusaha_id' => $cvTop->id,
        'divisi_id' => $oraimo->id,
    ]);

    $acehTimur = Cluster::factory()->create([
        'name' => 'ACEH TIMUR',
        'badanusaha_id' => $ptMsi->id,
        'divisi_id' => $msis->id,
        'region_id' => $aceh->id,
    ]);
    $jabo1 = Cluster::factory()->create([
        'name' => 'JABO1',
        'badanusaha_id' => $ptMsi->id,
        'divisi_id' => $msis->id,
        'region_id' => $jabo->id,
    ]);
    $kotaBekasi = Cluster::factory()->create([
        'name' => 'KOTA BEKASI',
        'badanusaha_id' => $cvTop->id,
        'divisi_id' => $oraimo->id,
        'region_id' => $bekasi->id,
    ]);

    $targetUser = User::factory()->create([
        'role_id' => $clusterRole->id,
    ]);
    $targetUser->badanUsahas()->sync([$cvTop->id, $ptMsi->id]);
    $targetUser->divisis()->sync([$msis->id, $oraimo->id]);
    $targetUser->regions()->sync([$aceh->id, $bekasi->id, $jabo->id]);
    $targetUser->clusters()->sync([$acehTimur->id, $jabo1->id, $kotaBekasi->id]);

    Livewire::test(EditUserPage::class, ['record' => $targetUser->getKey()])
        ->fillForm([
            'role_id' => $clusterRole->id,
            'badanUsahas' => [$ptMsi->id],
            'divisis' => [$msis->id],
            'regions' => [$jabo->id],
            'clusters' => [$jabo1->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $targetUser->refresh();

    expect($targetUser->badanUsahas()->pluck('badan_usahas.id')->all())->toEqual([$ptMsi->id])
        ->and($targetUser->divisis()->pluck('divisions.id')->all())->toEqual([$msis->id])
        ->and($targetUser->regions()->pluck('regions.id')->all())->toEqual([$jabo->id])
        ->and($targetUser->clusters()->pluck('clusters.id')->all())->toEqual([$jabo1->id]);
});

it('allows badan usaha scoped actor to assign descendants within owned badan usaha', function (): void {
    $bu = BadanUsaha::factory()->create();
    $actor = createAdminActor(['Create:User'], scopeLevel: 'badanusaha');
    $actor->badanUsahas()->sync([$bu->id]);
    $this->actingAs($actor);

    $clusterRole = Role::factory()->create([
        'organizational_scope_level' => 'cluster',
        'can_access_web' => true,
    ]);

    $bu = BadanUsaha::query()->findOrFail($bu->id);
    $otherBu = BadanUsaha::factory()->create();

    $division = Division::factory()->create(['badanusaha_id' => $bu->id]);
    $region = Region::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $division->id,
    ]);
    $cluster = Cluster::factory()->create([
        'badanusaha_id' => $bu->id,
        'divisi_id' => $division->id,
        'region_id' => $region->id,
    ]);

    Division::factory()->create(['badanusaha_id' => $otherBu->id]);

    Livewire::test(CreateUserPage::class)
        ->fillForm([
            'username' => 'user-bu-scoped',
            'nama_lengkap' => 'USER BU SCOPED',
            'password' => 'Password123!',
            'role_id' => $clusterRole->id,
            'badanUsahas' => [$bu->id],
            'divisis' => [$division->id],
            'regions' => [$region->id],
            'clusters' => [$cluster->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $createdUser = User::query()->where('username', 'user-bu-scoped')->firstOrFail();

    expect($createdUser->badanUsahas()->pluck('badan_usahas.id')->all())->toEqual([$bu->id])
        ->and($createdUser->divisis()->pluck('divisions.id')->all())->toEqual([$division->id])
        ->and($createdUser->regions()->pluck('regions.id')->all())->toEqual([$region->id])
        ->and($createdUser->clusters()->pluck('clusters.id')->all())->toEqual([$cluster->id]);
});
