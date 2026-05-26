<?php

/**
 * Property-Based Tests for Confirmation Workflow
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the NOO confirmation workflow.
 * Each test runs multiple iterations with randomly generated valid inputs to verify
 * that invariants hold across all valid executions.
 */

use App\Models\Outlet;
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
 * Helper to create authenticated user with organizational hierarchy and confirm permission
 */
function createConfirmUserWithHierarchy(bool $withConfirmPermission = true): array
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

    // Create or get the Confirm:Register permission with 'web' guard to match role
    if ($withConfirmPermission) {
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => 'Confirm:Register', 'guard_name' => 'web'],
            ['name' => 'Confirm:Register', 'guard_name' => 'web']
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
 * Helper to create a pending NOO register (status=PENDING, keterangan=null)
 */
function createPendingNooRegister(array $hierarchy, int $creatorId): Register
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
 * Helper to generate unique outlet code
 */
function generateUniqueOutletCode(): string
{
    return 'OUT-'.strtoupper(fake()->unique()->bothify('??###'));
}

/**
 * **Feature: register-workflow, Property 8: Confirmation State Transition**
 *
 * *For any* Register with status=PENDING (pending NOO), after confirmation with kode_outlet and limit,
 * the Register SHALL have status='CONFIRMED', confirmed_by_id set, confirmed_at set,
 * kode_outlet set, and limit set.
 *
 * **Validates: Requirements 4.1**
 */
test('Property 8: Confirmation State Transition - confirming pending NOO sets CONFIRMED status and audit fields', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user with confirm permission
        $setup = createConfirmUserWithHierarchy(withConfirmPermission: true);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a pending NOO register
        $register = createPendingNooRegister($hierarchy, $user->id);

        // Verify initial state
        expect($register->status)->toBe('PENDING')
            ->and($register->confirmed_by_id)->toBeNull()
            ->and($register->confirmed_at)->toBeNull();

        // Generate random confirmation data
        $kodeOutlet = generateUniqueOutletCode();
        $limit = fake()->numberBetween(1000000, 100000000);

        // Act: Confirm the NOO
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/confirm', [
                'id' => $register->id,
                'status' => 'CONFIRMED',
                'kode_outlet' => $kodeOutlet,
                'limit' => $limit,
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

        expect($register->status)->toBe('CONFIRMED', 'Status should be CONFIRMED after confirmation')
            ->and($register->confirmed_by_id)->toBe($user->id, 'confirmed_by_id should match authenticated user')
            ->and($register->confirmed_at)->not->toBeNull('confirmed_at should be set')
            ->and($register->kode_outlet)->toBe($kodeOutlet, 'kode_outlet should be set')
            ->and($register->limit)->toBe($limit, 'limit should be set');
    }
});

/**
 * **Feature: register-workflow, Property 9: Confirmation Authorization**
 *
 * *For any* user without confirm permission, confirmation operation SHALL be rejected
 * regardless of Register state.
 *
 * **Validates: Requirements 4.3**
 */
test('Property 9: Confirmation Authorization - user without confirm permission cannot confirm', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create user WITHOUT confirm permission
        $setup = createConfirmUserWithHierarchy(withConfirmPermission: false);
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a pending NOO register
        $register = createPendingNooRegister($hierarchy, $user->id);

        $originalStatus = $register->status;
        $originalConfirmedById = $register->confirmed_by_id;
        $originalConfirmedAt = $register->confirmed_at;

        // Generate random confirmation data
        $kodeOutlet = generateUniqueOutletCode();
        $limit = fake()->numberBetween(1000000, 100000000);

        // Act: Try to confirm the NOO without permission
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/confirm', [
                'id' => $register->id,
                'status' => 'CONFIRMED',
                'kode_outlet' => $kodeOutlet,
                'limit' => $limit,
            ]);

        // Assert: Request is rejected with 403 Forbidden
        $response->assertStatus(403);

        // Assert: Register was not modified
        $register->refresh();
        expect($register->status)->toBe($originalStatus, 'Status should not change')
            ->and($register->confirmed_by_id)->toBe($originalConfirmedById, 'confirmed_by_id should not change')
            ->and($register->confirmed_at)->toBe($originalConfirmedAt, 'confirmed_at should not change')
            ->and($register->kode_outlet)->toBeNull('kode_outlet should not be set')
            ->and($register->limit)->toBeNull('limit should not be set');
    }
});

test('confirmation allows an existing outlet code so approval can resolve it later', function () {
    $setup = createConfirmUserWithHierarchy(withConfirmPermission: true);
    $user = $setup['user'];
    $hierarchy = $setup['hierarchy'];

    Outlet::factory()->create([
        'kode_outlet' => 'COMPLETE',
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
    ]);

    $register = createPendingNooRegister($hierarchy, $user->id);

    $this->actingAs($user, 'sanctum')
        ->patchJson('/api/registers/'.$register->id.'/confirm', [
            'id' => $register->id,
            'status' => 'CONFIRMED',
            'kode_outlet' => 'COMPLETE',
            'limit' => 1000000,
        ])
        ->assertOk();

    expect($register->refresh()->status)->toBe('CONFIRMED')
        ->and($register->kode_outlet)->toBe('COMPLETE');
});
