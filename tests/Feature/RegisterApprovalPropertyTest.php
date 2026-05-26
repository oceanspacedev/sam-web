<?php

/**
 * Property-Based Tests for Approval Workflow
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the NOO approval workflow.
 * Each test runs multiple iterations with randomly generated valid inputs to verify
 * that invariants hold across all valid executions.
 */

use App\Filament\Resources\Registers\RegisterResource;
use App\Models\Outlet;
use App\Models\OutletChangeArchive;
use App\Models\Register;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsMasterData;

uses(SeedsMasterData::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');

    // Disable rate limiting middleware that requires Redis
    $this->withoutMiddleware(\App\Http\Middleware\RateLimitUploads::class);
});

/**
 * Helper to create authenticated user with organizational hierarchy and approve permission
 */
function createApproveUserWithHierarchy(bool $withApprovePermission = true): array
{
    $suffix = uniqid();

    $bu = \App\Models\BadanUsaha::create(['name' => 'BU-'.$suffix]);
    $div = \App\Models\Division::create(['name' => 'DIV-'.$suffix, 'badanusaha_id' => $bu->id]);
    $reg = \App\Models\Region::create(['name' => 'REG-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = \App\Models\Cluster::create(['name' => 'CLUS-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

    // Create role with 'web' guard (default for Spatie Permission)
    $role = \App\Models\Role::create([
        'name' => 'RM-'.$suffix,
        'guard_name' => 'web',
        'can_access_web' => true,
        'organizational_scope_level' => 'region',
    ]);

    // Create or get the Approve:Register permission with 'web' guard to match role
    if ($withApprovePermission) {
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'Approve:Register', 'guard_name' => 'web'],
            ['name' => 'Approve:Register', 'guard_name' => 'web']
        );
        $role->givePermissionTo($permission);
    }

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    // Assign the role to the user for Spatie permission checks
    $user->assignRole($role);

    // Attach organizational hierarchy to user
    $user->badanUsahas()->attach($bu->id);
    $user->divisis()->attach($div->id);
    $user->regions()->attach($reg->id);
    $user->clusters()->attach($clus->id);

    return ['user' => $user, 'hierarchy' => ['bu' => $bu, 'div' => $div, 'reg' => $reg, 'clus' => $clus, 'role' => $role]];
}

/**
 * Helper to create a confirmed register (status=CONFIRMED)
 */
function createConfirmedRegister(array $hierarchy, int $creatorId, int $confirmerId): Register
{
    return Register::create([
        'nama_outlet' => fake()->company().' '.fake()->randomNumber(3),
        'alamat_outlet' => fake()->address(),
        'nama_pemilik_outlet' => fake()->name(),
        'nomer_tlp_outlet' => '08'.fake()->numerify('##########'),
        'ktp_outlet' => fake()->numerify('################'),
        'distric' => 'D'.fake()->numerify('##'),
        'latlong' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
        'oppo' => '0',
        'vivo' => '0',
        'samsung' => '0',
        'xiaomi' => '0',
        'realme' => '0',
        'fl' => '0',
        'poto_shop_sign' => 'register/photos/sign.jpg',
        'poto_depan' => 'register/photos/front.jpg',
        'poto_kiri' => 'register/photos/left.jpg',
        'poto_kanan' => 'register/photos/right.jpg',
        'poto_ktp' => 'register/ktp/ktp.jpg',
        'video' => 'register/videos/vid.mp4',
        'created_by_id' => $creatorId,
        'tm_id' => $creatorId,
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status' => 'CONFIRMED',
        'keterangan' => null,
        'kode_outlet' => 'OUT-'.strtoupper(fake()->unique()->bothify('??###')),
        'limit' => fake()->numberBetween(1000000, 100000000),
        'confirmed_by_id' => $confirmerId,
        'confirmed_at' => now()->subHour(),
    ]);
}

/**
 * Helper to create a pending register (status=PENDING)
 */
function createPendingRegister(array $hierarchy, int $creatorId): Register
{
    return Register::create([
        'nama_outlet' => fake()->company().' '.fake()->randomNumber(3),
        'alamat_outlet' => fake()->address(),
        'nama_pemilik_outlet' => fake()->name(),
        'nomer_tlp_outlet' => '08'.fake()->numerify('##########'),
        'ktp_outlet' => fake()->numerify('################'),
        'distric' => 'D'.fake()->numerify('##'),
        'latlong' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
        'oppo' => '0',
        'vivo' => '0',
        'samsung' => '0',
        'xiaomi' => '0',
        'realme' => '0',
        'fl' => '0',
        'poto_shop_sign' => 'register/photos/sign.jpg',
        'poto_depan' => 'register/photos/front.jpg',
        'poto_kiri' => 'register/photos/left.jpg',
        'poto_kanan' => 'register/photos/right.jpg',
        'poto_ktp' => 'register/ktp/ktp.jpg',
        'video' => 'register/videos/vid.mp4',
        'created_by_id' => $creatorId,
        'tm_id' => $creatorId,
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status' => 'PENDING',
        'keterangan' => null,
    ]);
}

/**
 * Helper to create an approved register (status=APPROVED)
 */
function createApprovedRegister(array $hierarchy, int $creatorId, int $confirmerId, int $approverId): Register
{
    return Register::create([
        'nama_outlet' => fake()->company().' '.fake()->randomNumber(3),
        'alamat_outlet' => fake()->address(),
        'nama_pemilik_outlet' => fake()->name(),
        'nomer_tlp_outlet' => '08'.fake()->numerify('##########'),
        'ktp_outlet' => fake()->numerify('################'),
        'distric' => 'D'.fake()->numerify('##'),
        'latlong' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
        'oppo' => '0',
        'vivo' => '0',
        'samsung' => '0',
        'xiaomi' => '0',
        'realme' => '0',
        'fl' => '0',
        'poto_shop_sign' => 'register/photos/sign.jpg',
        'poto_depan' => 'register/photos/front.jpg',
        'poto_kiri' => 'register/photos/left.jpg',
        'poto_kanan' => 'register/photos/right.jpg',
        'poto_ktp' => 'register/ktp/ktp.jpg',
        'video' => 'register/videos/vid.mp4',
        'created_by_id' => $creatorId,
        'tm_id' => $creatorId,
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status' => 'APPROVED',
        'keterangan' => null,
        'kode_outlet' => 'OUT-'.strtoupper(fake()->unique()->bothify('??###')),
        'limit' => fake()->numberBetween(1000000, 100000000),
        'confirmed_by_id' => $confirmerId,
        'confirmed_at' => now()->subHours(2),
        'approved_by_id' => $approverId,
        'approved_at' => now()->subHour(),
    ]);
}

/**
 * **Feature: register-workflow, Property 10: Approval State Transition**
 *
 * *For any* Register with status='CONFIRMED', after approval, the Register SHALL have
 * status='APPROVED', approved_by_id set, and approved_at set.
 *
 * **Validates: Requirements 5.1**
 */
test('Property 10: Approval State Transition - approving confirmed NOO sets APPROVED status and audit fields', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with approve permission
        $setup = createApproveUserWithHierarchy(withApprovePermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a confirmed register
        $register = createConfirmedRegister($hierarchy, $user->id, $user->id);

        // Verify initial state
        expect($register->status)->toBe('CONFIRMED')
            ->and($register->approved_by_id)->toBeNull()
            ->and($register->approved_at)->toBeNull();

        // Act: Approve the NOO
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);

        // Assert: Response is successful
        $response->assertStatus(200);
        $response->assertJson([
            'meta' => [
                'code' => 200,
                'status' => 'success',
            ],
        ]);

        // Assert: Register was updated with correct invariants
        $register->refresh();

        expect($register->status)->toBe('APPROVED', 'Status should be APPROVED after approval')
            ->and($register->approved_by_id)->toBe($user->id, 'approved_by_id should match authenticated user')
            ->and($register->approved_at)->not->toBeNull('approved_at should be set');
    }
});

/**
 * **Feature: register-workflow, Property 11: Approval Creates Outlet**
 *
 * *For any* Register that transitions to status='APPROVED', an Outlet record SHALL exist
 * with register_id matching the Register, and outlet data (kode_outlet, nama_outlet,
 * alamat_outlet, latlong, limit, photos) matching the Register.
 *
 * **Validates: Requirements 5.2**
 */
test('Property 11: Approval Creates Outlet - approving NOO creates matching Outlet record', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with approve permission
        $setup = createApproveUserWithHierarchy(withApprovePermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a confirmed register with unique data
        $register = createConfirmedRegister($hierarchy, $user->id, $user->id);

        // Verify no outlet exists for this register yet
        $existingOutlet = Outlet::where('register_id', $register->id)->first();
        expect($existingOutlet)->toBeNull('No outlet should exist before approval');

        // Act: Approve the NOO
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);

        // Assert: Response is successful
        $response->assertStatus(200);

        // Assert: Outlet was created with matching data
        $outlet = Outlet::where('register_id', $register->id)->first();

        expect($outlet)->not->toBeNull('Outlet should be created after approval')
            ->and($outlet->register_id)->toBe($register->id, 'Outlet register_id should match')
            ->and($outlet->kode_outlet)->toBe($register->kode_outlet, 'Outlet kode_outlet should match')
            ->and($outlet->nama_outlet)->toBe($register->nama_outlet, 'Outlet nama_outlet should match')
            ->and($outlet->alamat_outlet)->toBe($register->alamat_outlet, 'Outlet alamat_outlet should match')
            ->and($outlet->latlong)->toBe($register->latlong, 'Outlet latlong should match')
            ->and($outlet->limit)->toBe($register->limit, 'Outlet limit should match')
            ->and($outlet->poto_shop_sign)->toBe($register->poto_shop_sign, 'Outlet poto_shop_sign should match')
            ->and($outlet->poto_depan)->toBe($register->poto_depan, 'Outlet poto_depan should match')
            ->and($outlet->poto_kiri)->toBe($register->poto_kiri, 'Outlet poto_kiri should match')
            ->and($outlet->poto_kanan)->toBe($register->poto_kanan, 'Outlet poto_kanan should match')
            ->and($outlet->poto_ktp)->toBe($register->poto_ktp, 'Outlet poto_ktp should match')
            ->and($outlet->video)->toBe($register->video, 'Outlet video should match');
    }
});

/**
 * **Feature: register-workflow, Property 12: Approval Idempotency**
 *
 * *For any* Register with status='APPROVED' that already has an associated Outlet,
 * re-approval SHALL update the existing Outlet rather than creating a duplicate.
 *
 * **Validates: Requirements 5.4**
 */
test('Property 12: Approval Idempotency - re-approving updates existing Outlet instead of creating duplicate', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with approve permission
        $setup = createApproveUserWithHierarchy(withApprovePermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a confirmed register
        $register = createConfirmedRegister($hierarchy, $user->id, $user->id);

        // First approval - creates outlet
        $response1 = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);
        $response1->assertStatus(200);

        // Get the created outlet
        $outlet = Outlet::where('register_id', $register->id)->first();
        expect($outlet)->not->toBeNull('Outlet should exist after first approval');
        $originalOutletId = $outlet->id;
        $outletCountBefore = Outlet::where('register_id', $register->id)->count();
        expect($outletCountBefore)->toBe(1, 'Should have exactly one outlet');

        // Update register data to verify outlet gets updated
        $register->refresh();
        $newNamaOutlet = fake()->company().' Updated '.fake()->randomNumber(3);
        $register->nama_outlet = $newNamaOutlet;
        $register->status = 'CONFIRMED'; // Reset to CONFIRMED to allow re-approval
        $register->approved_by_id = null;
        $register->approved_at = null;
        $register->save();

        // Second approval - should update existing outlet
        $response2 = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);
        $response2->assertStatus(200);

        // Assert: No duplicate outlet created
        $outletCountAfter = Outlet::where('register_id', $register->id)->count();
        expect($outletCountAfter)->toBe(1, 'Should still have exactly one outlet (no duplicate)');

        // Assert: Existing outlet was updated
        $outlet->refresh();
        expect($outlet->id)->toBe($originalOutletId, 'Outlet ID should remain the same')
            ->and($outlet->nama_outlet)->toBe($newNamaOutlet, 'Outlet nama_outlet should be updated');
    }
});

test('approval can turn duplicate outlet code into sequential branch code', function () {
    $setup = createApproveUserWithHierarchy(withApprovePermission: true);
    $user = $setup['user'];
    $hierarchy = $setup['hierarchy'];

    foreach (['COMPLETE', 'COMPLETE-CB1', 'COMPLETE-CB2'] as $kodeOutlet) {
        Outlet::factory()->create([
            'kode_outlet' => $kodeOutlet,
            'badanusaha_id' => $hierarchy['bu']->id,
            'divisi_id' => $hierarchy['div']->id,
            'region_id' => $hierarchy['reg']->id,
            'cluster_id' => $hierarchy['clus']->id,
        ]);
    }

    $firstRegister = createConfirmedRegister($hierarchy, $user->id, $user->id);
    $firstRegister->forceFill(['kode_outlet' => 'COMPLETE'])->save();

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/registers/'.$firstRegister->id.'/approve', [
            'id' => $firstRegister->id,
            'status' => 'APPROVED',
            'duplicate_resolution' => 'branch',
        ])
        ->assertOk()
        ->assertJsonPath('meta.final_kode_outlet', 'COMPLETE-CB3');

    $secondRegister = createConfirmedRegister($hierarchy, $user->id, $user->id);
    $secondRegister->forceFill(['kode_outlet' => 'COMPLETE'])->save();

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/registers/'.$secondRegister->id.'/approve', [
            'id' => $secondRegister->id,
            'status' => 'APPROVED',
            'duplicate_resolution' => 'branch',
        ])
        ->assertOk()
        ->assertJsonPath('meta.final_kode_outlet', 'COMPLETE-CB4');

    expect($firstRegister->refresh()->kode_outlet)->toBe('COMPLETE-CB3')
        ->and($secondRegister->refresh()->kode_outlet)->toBe('COMPLETE-CB4')
        ->and(Outlet::where('divisi_id', $hierarchy['div']->id)->where('kode_outlet', 'COMPLETE-CB3')->exists())->toBeTrue()
        ->and(Outlet::where('divisi_id', $hierarchy['div']->id)->where('kode_outlet', 'COMPLETE-CB4')->exists())->toBeTrue();
});

test('duplicate outlet code check suggests branch when same outlet name has different address', function () {
    $setup = createApproveUserWithHierarchy(withApprovePermission: true);
    $user = $setup['user'];
    $hierarchy = $setup['hierarchy'];

    Outlet::factory()->create([
        'kode_outlet' => 'COMPLETE',
        'nama_outlet' => 'TOKO COMPLETE',
        'alamat_outlet' => 'Jalan Lama Nomor 1',
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
    ]);

    $otherRegion = \App\Models\Region::create([
        'name' => 'REG-CABANG-'.uniqid(),
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
    ]);
    $otherCluster = \App\Models\Cluster::create([
        'name' => 'CLUS-CABANG-'.uniqid(),
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $otherRegion->id,
    ]);

    $register = createConfirmedRegister($hierarchy, $user->id, $user->id);
    $register->forceFill([
        'kode_outlet' => 'COMPLETE',
        'nama_outlet' => 'TOKO COMPLETE',
        'alamat_outlet' => 'Jalan Baru Nomor 99',
        'region_id' => $otherRegion->id,
        'cluster_id' => $otherCluster->id,
    ])->save();

    $result = RegisterResource::duplicateOutletCodeCheckResult($register, 'COMPLETE');

    expect($result['has_duplicate'])->toBeTrue()
        ->and($result['duplicate_suggestion_title'])->toContain('cabang')
        ->and($result['next_branch_code'])->toBe('COMPLETE-CB1');
});

test('approval form requires outlet code to be checked again after code changes', function () {
    $setup = createApproveUserWithHierarchy(withApprovePermission: true);
    $user = $setup['user'];
    $hierarchy = $setup['hierarchy'];
    $register = createConfirmedRegister($hierarchy, $user->id, $user->id);

    RegisterResource::validateOutletCodeCheckBeforeApproval($register, [
        'kode_outlet' => 'COMPLETE-BARU',
        'checked_kode_outlet' => 'COMPLETE-LAMA',
        'limit' => 1000000,
    ]);
})->throws(ValidationException::class, 'Cek kode outlet lagi setelah mengubah kode.');

test('approval can override existing outlet and archives previous values', function () {
    $setup = createApproveUserWithHierarchy(withApprovePermission: true);
    $user = $setup['user'];
    $hierarchy = $setup['hierarchy'];

    $existingOutlet = Outlet::factory()->create([
        'kode_outlet' => 'COMPLETE',
        'nama_outlet' => 'Outlet Lama',
        'alamat_outlet' => 'Alamat Lama',
        'nama_pemilik_outlet' => 'Pemilik Lama',
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
    ]);

    $register = createConfirmedRegister($hierarchy, $user->id, $user->id);
    $register->forceFill([
        'kode_outlet' => 'COMPLETE',
        'nama_outlet' => 'Outlet Baru',
        'alamat_outlet' => 'Alamat Baru',
        'nama_pemilik_outlet' => 'Pemilik Baru',
    ])->save();

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/registers/'.$register->id.'/approve', [
            'id' => $register->id,
            'status' => 'APPROVED',
            'duplicate_resolution' => 'override',
        ])
        ->assertOk()
        ->assertJsonPath('meta.outlet_id', $existingOutlet->id)
        ->assertJsonPath('meta.duplicate_resolution', 'override')
        ->assertJsonPath('meta.final_kode_outlet', 'COMPLETE');

    $archive = OutletChangeArchive::query()->firstOrFail();
    $existingOutlet->refresh();

    expect($existingOutlet->register_id)->toBe($register->id)
        ->and($existingOutlet->nama_outlet)->toBe('Outlet Baru')
        ->and($archive->action)->toBe(OutletChangeArchive::ACTION_APPROVAL_OVERRIDE)
        ->and($archive->old_values['nama_outlet'])->toBe('Outlet Lama')
        ->and($archive->new_values['nama_outlet'])->toBe('Outlet Baru')
        ->and($archive->changed_fields)->toContain('nama_outlet', 'alamat_outlet', 'register_id');
});
