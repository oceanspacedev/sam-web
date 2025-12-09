<?php

/**
 * Property-Based Tests for State Machine and Audit Trail
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the Register state machine enforcement
 * and audit trail completeness. Each test runs multiple iterations with randomly
 * generated valid inputs to verify that invariants hold across all valid executions.
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
 * Helper to create authenticated user with organizational hierarchy and all permissions
 */
function createStateMachineUserWithHierarchy(array $permissions = []): array
{
    $suffix = uniqid();

    $bu = \App\Models\BadanUsaha::create(['name' => 'BU-'.$suffix]);
    $div = \App\Models\Division::create(['name' => 'DIV-'.$suffix, 'badanusaha_id' => $bu->id]);
    $reg = \App\Models\Region::create(['name' => 'REG-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = \App\Models\Cluster::create(['name' => 'CLUS-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

    // Create role with 'web' guard (default for Spatie Permission)
    $role = \App\Models\Role::create([
        'name' => 'Admin-'.$suffix,
        'guard_name' => 'web',
        'can_access_web' => true,
        'organizational_scope_level' => 'all',
    ]);

    // Create and assign permissions
    foreach ($permissions as $permName) {
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => $permName, 'guard_name' => 'web'],
            ['name' => $permName, 'guard_name' => 'web']
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
 * Helper to create a LEAD register (keterangan='LEAD', status='PENDING')
 */
function createLeadRegisterForStateMachine(array $hierarchy, int $creatorId): Register
{
    return Register::create([
        'nama_outlet' => fake()->company().' '.fake()->randomNumber(3),
        'alamat_outlet' => fake()->address(),
        'nama_pemilik_outlet' => fake()->name(),
        'nomer_tlp_outlet' => '08'.fake()->numerify('##########'),
        'ktp_outlet' => '-',
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
        'poto_ktp' => '-',
        'created_by_id' => $creatorId,
        'tm_id' => $creatorId,
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status' => 'PENDING',
        'keterangan' => 'LEAD',
    ]);
}

/**
 * Helper to create a pending NOO register (status='PENDING', keterangan=null)
 */
function createPendingNooForStateMachine(array $hierarchy, int $creatorId): Register
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
 * Helper to create a confirmed register (status='CONFIRMED')
 */
function createConfirmedForStateMachine(array $hierarchy, int $creatorId, int $confirmerId): Register
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
 * Helper to create an approved register (status='APPROVED')
 */
function createApprovedForStateMachine(array $hierarchy, int $creatorId, int $confirmerId, int $approverId): Register
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
 * Helper to create a rejected register (status='REJECTED')
 */
function createRejectedForStateMachine(array $hierarchy, int $creatorId, int $rejecterId): Register
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
 * **Feature: register-workflow, Property 23: State Machine Enforcement**
 *
 * *For any* status transition attempt, only the following transitions SHALL be allowed:
 * (PENDING/LEAD → CONFIRMED), (PENDING/LEAD → REJECTED), (CONFIRMED → APPROVED), (CONFIRMED → REJECTED).
 * All other transitions SHALL be rejected.
 *
 * **Validates: Requirements 10.1, 10.2**
 */

/**
 * Test valid transition: PENDING → CONFIRMED
 */
test('Property 23: State Machine Enforcement - PENDING to CONFIRMED is allowed', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with confirm permission
        $setup = createStateMachineUserWithHierarchy(['Confirm:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a pending NOO register
        $register = createPendingNooForStateMachine($hierarchy, $user->id);

        // Verify initial state
        expect($register->status)->toBe('PENDING');

        // Act: Confirm the NOO (PENDING → CONFIRMED)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/confirm', [
                'id' => $register->id,
                'status' => 'CONFIRMED',
                'kode_outlet' => 'OUT-'.strtoupper(fake()->unique()->bothify('??###')),
                'limit' => fake()->numberBetween(1000000, 100000000),
            ]);

        // Assert: Transition is allowed
        $response->assertStatus(200);

        $register->refresh();
        expect($register->status)->toBe('CONFIRMED', 'PENDING → CONFIRMED transition should be allowed');
    }
});

/**
 * Test valid transition: PENDING → REJECTED
 */
test('Property 23: State Machine Enforcement - PENDING to REJECTED is allowed', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createStateMachineUserWithHierarchy(['Reject:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a pending NOO register
        $register = createPendingNooForStateMachine($hierarchy, $user->id);

        // Verify initial state
        expect($register->status)->toBe('PENDING');

        // Act: Reject the NOO (PENDING → REJECTED)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => 'Test rejection reason '.fake()->sentence(),
            ]);

        // Assert: Transition is allowed
        $response->assertStatus(200);

        $register->refresh();
        expect($register->status)->toBe('REJECTED', 'PENDING → REJECTED transition should be allowed');
    }
});

/**
 * Test valid transition: CONFIRMED → APPROVED
 */
test('Property 23: State Machine Enforcement - CONFIRMED to APPROVED is allowed', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with approve permission
        $setup = createStateMachineUserWithHierarchy(['Approve:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a confirmed register
        $register = createConfirmedForStateMachine($hierarchy, $user->id, $user->id);

        // Verify initial state
        expect($register->status)->toBe('CONFIRMED');

        // Act: Approve the NOO (CONFIRMED → APPROVED)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);

        // Assert: Transition is allowed
        $response->assertStatus(200);

        $register->refresh();
        expect($register->status)->toBe('APPROVED', 'CONFIRMED → APPROVED transition should be allowed');
    }
});

/**
 * Test valid transition: CONFIRMED → REJECTED
 */
test('Property 23: State Machine Enforcement - CONFIRMED to REJECTED is allowed', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createStateMachineUserWithHierarchy(['Reject:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a confirmed register
        $register = createConfirmedForStateMachine($hierarchy, $user->id, $user->id);

        // Verify initial state
        expect($register->status)->toBe('CONFIRMED');

        // Act: Reject the NOO (CONFIRMED → REJECTED)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => 'Test rejection reason '.fake()->sentence(),
            ]);

        // Assert: Transition is allowed
        $response->assertStatus(200);

        $register->refresh();
        expect($register->status)->toBe('REJECTED', 'CONFIRMED → REJECTED transition should be allowed');
    }
});

/**
 * Test invalid transition: APPROVED → CONFIRMED (blocked)
 */
test('Property 23: State Machine Enforcement - APPROVED to CONFIRMED is blocked', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with confirm permission
        $setup = createStateMachineUserWithHierarchy(['Confirm:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create an approved register
        $register = createApprovedForStateMachine($hierarchy, $user->id, $user->id, $user->id);

        // Verify initial state
        expect($register->status)->toBe('APPROVED');

        // Act: Try to confirm an already approved register (invalid transition)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/confirm', [
                'id' => $register->id,
                'status' => 'CONFIRMED',
                'kode_outlet' => 'OUT-'.strtoupper(fake()->unique()->bothify('??###')),
                'limit' => fake()->numberBetween(1000000, 100000000),
            ]);

        // Assert: Transition is blocked
        $response->assertStatus(400);

        $register->refresh();
        expect($register->status)->toBe('APPROVED', 'APPROVED → CONFIRMED transition should be blocked');
    }
});

/**
 * Test invalid transition: APPROVED → REJECTED (blocked)
 */
test('Property 23: State Machine Enforcement - APPROVED to REJECTED is blocked', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createStateMachineUserWithHierarchy(['Reject:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create an approved register
        $register = createApprovedForStateMachine($hierarchy, $user->id, $user->id, $user->id);

        // Verify initial state
        expect($register->status)->toBe('APPROVED');

        // Act: Try to reject an already approved register (invalid transition)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => 'Test rejection reason '.fake()->sentence(),
            ]);

        // Assert: Transition is blocked
        $response->assertStatus(400);

        $register->refresh();
        expect($register->status)->toBe('APPROVED', 'APPROVED → REJECTED transition should be blocked');
    }
});

/**
 * Test invalid transition: REJECTED → CONFIRMED (blocked)
 */
test('Property 23: State Machine Enforcement - REJECTED to CONFIRMED is blocked', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with confirm permission
        $setup = createStateMachineUserWithHierarchy(['Confirm:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a rejected register
        $register = createRejectedForStateMachine($hierarchy, $user->id, $user->id);

        // Verify initial state
        expect($register->status)->toBe('REJECTED');

        // Act: Try to confirm a rejected register (invalid transition)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/confirm', [
                'id' => $register->id,
                'status' => 'CONFIRMED',
                'kode_outlet' => 'OUT-'.strtoupper(fake()->unique()->bothify('??###')),
                'limit' => fake()->numberBetween(1000000, 100000000),
            ]);

        // Assert: Transition is blocked
        $response->assertStatus(400);

        $register->refresh();
        expect($register->status)->toBe('REJECTED', 'REJECTED → CONFIRMED transition should be blocked');
    }
});

/**
 * Test invalid transition: REJECTED → APPROVED (blocked)
 */
test('Property 23: State Machine Enforcement - REJECTED to APPROVED is blocked', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with approve permission
        $setup = createStateMachineUserWithHierarchy(['Approve:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a rejected register
        $register = createRejectedForStateMachine($hierarchy, $user->id, $user->id);

        // Verify initial state
        expect($register->status)->toBe('REJECTED');

        // Act: Try to approve a rejected register (invalid transition)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);

        // Assert: Transition is blocked
        $response->assertStatus(400);

        $register->refresh();
        expect($register->status)->toBe('REJECTED', 'REJECTED → APPROVED transition should be blocked');
    }
});

/**
 * Test invalid transition: REJECTED → REJECTED (blocked - double rejection)
 */
test('Property 23: State Machine Enforcement - REJECTED to REJECTED is blocked', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createStateMachineUserWithHierarchy(['Reject:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a rejected register
        $register = createRejectedForStateMachine($hierarchy, $user->id, $user->id);
        $originalKeterangan = $register->keterangan;

        // Verify initial state
        expect($register->status)->toBe('REJECTED');

        // Act: Try to reject an already rejected register (invalid transition)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => 'New rejection reason '.fake()->sentence(),
            ]);

        // Assert: Transition is blocked
        $response->assertStatus(400);

        $register->refresh();
        expect($register->status)->toBe('REJECTED', 'REJECTED → REJECTED transition should be blocked')
            ->and($register->keterangan)->toBe($originalKeterangan, 'Rejection reason should not change');
    }
});

/**
 * Test invalid transition: PENDING → APPROVED (blocked - must go through CONFIRMED first)
 */
test('Property 23: State Machine Enforcement - PENDING to APPROVED is blocked', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with approve permission
        $setup = createStateMachineUserWithHierarchy(['Approve:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a pending NOO register
        $register = createPendingNooForStateMachine($hierarchy, $user->id);

        // Verify initial state
        expect($register->status)->toBe('PENDING');

        // Act: Try to approve a pending register directly (invalid transition)
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);

        // Assert: Transition is blocked (must go through CONFIRMED first)
        $response->assertStatus(400);

        $register->refresh();
        expect($register->status)->toBe('PENDING', 'PENDING → APPROVED transition should be blocked');
    }
});

/**
 * **Feature: register-workflow, Property 24: Audit Trail Completeness**
 *
 * *For any* status transition, the corresponding audit fields SHALL be set:
 * confirmation sets (confirmed_by_id, confirmed_at),
 * approval sets (approved_by_id, approved_at),
 * rejection sets (rejected_by_id, rejected_at).
 *
 * **Validates: Requirements 10.3**
 */

/**
 * Test audit trail: Confirmation sets confirmed_by_id and confirmed_at
 */
test('Property 24: Audit Trail Completeness - confirmation sets audit fields', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with confirm permission
        $setup = createStateMachineUserWithHierarchy(['Confirm:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a pending NOO register
        $register = createPendingNooForStateMachine($hierarchy, $user->id);

        // Verify initial audit fields are null
        expect($register->confirmed_by_id)->toBeNull()
            ->and($register->confirmed_at)->toBeNull();

        // Act: Confirm the NOO
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/confirm', [
                'id' => $register->id,
                'status' => 'CONFIRMED',
                'kode_outlet' => 'OUT-'.strtoupper(fake()->unique()->bothify('??###')),
                'limit' => fake()->numberBetween(1000000, 100000000),
            ]);

        // Assert: Response is successful
        $response->assertStatus(200);

        // Assert: Audit fields are set correctly
        $register->refresh();

        expect($register->confirmed_by_id)->toBe($user->id, 'confirmed_by_id should match authenticated user')
            ->and($register->confirmed_at)->not->toBeNull('confirmed_at should be set');

        // Verify timestamp is a valid datetime string
        $confirmedAt = \Carbon\Carbon::parse($register->confirmed_at);
        expect($confirmedAt)->toBeInstanceOf(\Carbon\Carbon::class, 'confirmed_at should be a valid timestamp');
    }
});

/**
 * Test audit trail: Approval sets approved_by_id and approved_at
 */
test('Property 24: Audit Trail Completeness - approval sets audit fields', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with approve permission
        $setup = createStateMachineUserWithHierarchy(['Approve:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a confirmed register
        $register = createConfirmedForStateMachine($hierarchy, $user->id, $user->id);

        // Verify initial approval audit fields are null
        expect($register->approved_by_id)->toBeNull()
            ->and($register->approved_at)->toBeNull();

        // Act: Approve the NOO
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);

        // Assert: Response is successful
        $response->assertStatus(200);

        // Assert: Audit fields are set correctly
        $register->refresh();

        expect($register->approved_by_id)->toBe($user->id, 'approved_by_id should match authenticated user')
            ->and($register->approved_at)->not->toBeNull('approved_at should be set');

        // Verify timestamp is a valid datetime string
        $approvedAt = \Carbon\Carbon::parse($register->approved_at);
        expect($approvedAt)->toBeInstanceOf(\Carbon\Carbon::class, 'approved_at should be a valid timestamp');
    }
});

/**
 * Test audit trail: Rejection sets rejected_by_id and rejected_at
 */
test('Property 24: Audit Trail Completeness - rejection sets audit fields', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with reject permission
        $setup = createStateMachineUserWithHierarchy(['Reject:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Randomly choose between PENDING and CONFIRMED status
        $initialStatus = fake()->randomElement(['PENDING', 'CONFIRMED']);

        if ($initialStatus === 'PENDING') {
            $register = createPendingNooForStateMachine($hierarchy, $user->id);
        } else {
            $register = createConfirmedForStateMachine($hierarchy, $user->id, $user->id);
        }

        // Verify initial rejection audit fields are null
        expect($register->rejected_by_id)->toBeNull()
            ->and($register->rejected_at)->toBeNull();

        $rejectionReason = 'Test rejection reason '.fake()->sentence();

        // Act: Reject the register
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/reject', [
                'id' => $register->id,
                'status' => 'REJECTED',
                'alasan' => $rejectionReason,
            ]);

        // Assert: Response is successful
        $response->assertStatus(200);

        // Assert: Audit fields are set correctly
        $register->refresh();

        expect($register->rejected_by_id)->toBe($user->id, 'rejected_by_id should match authenticated user')
            ->and($register->rejected_at)->not->toBeNull('rejected_at should be set')
            ->and($register->keterangan)->toBe($rejectionReason, 'keterangan should contain rejection reason');

        // Verify timestamp is a valid datetime string
        $rejectedAt = \Carbon\Carbon::parse($register->rejected_at);
        expect($rejectedAt)->toBeInstanceOf(\Carbon\Carbon::class, 'rejected_at should be a valid timestamp');
    }
});

/**
 * Test audit trail: Full workflow preserves all audit fields
 */
test('Property 24: Audit Trail Completeness - full workflow preserves all audit fields', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with all permissions
        $setup = createStateMachineUserWithHierarchy(['Confirm:Register', 'Approve:Register']);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a pending NOO register
        $register = createPendingNooForStateMachine($hierarchy, $user->id);

        // Step 1: Confirm
        $kodeOutlet = 'OUT-'.strtoupper(fake()->unique()->bothify('??###'));
        $limit = fake()->numberBetween(1000000, 100000000);

        $response1 = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/confirm', [
                'id' => $register->id,
                'status' => 'CONFIRMED',
                'kode_outlet' => $kodeOutlet,
                'limit' => $limit,
            ]);
        $response1->assertStatus(200);

        $register->refresh();
        $confirmedById = $register->confirmed_by_id;
        $confirmedAt = $register->confirmed_at;

        // Verify confirmation audit fields
        expect($confirmedById)->toBe($user->id)
            ->and($confirmedAt)->not->toBeNull();

        // Step 2: Approve
        $response2 = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/approve', [
                'id' => $register->id,
                'status' => 'APPROVED',
            ]);
        $response2->assertStatus(200);

        // Assert: All audit fields are preserved and set correctly
        $register->refresh();

        // Confirmation audit fields should be preserved
        expect($register->confirmed_by_id)->toBe($confirmedById, 'confirmed_by_id should be preserved')
            ->and($register->confirmed_at)->toBe($confirmedAt, 'confirmed_at should be preserved');

        // Approval audit fields should be set
        expect($register->approved_by_id)->toBe($user->id, 'approved_by_id should match authenticated user')
            ->and($register->approved_at)->not->toBeNull('approved_at should be set');

        // Verify timestamp is a valid datetime string
        $approvedAt = \Carbon\Carbon::parse($register->approved_at);
        expect($approvedAt)->toBeInstanceOf(\Carbon\Carbon::class, 'approved_at should be a valid timestamp');

        // Rejection audit fields should remain null (not rejected)
        expect($register->rejected_by_id)->toBeNull('rejected_by_id should remain null')
            ->and($register->rejected_at)->toBeNull('rejected_at should remain null');
    }
});
