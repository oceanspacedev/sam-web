<?php

/**
 * Property-Based Tests for Rejection Workflow
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the NOO rejection workflow.
 * Each test runs multiple iterations with randomly generated valid inputs to verify
 * that invariants hold across all valid executions.
 */

use App\Models\Register;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsMasterData;

uses(SeedsMasterData::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');

    // Disable rate limiting middleware that requires Redis
    $this->withoutMiddleware(\App\Http\Middleware\RateLimitUploads::class);
});

/**
 * Helper to create authenticated user with organizational hierarchy and reject permission
 */
function createRejectUserWithHierarchy(bool $withRejectPermission = true): array
{
    $suffix = uniqid();

    $bu = \App\Models\BadanUsaha::create(['name' => 'BU-'.$suffix]);
    $div = \App\Models\Division::create(['name' => 'DIV-'.$suffix, 'badanusaha_id' => $bu->id]);
    $reg = \App\Models\Region::create(['name' => 'REG-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = \App\Models\Cluster::create(['name' => 'CLUS-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

    // Create role with 'web' guard (default for Spatie Permission)
    $role = \App\Models\Role::create([
        'name' => 'TL-'.$suffix,
        'guard_name' => 'web',
        'can_access_web' => true,
        'organizational_scope_level' => 'cluster',
    ]);

    // Create or get the Reject:Register permission with 'web' guard to match role
    if ($withRejectPermission) {
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'Reject:Register', 'guard_name' => 'web'],
            ['name' => 'Reject:Register', 'guard_name' => 'web']
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
 * Helper to create a pending register (status=PENDING, keterangan=null)
 */
function createPendingRegisterForRejection(array $hierarchy, int $creatorId): Register
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
 * Helper to create a confirmed register (status=CONFIRMED)
 */
function createConfirmedRegisterForRejection(array $hierarchy, int $creatorId, int $confirmerId): Register
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
 * Helper to create an approved register (status=APPROVED)
 */
function createApprovedRegisterForRejection(array $hierarchy, int $creatorId, int $confirmerId, int $approverId): Register
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
 * Helper to create a rejected register (status=REJECTED)
 */
function createRejectedRegisterForRejection(array $hierarchy, int $creatorId, int $rejecterId): Register
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
        'status' => 'REJECTED',
        'keterangan' => 'Previous rejection reason',
        'rejected_by_id' => $rejecterId,
        'rejected_at' => now()->subHour(),
    ]);
}

/**
 * Helper to generate random rejection reason
 */
function generateRejectionReason(): string
{
    $reasons = [
        'Data tidak lengkap',
        'Foto KTP tidak jelas',
        'Alamat tidak valid',
        'Outlet sudah terdaftar',
        'Lokasi tidak sesuai',
        'Dokumen tidak valid',
        'Informasi pemilik tidak akurat',
    ];

    return fake()->randomElement($reasons).' - '.fake()->sentence(3);
}

/**
 * **Feature: register-workflow, Property 13: Rejection State Transition**
 *
 * *For any* Register with status in (PENDING, 'CONFIRMED'), after rejection with reason,
 * the Register SHALL have status='REJECTED', rejected_by_id set, rejected_at set,
 * and keterangan containing the rejection reason.
 *
 * **Validates: Requirements 6.1**
 */
test('Property 13: Rejection State Transition - rejecting pending NOO sets REJECTED status and audit fields', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createRejectUserWithHierarchy(withRejectPermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Randomly choose between PENDING and CONFIRMED status
        $initialStatus = fake()->randomElement(['PENDING', 'CONFIRMED']);

        if ($initialStatus === 'PENDING') {
            $register = createPendingRegisterForRejection($hierarchy, $user->id);
        } else {
            $register = createConfirmedRegisterForRejection($hierarchy, $user->id, $user->id);
        }

        // Generate random rejection reason
        $rejectionReason = generateRejectionReason();

        // Verify initial state
        expect($register->status)->toBe($initialStatus)
            ->and($register->rejected_by_id)->toBeNull()
            ->and($register->rejected_at)->toBeNull();

        // Act: Reject the register
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => $rejectionReason,
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

        expect($register->status)->toBe('REJECTED', 'Status should be REJECTED after rejection')
            ->and($register->rejected_by_id)->toBe($user->id, 'rejected_by_id should match authenticated user')
            ->and($register->rejected_at)->not->toBeNull('rejected_at should be set')
            ->and($register->keterangan)->toBe($rejectionReason, 'keterangan should contain rejection reason');
    }
});

/**
 * **Feature: register-workflow, Property 13: Rejection State Transition (APPROVED rejection blocked)**
 *
 * *For any* Register with status='APPROVED', rejection SHALL be blocked.
 *
 * **Validates: Requirements 6.1, 10.1, 10.2**
 */
test('Property 13: Rejection State Transition - rejecting APPROVED register is blocked', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createRejectUserWithHierarchy(withRejectPermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create an approved register
        $register = createApprovedRegisterForRejection($hierarchy, $user->id, $user->id, $user->id);

        // Generate random rejection reason
        $rejectionReason = generateRejectionReason();

        // Verify initial state
        expect($register->status)->toBe('APPROVED');

        // Act: Attempt to reject the approved register
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => $rejectionReason,
            ]);

        // Assert: Response should be 400 Bad Request
        $response->assertStatus(400);

        // Assert: Register status should remain APPROVED
        $register->refresh();
        expect($register->status)->toBe('APPROVED', 'Status should remain APPROVED')
            ->and($register->rejected_by_id)->toBeNull('rejected_by_id should remain null')
            ->and($register->rejected_at)->toBeNull('rejected_at should remain null');
    }
});

/**
 * **Feature: register-workflow, Property 13: Rejection State Transition (already REJECTED blocked)**
 *
 * *For any* Register with status='REJECTED', re-rejection SHALL be blocked.
 *
 * **Validates: Requirements 6.1, 10.1, 10.2**
 */
test('Property 13: Rejection State Transition - rejecting already REJECTED register is blocked', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createRejectUserWithHierarchy(withRejectPermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create an already rejected register
        $register = createRejectedRegisterForRejection($hierarchy, $user->id, $user->id);
        $originalRejectedById = $register->rejected_by_id;
        $originalRejectedAt = $register->rejected_at;
        $originalKeterangan = $register->keterangan;

        // Generate new rejection reason
        $newRejectionReason = generateRejectionReason();

        // Verify initial state
        expect($register->status)->toBe('REJECTED');

        // Act: Attempt to reject the already rejected register
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => $newRejectionReason,
            ]);

        // Assert: Response should be 400 Bad Request
        $response->assertStatus(400);

        // Assert: Register should remain unchanged
        $register->refresh();
        expect($register->status)->toBe('REJECTED', 'Status should remain REJECTED')
            ->and($register->rejected_by_id)->toBe($originalRejectedById, 'rejected_by_id should remain unchanged')
            ->and($register->keterangan)->toBe($originalKeterangan, 'keterangan should remain unchanged');
    }
});

/**
 * **Feature: register-workflow, Property 14: Rejection Reason Required**
 *
 * *For any* rejection operation without a reason (empty or null alasan),
 * the operation SHALL be rejected with validation error.
 *
 * **Validates: Requirements 6.3**
 */
test('Property 14: Rejection Reason Required - rejection without reason is rejected', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createRejectUserWithHierarchy(withRejectPermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a pending register
        $register = createPendingRegisterForRejection($hierarchy, $user->id);

        // Verify initial state
        expect($register->status)->toBe('PENDING')
            ->and($register->rejected_by_id)->toBeNull()
            ->and($register->rejected_at)->toBeNull();

        // Act: Attempt to reject without providing reason (missing alasan field)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                // alasan is missing
            ]);

        // Assert: Response should be 422 Unprocessable Entity (validation error)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['alasan']);

        // Assert: Register should remain unchanged
        $register->refresh();
        expect($register->status)->toBe('PENDING', 'Status should remain PENDING')
            ->and($register->rejected_by_id)->toBeNull('rejected_by_id should remain null')
            ->and($register->rejected_at)->toBeNull('rejected_at should remain null');
    }
});

/**
 * **Feature: register-workflow, Property 14: Rejection Reason Required (empty string)**
 *
 * *For any* rejection operation with empty string as reason,
 * the operation SHALL be rejected with validation error.
 *
 * **Validates: Requirements 6.3**
 */
test('Property 14: Rejection Reason Required - rejection with empty reason is rejected', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createRejectUserWithHierarchy(withRejectPermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a confirmed register
        $register = createConfirmedRegisterForRejection($hierarchy, $user->id, $user->id);

        // Verify initial state
        expect($register->status)->toBe('CONFIRMED')
            ->and($register->rejected_by_id)->toBeNull()
            ->and($register->rejected_at)->toBeNull();

        // Act: Attempt to reject with empty string as reason
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => '',
            ]);

        // Assert: Response should be 422 Unprocessable Entity (validation error)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['alasan']);

        // Assert: Register should remain unchanged
        $register->refresh();
        expect($register->status)->toBe('CONFIRMED', 'Status should remain CONFIRMED')
            ->and($register->rejected_by_id)->toBeNull('rejected_by_id should remain null')
            ->and($register->rejected_at)->toBeNull('rejected_at should remain null');
    }
});
