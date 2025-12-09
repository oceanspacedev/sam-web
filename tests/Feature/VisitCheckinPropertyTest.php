<?php

/**
 * Property-Based Tests for Visit Checkin Workflow
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the Visit checkin workflow.
 * Each test runs multiple iterations with randomly generated valid inputs to verify
 * that invariants hold across all valid executions.
 */

use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\UploadedFile;
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
 * Helper to create authenticated user with organizational hierarchy
 */
function createUserWithHierarchy(): array
{
    $suffix = uniqid();

    $bu = \App\Models\BadanUsaha::create(['name' => 'BU-'.$suffix]);
    $div = \App\Models\Division::create(['name' => 'DIV-'.$suffix, 'badanusaha_id' => $bu->id]);
    $reg = \App\Models\Region::create(['name' => 'REG-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id]);
    $clus = \App\Models\Cluster::create(['name' => 'CLUS-'.$suffix, 'badanusaha_id' => $bu->id, 'divisi_id' => $div->id, 'region_id' => $reg->id]);

    $role = \App\Models\Role::create([
        'name' => 'DM-'.$suffix,
        'can_access_web' => true,
        'organizational_scope_level' => 'cluster',
    ]);

    $user = User::factory()->create([
        'role_id' => $role->id,
    ]);

    // Attach organizational hierarchy to user
    $user->badanUsahas()->attach($bu->id);
    $user->divisis()->attach($div->id);
    $user->regions()->attach($reg->id);
    $user->clusters()->attach($clus->id);

    return ['user' => $user, 'hierarchy' => ['bu' => $bu, 'div' => $div, 'reg' => $reg, 'clus' => $clus, 'role' => $role]];
}

/**
 * Helper to create an outlet within the user's organizational scope
 */
function createOutletInScope(array $hierarchy): Outlet
{
    return Outlet::create([
        'kode_outlet' => 'OUT-'.fake()->unique()->numerify('######'),
        'nama_outlet' => fake()->company().' '.fake()->randomNumber(3),
        'alamat_outlet' => fake()->address(),
        'nama_pemilik_outlet' => fake()->name(),
        'nomer_tlp_outlet' => '08'.fake()->numerify('##########'),
        'distric' => 'D'.fake()->numerify('##'),
        'latlong' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
        'badanusaha_id' => $hierarchy['bu']->id,
        'divisi_id' => $hierarchy['div']->id,
        'region_id' => $hierarchy['reg']->id,
        'cluster_id' => $hierarchy['clus']->id,
        'status_outlet' => 'MAINTAIN',
    ]);
}

/**
 * Helper to generate valid checkin data
 */
function generateValidCheckinData(int $outletId): array
{
    return [
        'outlet_id' => $outletId,
        'latlong_in' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
        'tipe_visit' => fake()->randomElement(['PLANNED', 'EXTRACALL']),
        'picture_visit' => UploadedFile::fake()->image('checkin.jpg', 800, 600),
    ];
}

/**
 * **Feature: register-workflow, Property 17: Checkin Creates Visit**
 *
 * *For any* valid checkin with outlet_id, latlong_in, picture_visit, and tipe_visit,
 * a Visit record SHALL be created with user_id matching authenticated user,
 * checkin_at set, and all provided fields stored.
 *
 * **Validates: Requirements 8.1, 8.3**
 */
test('Property 17: Checkin Creates Visit - valid checkin creates visit with correct data', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create fresh user and hierarchy for each iteration
        $setup = createUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create an outlet within user's scope
        $outlet = createOutletInScope($hierarchy);

        $checkinData = generateValidCheckinData($outlet->id);

        // Act: Perform checkin
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/checkin', $checkinData);

        // Assert: Response is successful
        $response->assertStatus(200);
        $response->assertJson([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Check-in berhasil',
            ],
        ]);

        // Assert: Visit was created with correct invariants
        $visit = Visit::where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->whereDate('tanggal_visit', today())
            ->first();

        expect($visit)->not->toBeNull('Visit should be created')
            ->and($visit->user_id)->toBe($user->id, 'user_id should match authenticated user')
            ->and($visit->outlet_id)->toBe($outlet->id, 'outlet_id should match')
            ->and($visit->latlong_in)->toBe($checkinData['latlong_in'], 'latlong_in should be stored')
            ->and($visit->tipe_visit)->toBe($checkinData['tipe_visit'], 'tipe_visit should be stored')
            ->and($visit->check_in_time)->not->toBeNull('check_in_time should be set')
            ->and($visit->picture_visit_in)->not->toBeNull('picture_visit_in should be stored')
            ->and($visit->check_out_time)->toBeNull('check_out_time should be null initially');
    }
});

/**
 * **Feature: register-workflow, Property 18: Checkin Outlet Validation**
 *
 * *For any* checkin attempt at a non-existent outlet_id or outlet outside user's scope,
 * the operation SHALL be rejected.
 *
 * **Validates: Requirements 8.2**
 */
test('Property 18: Checkin Outlet Validation - checkin at non-existent outlet is rejected', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 100; $i++) {
        $setup = createUserWithHierarchy();
        $user = $setup['user'];

        // Use a non-existent outlet ID
        $nonExistentOutletId = 999999 + $i;

        $checkinData = [
            'outlet_id' => $nonExistentOutletId,
            'latlong_in' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
            'tipe_visit' => fake()->randomElement(['PLANNED', 'EXTRACALL']),
            'picture_visit' => UploadedFile::fake()->image('checkin.jpg', 800, 600),
        ];

        $countBefore = Visit::count();

        // Act: Try to checkin at non-existent outlet
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/checkin', $checkinData);

        // Assert: Request is rejected with validation error (422 for non-existent outlet)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['outlet_id']);

        // Assert: No visit was created
        expect(Visit::count())->toBe($countBefore, 'No visit should be created for non-existent outlet');
    }
});

test('Property 18b: Checkin Outlet Validation - checkin at outlet outside scope is rejected', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 100; $i++) {
        $setup = createUserWithHierarchy();
        $user = $setup['user'];

        // Create a different organizational hierarchy (outside user's scope)
        $otherSuffix = uniqid('other');
        $otherBu = \App\Models\BadanUsaha::create(['name' => 'OTHER-BU-'.$otherSuffix]);
        $otherDiv = \App\Models\Division::create(['name' => 'OTHER-DIV-'.$otherSuffix, 'badanusaha_id' => $otherBu->id]);
        $otherReg = \App\Models\Region::create(['name' => 'OTHER-REG-'.$otherSuffix, 'badanusaha_id' => $otherBu->id, 'divisi_id' => $otherDiv->id]);
        $otherClus = \App\Models\Cluster::create(['name' => 'OTHER-CLUS-'.$otherSuffix, 'badanusaha_id' => $otherBu->id, 'divisi_id' => $otherDiv->id, 'region_id' => $otherReg->id]);

        // Create outlet in different scope
        $outletOutsideScope = Outlet::create([
            'kode_outlet' => 'OUT-OTHER-'.fake()->unique()->numerify('######'),
            'nama_outlet' => fake()->company(),
            'alamat_outlet' => fake()->address(),
            'nama_pemilik_outlet' => fake()->name(),
            'nomer_tlp_outlet' => '08'.fake()->numerify('##########'),
            'distric' => 'D01',
            'latlong' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
            'badanusaha_id' => $otherBu->id,
            'divisi_id' => $otherDiv->id,
            'region_id' => $otherReg->id,
            'cluster_id' => $otherClus->id,
            'status_outlet' => 'MAINTAIN',
        ]);

        $checkinData = generateValidCheckinData($outletOutsideScope->id);

        $countBefore = Visit::count();

        // Act: Try to checkin at outlet outside scope
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/checkin', $checkinData);

        // Assert: Request is rejected (404 - outlet not found in user's scope)
        $response->assertStatus(404);

        // Assert: No visit was created
        expect(Visit::count())->toBe($countBefore, 'No visit should be created for outlet outside scope');
    }
});

/**
 * **Feature: register-workflow, Property 19: Concurrent Visit Prevention**
 *
 * *For any* user with an existing Visit where checkout_at is null,
 * new checkin attempts SHALL be rejected.
 *
 * **Validates: Requirements 8.4**
 */
test('Property 19: Concurrent Visit Prevention - cannot checkin with active visit', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 100; $i++) {
        $setup = createUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create two outlets within user's scope
        $outlet1 = createOutletInScope($hierarchy);
        $outlet2 = createOutletInScope($hierarchy);

        // First checkin - should succeed
        $checkinData1 = generateValidCheckinData($outlet1->id);
        $response1 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/checkin', $checkinData1);
        $response1->assertStatus(200);

        // Verify active visit exists
        $activeVisit = Visit::where('user_id', $user->id)
            ->whereDate('tanggal_visit', today())
            ->whereNull('check_out_time')
            ->first();
        expect($activeVisit)->not->toBeNull('Active visit should exist');

        $countBefore = Visit::count();

        // Second checkin attempt - should fail
        $checkinData2 = generateValidCheckinData($outlet2->id);
        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/checkin', $checkinData2);

        // Assert: Request is rejected
        $response2->assertStatus(400);
        $response2->assertJson([
            'meta' => [
                'status' => 'error',
            ],
        ]);

        // Assert: No new visit was created
        expect(Visit::count())->toBe($countBefore, 'No new visit should be created when active visit exists');
    }
});

test('Property 19b: Concurrent Visit Prevention - cannot checkin at same outlet twice in same day', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 100; $i++) {
        $setup = createUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create an outlet within user's scope
        $outlet = createOutletInScope($hierarchy);

        // First checkin - should succeed
        $checkinData1 = generateValidCheckinData($outlet->id);
        $response1 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/checkin', $checkinData1);
        $response1->assertStatus(200);

        // Get the visit and complete checkout
        $visit = Visit::where('user_id', $user->id)
            ->where('outlet_id', $outlet->id)
            ->whereDate('tanggal_visit', today())
            ->first();

        // Checkout from first visit
        $checkoutData = [
            'latlong_out' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
            'laporan_visit' => fake()->sentence(10),
            'picture_visit' => UploadedFile::fake()->image('checkout.jpg', 800, 600),
            'transaksi' => fake()->randomElement(['YES', 'NO']),
        ];
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/'.$visit->id.'/checkout', $checkoutData)
            ->assertStatus(200);

        $countBefore = Visit::count();

        // Second checkin attempt at same outlet - should fail
        $checkinData2 = generateValidCheckinData($outlet->id);
        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/checkin', $checkinData2);

        // Assert: Request is rejected (duplicate visit to same outlet on same day)
        $response2->assertStatus(400);

        // Assert: No new visit was created
        expect(Visit::count())->toBe($countBefore, 'No duplicate visit should be created for same outlet on same day');
    }
});
