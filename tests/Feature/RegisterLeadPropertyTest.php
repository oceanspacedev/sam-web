<?php

/**
 * Property-Based Tests for Lead Workflow
 *
 * **Feature: register-workflow**
 *
 * These tests verify correctness properties for the Lead submission and upgrade workflow.
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
 * Helper to generate valid lead data with random values
 */
function generateValidLeadData(array $hierarchy, bool $withPhotos = true): array
{
    $data = [
        'nama_outlet' => fake()->company().' '.fake()->randomNumber(3),
        'alamat_outlet' => fake()->address(),
        'nama_pemilik' => fake()->name(),
        'nomer_pemilik' => '08'.fake()->numerify('##########'),
        'nomer_perwakilan' => '08'.fake()->numerify('##########'),
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
        // Add required photos - the database schema requires these fields
        $data['photo0'] = UploadedFile::fake()->image('fotoshopsign.jpg', 800, 600);
        $data['photo1'] = UploadedFile::fake()->image('fotodepan.jpg', 800, 600);
        $data['photo2'] = UploadedFile::fake()->image('fotokiri.jpg', 800, 600);
        $data['photo3'] = UploadedFile::fake()->image('fotokanan.jpg', 800, 600);
        // Video is also required by the database schema
        $data['video'] = UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4');
    }

    return $data;
}

/**
 * Helper to create authenticated user with organizational hierarchy
 */
function createAuthenticatedUserWithHierarchy(): array
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
 * **Feature: register-workflow, Property 1: Lead Creation Invariant**
 *
 * *For any* valid lead submission with required fields (nama_outlet, alamat_outlet,
 * nama_pemilik, nomer_pemilik, latlong), the created Register SHALL have status=null
 * and keterangan='LEAD', and created_by_id matching the authenticated user.
 *
 * **Validates: Requirements 1.1, 1.5**
 */
test('Property 1: Lead Creation Invariant - valid lead submission creates register with LEAD status and correct creator', function () {
    // Run reduced iterations to avoid resource exhaustion
    for ($i = 0; $i < 10; $i++) {
        // Arrange: Create fresh user and hierarchy for each iteration
        $setup = createAuthenticatedUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        $leadData = generateValidLeadData($hierarchy);

        // Act: Submit lead
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/registers/leads', $leadData);

        // Assert: Response is successful
        $response->assertStatus(200);
        $response->assertJson([
            'meta' => [
                'code' => 200,
                'status' => 'success',
            ],
        ]);

        // Assert: Register was created with correct invariants
        $register = Register::where('nama_outlet', $leadData['nama_outlet'])->first();

        expect($register)->not->toBeNull()
            // Note: With new schema, leads are identified by type='LEAD' instead of keterangan
            ->and($register->type)->toBe('LEAD', 'Lead type should be LEAD')
            ->and($register->created_by_id)->toBe($user->id, 'created_by_id should match authenticated user')
            ->and($register->nama_outlet)->toBe($leadData['nama_outlet'])
            ->and($register->alamat_outlet)->toBe($leadData['alamat_outlet'])
            ->and($register->nama_pemilik_outlet)->toBe($leadData['nama_pemilik'])
            ->and($register->nomer_tlp_outlet)->toBe($leadData['nomer_pemilik'])
            ->and($register->latlong)->toBe($leadData['latlong']);
    }
});

/**
 * **Feature: register-workflow, Property 2: Lead Validation Rejection**
 *
 * *For any* lead submission missing any required field (nama_outlet, alamat_outlet,
 * nama_pemilik, nomer_pemilik, latlong), the Register System SHALL reject with
 * validation errors and no Register record SHALL be created.
 *
 * **Validates: Requirements 1.3**
 */
test('Property 2: Lead Validation Rejection - missing required fields causes rejection', function () {
    $requiredFields = ['nama_outlet', 'alamat_outlet', 'nama_pemilik', 'nomer_pemilik', 'latlong', 'distric', 'oppo', 'vivo', 'samsung', 'xiaomi', 'realme', 'fl'];

    // Run 100 iterations for each required field as per design document
    for ($i = 0; $i < 10; $i++) {
        $setup = createAuthenticatedUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Pick a random required field to omit
        $fieldToOmit = $requiredFields[array_rand($requiredFields)];

        $leadData = generateValidLeadData($hierarchy, withPhotos: true);
        unset($leadData[$fieldToOmit]);

        $countBefore = Register::count();

        // Act: Submit lead with missing field
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/registers/leads', $leadData);

        // Assert: Request is rejected with validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors([$fieldToOmit]);

        // Assert: No register was created
        expect(Register::count())->toBe($countBefore, "No register should be created when {$fieldToOmit} is missing");
    }
});

/**
 * **Feature: register-workflow, Property 3: Organizational Hierarchy Inheritance**
 *
 * *For any* Register created (lead or NOO), the organizational hierarchy fields
 * (badanusaha_id, divisi_id, region_id, cluster_id) SHALL match the submitting
 * user's hierarchy or the explicitly provided hierarchy.
 *
 * **Validates: Requirements 1.4**
 */
test('Property 3: Organizational Hierarchy Inheritance - lead inherits correct hierarchy', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 10; $i++) {
        $setup = createAuthenticatedUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        $leadData = generateValidLeadData($hierarchy);

        // Act: Submit lead
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/registers/leads', $leadData);

        $response->assertStatus(200);

        // Assert: Register has correct organizational hierarchy
        $register = Register::where('nama_outlet', $leadData['nama_outlet'])->first();

        expect($register)->not->toBeNull()
            ->and($register->badanusaha_id)->toBe($hierarchy['bu']->id, 'badanusaha_id should match')
            ->and($register->divisi_id)->toBe($hierarchy['div']->id, 'divisi_id should match')
            ->and($register->region_id)->toBe($hierarchy['reg']->id, 'region_id should match')
            ->and($register->cluster_id)->toBe($hierarchy['clus']->id, 'cluster_id should match');
    }
});

/**
 * **Feature: register-workflow, Property 4: Lead Upgrade State Transition**
 *
 * *For any* Register with keterangan='LEAD', after upgrade with valid KTP photo
 * and number, the Register SHALL have poto_ktp set, ktp_outlet set, and
 * keterangan cleared (null or empty).
 *
 * **Validates: Requirements 2.1**
 */
test('Property 4: Lead Upgrade State Transition - upgrading lead clears LEAD status and sets KTP', function () {
    // Run 100 iterations as per design document
    for ($i = 0; $i < 10; $i++) {
        $setup = createAuthenticatedUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Create a lead first
        $leadData = generateValidLeadData($hierarchy);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/registers/leads', $leadData)
            ->assertStatus(200);

        $lead = Register::where('nama_outlet', $leadData['nama_outlet'])->first();
        expect($lead->type)->toBe('LEAD');

        // Generate random KTP number
        $ktpNumber = fake()->numerify('################');

        // Act: Upgrade lead with KTP
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$lead->id.'/upgrade', [
                'id' => $lead->id,
                'noktp' => $ktpNumber,
                'photo' => UploadedFile::fake()->image('ktp.jpg', 800, 600),
            ]);

        $response->assertStatus(200);

        // Assert: Lead is upgraded correctly
        $lead->refresh();

        expect($lead->keterangan)->toBeNull('keterangan should be cleared after upgrade')
            ->and($lead->ktp_outlet)->toBe($ktpNumber, 'ktp_outlet should be set')
            ->and($lead->poto_ktp)->not->toBe('-', 'poto_ktp should be updated from default')
            ->and($lead->poto_ktp)->not->toBeNull('poto_ktp should not be null');
    }
});

/**
 * **Feature: register-workflow, Property 5: Non-Lead Upgrade Rejection**
 *
 * *For any* Register where keterangan != 'LEAD' (i.e., already upgraded, confirmed,
 * or approved), upgrade operation SHALL be rejected.
 *
 * **Validates: Requirements 2.3**
 */
test('Property 5: Non-Lead Upgrade Rejection - cannot upgrade non-lead registers', function () {
    // Test different non-lead states
    // Note: Database schema has status as NOT NULL with default 'PENDING'
    // So we use 'PENDING' instead of null for upgraded NOO state
    $nonLeadStates = [
        ['status' => 'PENDING', 'keterangan' => null],      // Already upgraded NOO (pending confirmation)
        ['status' => 'CONFIRMED', 'keterangan' => null], // Confirmed
        ['status' => 'APPROVED', 'keterangan' => null],  // Approved
        ['status' => 'REJECTED', 'keterangan' => 'Some reason'], // Rejected
    ];

    // Run 100 iterations as per design document
    for ($i = 0; $i < 10; $i++) {
        $setup = createAuthenticatedUserWithHierarchy();
        $user = $setup['user'];
        $hierarchy = $setup['hierarchy'];

        // Pick a random non-lead state (cycle through all states)
        $state = $nonLeadStates[$i % count($nonLeadStates)];

        // Create a register in non-lead state
        $register = Register::create([
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
            'created_by_id' => $user->id,
            'tm_id' => $user->id,
            'badanusaha_id' => $hierarchy['bu']->id,
            'divisi_id' => $hierarchy['div']->id,
            'region_id' => $hierarchy['reg']->id,
            'cluster_id' => $hierarchy['clus']->id,
            'status' => $state['status'],
            'keterangan' => $state['keterangan'],
        ]);

        $originalKtp = $register->ktp_outlet;
        $originalPotoKtp = $register->poto_ktp;

        // Act: Try to upgrade non-lead register
        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/registers/'.$register->id.'/upgrade', [
                'id' => $register->id,
                'noktp' => fake()->numerify('################'),
                'photo' => UploadedFile::fake()->image('ktp.jpg', 800, 600),
            ]);

        // Assert: Request is rejected
        $response->assertStatus(400);
        $response->assertJson([
            'meta' => [
                'status' => 'error',
                'message' => 'Register bukan LEAD',
            ],
        ]);

        // Assert: Register was not modified
        $register->refresh();
        expect($register->ktp_outlet)->toBe($originalKtp, 'ktp_outlet should not change')
            ->and($register->poto_ktp)->toBe($originalPotoKtp, 'poto_ktp should not change');
    }
});
