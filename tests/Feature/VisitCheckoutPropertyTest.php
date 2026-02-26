<?php

/**
 * Property-Based Tests for Visit Checkout Workflow
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the Visit checkout workflow.
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
function createCheckoutUserWithHierarchy(): array
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
function createCheckoutOutletInScope(array $hierarchy): Outlet
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
 * Helper to create a visit with checkin (active visit ready for checkout)
 */
function createActiveVisit(User $user, Outlet $outlet): Visit
{
    return Visit::create([
        'tanggal_visit' => today(),
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tipe_visit' => fake()->randomElement(['PLANNED', 'EXTRACALL']),
        'latlong_in' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
        'check_in_time' => now()->subMinutes(fake()->numberBetween(5, 60)),
        'picture_visit_in' => 'tmp/test-checkin-'.uniqid().'.jpg',
    ]);
}

/**
 * Helper to generate valid checkout data
 */
function generateValidCheckoutData(): array
{
    return [
        'latlong_out' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
        'laporan_visit' => fake()->sentence(10),
        'picture_visit' => UploadedFile::fake()->image('checkout.jpg', 800, 600),
        'transaksi' => fake()->randomElement(['YES', 'NO']),
    ];
}

/**
 * **Feature: register-workflow, Property 20: Checkout State Transition**
 *
 * *For any* Visit with checkout_at=null, after checkout with valid data,
 * the Visit SHALL have checkout_at set, latlong_out set, picture_visit_out set,
 * laporan_visit set, and transaksi set.
 *
 * **Validates: Requirements 9.1, 9.2**
 */
test('Property 20: Checkout State Transition - valid checkout updates visit with correct data', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create fresh user and hierarchy for each iteration
        $setup = createCheckoutUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create an outlet within user's scope
        $outlet = createCheckoutOutletInScope($hierarchy);

        // Create an active visit (checked in but not checked out)
        $visit = createActiveVisit($user, $outlet);

        // Verify precondition: visit has no checkout data
        expect($visit->check_out_time)->toBeNull('Precondition: check_out_time should be null');

        $checkoutData = generateValidCheckoutData();

        // Act: Perform checkout
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/'.$visit->id.'/checkout', $checkoutData);

        // Assert: Response is successful
        $response->assertStatus(200);
        $response->assertJson([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Check-out berhasil',
            ],
        ]);

        // Refresh visit from database
        $visit->refresh();

        // Assert: Visit was updated with correct invariants
        expect($visit->check_out_time)->not->toBeNull('check_out_time should be set')
            ->and($visit->latlong_out)->toBe($checkoutData['latlong_out'], 'latlong_out should be stored')
            ->and($visit->laporan_visit)->toBe($checkoutData['laporan_visit'], 'laporan_visit should be stored')
            ->and($visit->transaksi)->toBe($checkoutData['transaksi'], 'transaksi should be stored')
            ->and($visit->picture_visit_out)->not->toBeNull('picture_visit_out should be stored')
            ->and($visit->durasi_visit)->not->toBeNull('durasi_visit should be calculated');
    }
});

/**
 * **Feature: register-workflow, Property 21: Checkout Ownership Validation**
 *
 * *For any* checkout attempt on a Visit where user_id != authenticated user's id,
 * the operation SHALL be rejected.
 *
 * **Validates: Requirements 9.3**
 */
test('Property 21: Checkout Ownership Validation - cannot checkout from another user visit', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create two users with same hierarchy
        $setup1 = createCheckoutUserWithHierarchy();
        $user1 = $setup1['user'];
        $hierarchy = $setup1['hierarchy'];

        // Create second user in same hierarchy
        $role2 = \App\Models\Role::create([
            'name' => 'DM2-'.uniqid(),
            'can_access_web' => true,
            'organizational_scope_level' => 'cluster',
        ]);
        $user2 = User::factory()->create(['role_id' => $role2->id]);
        $user2->badanUsahas()->attach($hierarchy['bu']->id);
        $user2->divisis()->attach($hierarchy['div']->id);
        $user2->regions()->attach($hierarchy['reg']->id);
        $user2->clusters()->attach($hierarchy['clus']->id);

        // Create an outlet within scope
        $outlet = createCheckoutOutletInScope($hierarchy);

        // Create an active visit for user1
        $visit = createActiveVisit($user1, $outlet);

        // Verify precondition: visit belongs to user1
        expect($visit->user_id)->toBe($user1->id, 'Precondition: visit should belong to user1');

        $checkoutData = generateValidCheckoutData();

        // Act: user2 tries to checkout from user1's visit
        $response = $this->actingAs($user2, 'sanctum')
            ->postJson('/api/visit/'.$visit->id.'/checkout', $checkoutData);

        // Assert: Request is rejected (404 - visit not found for this user)
        $response->assertStatus(404);

        // Refresh visit from database
        $visit->refresh();

        // Assert: Visit was NOT updated
        expect($visit->check_out_time)->toBeNull('check_out_time should still be null')
            ->and($visit->latlong_out)->toBeNull('latlong_out should still be null')
            ->and($visit->laporan_visit)->toBeNull('laporan_visit should still be null');
    }
});

/**
 * **Feature: register-workflow, Property 22: Double Checkout Prevention**
 *
 * *For any* Visit where checkout_at is already set, checkout attempts SHALL be rejected.
 *
 * **Validates: Requirements 9.4**
 */
test('Property 22: Double Checkout Prevention - cannot checkout from already completed visit', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 100; $i++) {
        // Arrange: Create fresh user and hierarchy for each iteration
        $setup = createCheckoutUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create an outlet within user's scope
        $outlet = createCheckoutOutletInScope($hierarchy);

        // Create a completed visit (already checked out)
        $completedVisit = Visit::create([
            'tanggal_visit' => today(),
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
            'tipe_visit' => fake()->randomElement(['PLANNED', 'EXTRACALL']),
            'latlong_in' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
            'check_in_time' => now()->subMinutes(60),
            'picture_visit_in' => 'tmp/test-checkin-'.uniqid().'.jpg',
            // Already checked out
            'latlong_out' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
            'check_out_time' => now()->subMinutes(30),
            'picture_visit_out' => 'tmp/test-checkout-'.uniqid().'.jpg',
            'laporan_visit' => 'Original report',
            'transaksi' => 'YES',
            'durasi_visit' => 30,
        ]);

        // Verify precondition: visit is already checked out
        expect($completedVisit->check_out_time)->not->toBeNull('Precondition: check_out_time should be set');

        $originalCheckoutTime = (string) $completedVisit->check_out_time;
        $originalReport = $completedVisit->laporan_visit;

        $checkoutData = generateValidCheckoutData();

        // Act: Try to checkout again
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/visit/'.$completedVisit->id.'/checkout', $checkoutData);

        // Assert: Request is rejected (404 - visit not found or already checked out)
        $response->assertStatus(404);

        // Refresh visit from database
        $completedVisit->refresh();

        // Assert: Visit data was NOT changed
        expect((string) $completedVisit->check_out_time)->toBe($originalCheckoutTime, 'check_out_time should not change')
            ->and($completedVisit->laporan_visit)->toBe($originalReport, 'laporan_visit should not change');
    }
});
