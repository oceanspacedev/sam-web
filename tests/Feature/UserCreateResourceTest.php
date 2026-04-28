<?php

use App\Filament\Resources\Users\Pages\CreateUser as CreateUserPage;
use App\Filament\Resources\Users\Pages\EditUser as EditUserPage;
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

it('keeps valid child selections when adding multiple badan usaha on user create', function (): void {
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
        ->toMatchArray([$buA->id, $buB->id])
        ->and($createdUser->divisis()->pluck('divisions.id')->all())->toEqual([$divisionA->id])
        ->and($createdUser->regions()->pluck('regions.id')->all())->toEqual([$regionA->id])
        ->and($createdUser->clusters()->pluck('clusters.id')->all())->toEqual([$clusterA->id]);
});
