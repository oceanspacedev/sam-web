<?php

/**
 * Property-Based Tests for Register Listing
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the Register listing and filtering functionality.
 * Each test runs multiple iterations with randomly generated valid inputs to verify
 * that invariants hold across all valid executions.
 */

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Register;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsSystemSettings;

uses(SeedsSystemSettings::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');

    // Disable rate limiting middleware that requires Redis
    $this->withoutMiddleware(\App\Http\Middleware\RateLimitUploads::class);

    // Create global system setting to avoid null references
    $this->seedGlobalSystemSetting();
});

/**
 * Helper to create organizational hierarchy
 */
function createOrganizationalHierarchy(string $suffix = ''): array
{
    $suffix = $suffix ?: uniqid();

    $bu = BadanUsaha::create(['name' => 'BU-'.$suffix]);
    $div = Division::create(['name' => 'DIV-'.$suffix, 'badanusaha_id' => $bu->id]);
    $reg = Region::create(['name' => 'REG-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = Cluster::create(['name' => 'CLUS-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

    return ['bu' => $bu, 'div' => $div, 'reg' => $reg, 'clus' => $clus];
}

/**
 * Helper to create a user with specific organizational scope
 */
function createUserWithScope(array $hierarchy, string $scopeLevel): User
{
    $suffix = uniqid();

    $role = Role::create([
        'name' => 'Role-'.$suffix,
        'can_access_web' => true,
        'organizational_scope_level' => $scopeLevel,
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    // Attach organizational hierarchy to user
    $user->badanUsahas()->attach($hierarchy['bu']->id);
    $user->divisis()->attach($hierarchy['div']->id);
    $user->regions()->attach($hierarchy['reg']->id);
    $user->clusters()->attach($hierarchy['clus']->id);

    // Refresh user to clear any cached data and reload relationships
    return $user->fresh();
}

/**
 * Helper to create a register in a specific hierarchy
 */
function createRegisterInHierarchy(array $hierarchy, User $creator, array $overrides = []): Register
{
    return Register::create(array_merge([
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
        'created_by_id' => $creator->id,
        'tm_id' => $creator->id,
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status' => 'PENDING',
        'type' => 'NOO',
        'keterangan' => null,
    ], $overrides));
}

/**
 * **Feature: register-workflow, Property 15: Created/TM Scope Filtering**
 *
 * *For any* user fetching registers, the returned set SHALL contain only registers
 * where the user is either creator (`created_by_id`) or assigned TM (`tm_id`).
 *
 * **Validates: Requirements 7.1**
 */
test('Property 15: Created/TM Scope Filtering - users only see registers where they are creator or TM', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 100; $i++) {
        // Create two separate organizational hierarchies
        $hierarchy1 = createOrganizationalHierarchy('h1-'.$i);
        $hierarchy2 = createOrganizationalHierarchy('h2-'.$i);

        // Create users in different hierarchies
        $user1 = createUserWithScope($hierarchy1, 'cluster');
        $user2 = createUserWithScope($hierarchy2, 'cluster');

        // Create registers for different ownership/TM scenarios
        $createdByUserInOwnHierarchy = createRegisterInHierarchy($hierarchy1, $user1);
        $createdByUserInOtherHierarchy = createRegisterInHierarchy($hierarchy2, $user1);
        $assignedToUserAsTm = createRegisterInHierarchy($hierarchy2, $user2, [
            'tm_id' => $user1->id,
        ]);
        $unrelatedRegister = createRegisterInHierarchy($hierarchy2, $user2);

        // Act: Fetch registers as user1
        $response = $this->actingAs($user1, 'sanctum')
            ->getJson('/api/registers/all');

        $response->assertStatus(200);

        // Assert: User1 sees registers where they are creator or TM
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($createdByUserInOwnHierarchy->id, $returnedIds, 'User should see register they created');
        $this->assertContains($createdByUserInOtherHierarchy->id, $returnedIds, 'User should still see register they created in different hierarchy');
        $this->assertContains($assignedToUserAsTm->id, $returnedIds, 'User should see register where they are assigned TM');
        $this->assertNotContains($unrelatedRegister->id, $returnedIds, 'User should NOT see register where they are neither creator nor TM');

        // Verify all returned registers satisfy created_by_id/tm_id visibility
        foreach ($response->json('data') as $registerData) {
            $register = Register::find($registerData['id']);
            $this->assertTrue(
                $register->created_by_id === $user1->id || $register->tm_id === $user1->id,
                "Register {$register->id} should be visible to user by created_by_id/tm_id"
            );
        }
    }
});

/**
 * **Feature: register-workflow, Property 16: Pending Filter Correctness**
 *
 * *For any* fetch of pending registers, all returned registers SHALL have approved_by_id=null.
 *
 * **Validates: Requirements 7.3**
 */
test('Property 16: Pending Filter Correctness - pending endpoint returns only unapproved registers', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 100; $i++) {
        // Create organizational hierarchy
        $hierarchy = createOrganizationalHierarchy('pending-'.$i);

        // Create a user with cluster-level scope
        $user = createUserWithScope($hierarchy, 'cluster');

        // Create registers with different approval states
        // 1. Pending register (approved_by_id = null)
        $pendingRegister = createRegisterInHierarchy($hierarchy, $user, [
            'status' => 'PENDING',
            'approved_by_id' => null,
            'approved_at' => null,
        ]);

        // 2. Confirmed register (approved_by_id = null)
        $confirmedRegister = createRegisterInHierarchy($hierarchy, $user, [
            'status' => 'CONFIRMED',
            'confirmed_by_id' => $user->id,
            'confirmed_at' => now(),
            'kode_outlet' => 'OUT-'.fake()->randomNumber(5),
            'limit' => fake()->numberBetween(1000000, 10000000),
            'approved_by_id' => null,
            'approved_at' => null,
        ]);

        // 3. Approved register (approved_by_id != null)
        $approvedRegister = createRegisterInHierarchy($hierarchy, $user, [
            'status' => 'APPROVED',
            'confirmed_by_id' => $user->id,
            'confirmed_at' => now()->subDay(),
            'kode_outlet' => 'OUT-'.fake()->randomNumber(5),
            'limit' => fake()->numberBetween(1000000, 10000000),
            'approved_by_id' => $user->id,
            'approved_at' => now(),
        ]);

        // 4. Rejected register (approved_by_id = null but rejected)
        $rejectedRegister = createRegisterInHierarchy($hierarchy, $user, [
            'status' => 'REJECTED',
            'rejected_by_id' => $user->id,
            'rejected_at' => now(),
            'keterangan' => 'Test rejection reason',
            'approved_by_id' => null,
            'approved_at' => null,
        ]);

        // Act: Fetch pending registers
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/registers/pending');

        $response->assertStatus(200);

        // Assert: All returned registers have approved_by_id = null
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();

        foreach ($response->json('data') as $registerData) {
            $register = Register::find($registerData['id']);
            $this->assertNull(
                $register->approved_by_id,
                "Pending register {$register->id} should have approved_by_id = null"
            );
        }

        // Assert: Pending and confirmed registers should be in results (they have approved_by_id = null)
        $this->assertContains($pendingRegister->id, $returnedIds, 'Pending register should be in pending list');
        $this->assertContains($confirmedRegister->id, $returnedIds, 'Confirmed register should be in pending list');

        // Assert: Approved register should NOT be in results
        $this->assertNotContains($approvedRegister->id, $returnedIds, 'Approved register should NOT be in pending list');

        // Note: Rejected registers with approved_by_id = null will be included in pending list
        // This is the current behavior based on the whereNull('approved_by_id') filter
        $this->assertContains($rejectedRegister->id, $returnedIds, 'Rejected register (with approved_by_id=null) is in pending list');
    }
});
