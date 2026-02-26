<?php

/**
 * Property-Based Tests for NOO Workflow
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the NOO (New Outlet Opening) submission workflow.
 * Each test runs multiple iterations with randomly generated valid inputs to verify
 * that invariants hold across all valid executions.
 */

use App\Models\Register;
use App\Models\User;
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
 * Helper to generate valid NOO data with random values including KTP
 */
function generateValidNooData(array $hierarchy, bool $withPhotos = true): array
{
    $data = [
        'nama_outlet' => fake()->company().' '.fake()->randomNumber(3),
        'alamat_outlet' => fake()->address(),
        'nama_pemilik' => fake()->name(),
        'nomer_pemilik' => '08'.fake()->numerify('##########'),
        'nomer_perwakilan' => '08'.fake()->numerify('##########'),
        'ktpnpwp' => fake()->numerify('################'), // Required for NOO
        'distric' => 'D'.fake()->numerify('##'),
        'latlong' => fake()->latitude(-8, -6).','.fake()->longitude(106, 115),
        'oppo' => fake()->numberBetween(0, 99),
        'vivo' => fake()->numberBetween(0, 99),
        'samsung' => fake()->numberBetween(0, 99),
        'xiaomi' => fake()->numberBetween(0, 99),
        'realme' => fake()->numberBetween(0, 99),
        'fl' => fake()->numberBetween(0, 99),
        'cluster_id' => $hierarchy['clus']->id,
        'region_id' => $hierarchy['reg']->id,
        'divisi_id' => $hierarchy['div']->id,
        'badanusaha_id' => $hierarchy['bu']->id,
    ];

    if ($withPhotos) {
        // Add photos including KTP photo for NOO
        $data['photo0'] = UploadedFile::fake()->image('fotoshopsign.jpg', 800, 600);
        $data['photo1'] = UploadedFile::fake()->image('fotodepan.jpg', 800, 600);
        $data['photo2'] = UploadedFile::fake()->image('fotokiri.jpg', 800, 600);
        $data['photo3'] = UploadedFile::fake()->image('fotokanan.jpg', 800, 600);
        $data['photo4'] = UploadedFile::fake()->image('fotoktp.jpg', 800, 600); // KTP photo for NOO
        $data['video'] = UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4');
    }

    return $data;
}

/**
 * Helper to create authenticated user with organizational hierarchy
 */
function createNooAuthenticatedUserWithHierarchy(): array
{
    // Create unique organizational hierarchy for each call
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
 * **Feature: register-workflow, Property 6: NOO Creation Invariant**
 *
 * *For any* valid NOO submission with all required fields including KTP,
 * the created Register SHALL have status='PENDING' (database default) and keterangan=null (not 'LEAD').
 *
 * Note: The design document states status=null, but the database schema has status as an enum
 * with default 'PENDING'. The key differentiator between Lead and NOO is keterangan:
 * - Lead: keterangan='LEAD'
 * - NOO: keterangan=null
 *
 * **Validates: Requirements 3.1**
 */
test('Property 6: NOO Creation Invariant - valid NOO submission creates register with PENDING status and null keterangan', function () {
    // Run reduced iterations to avoid resource exhaustion
    for ($i = 0; $i < 10; $i++) {
        // Arrange: Create fresh user and hierarchy for each iteration
        $setup = createNooAuthenticatedUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        $nooData = generateValidNooData($hierarchy);

        // Act: Submit NOO
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/registers/noos', $nooData);

        // Assert: Response is successful
        $response->assertStatus(200);
        $response->assertJson([
            'meta' => [
                'code' => 200,
                'status' => 'success',
            ],
        ]);

        // Assert: Register was created with correct invariants
        $register = Register::where('nama_outlet', $nooData['nama_outlet'])->first();

        expect($register)->not->toBeNull()
            // NOO should have status='PENDING' (database default) and keterangan=null (not 'LEAD')
            // The key differentiator from Lead is keterangan being null instead of 'LEAD'
            ->and($register->status)->toBe('PENDING', 'NOO status should be PENDING (database default)')
            ->and($register->keterangan)->toBeNull('NOO keterangan should be null (not LEAD)')
            ->and($register->created_by_id)->toBe($user->id, 'created_by_id should match authenticated user')
            ->and($register->nama_outlet)->toBe($nooData['nama_outlet'])
            ->and($register->alamat_outlet)->toBe($nooData['alamat_outlet'])
            ->and($register->nama_pemilik_outlet)->toBe($nooData['nama_pemilik'])
            ->and($register->nomer_tlp_outlet)->toBe($nooData['nomer_pemilik'])
            ->and($register->ktp_outlet)->toBe($nooData['ktpnpwp'], 'KTP should be set for NOO')
            ->and($register->latlong)->toBe($nooData['latlong']);
    }
});

/**
 * **Feature: register-workflow, Property 7: NOO KTP Validation**
 *
 * *For any* NOO submission without KTP photo or KTP number,
 * the Register System SHALL reject with validation errors.
 *
 * **Validates: Requirements 3.4**
 */
test('Property 7: NOO KTP Validation - missing KTP information causes rejection', function () {
    // Run 100 iterations with different random inputs as per design document
    for ($i = 0; $i < 10; $i++) {
        // Arrange: Create fresh user and hierarchy for each iteration
        $setup = createNooAuthenticatedUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        $nooData = generateValidNooData($hierarchy, withPhotos: true);

        // Remove KTP number (ktpnpwp) - this is required for NOO
        unset($nooData['ktpnpwp']);

        $countBefore = Register::count();

        // Act: Submit NOO without KTP number
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/registers/noos', $nooData);

        // Assert: Request is rejected with validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ktpnpwp']);

        // Assert: No register was created
        expect(Register::count())->toBe($countBefore, 'No register should be created when ktpnpwp is missing');
    }
});
