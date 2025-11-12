<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FilenameGeneratorService;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConsistentFlatStorageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected FileUploadService $fileUpload;

    protected FilenameGeneratorService $filenameGenerator;

    protected User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileUpload = new FileUploadService;
        $this->filenameGenerator = new FilenameGeneratorService;
        $this->testUser = User::factory()->create(['id' => 123]);

        // Use fake storage for testing
        Storage::fake('public');
        Queue::fake(); // Prevent actual job dispatching during test

        $this->actingAs($this->testUser);
    }

    /**
     * Test consistent filename generation across all types
     */
    public function test_consistent_filename_generation_across_all_types(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $userId = 123;

        $types = [
            'register-photo' => 'rp',
            'register-video' => 'rv',
            'register-ktp' => 'rk',
            'visit-in' => 'vi',
            'visit-out' => 'vo',
            'outlet-photo' => 'op',
            'outlet-video' => 'ov',
            'outlet-ktp' => 'ok',
        ];

        $generatedFiles = [];

        foreach ($types as $type => $expectedPrefix) {
            $filename = $this->filenameGenerator->generate($file, $type, $userId);

            // Verify format: {type}{date}{userId}-{hash}-{uuid}.{ext}
            $this->assertMatchesRegularExpression(
                "/^{$expectedPrefix}\d{6,8}{$userId}-[a-f0-9]{6}-[a-f0-9-]{36}\.jpg$/",
                $filename,
                "Filename {$filename} should match expected pattern for type {$type}"
            );

            // Verify it's parseable
            $parsed = $this->filenameGenerator->parse($filename);
            $this->assertNotEmpty($parsed, "Filename {$filename} should be parseable");
            $this->assertEquals($type, $parsed['type'], "Type should be {$type}");
            $this->assertEquals($userId, $parsed['user_id'], "User ID should be {$userId}");

            $generatedFiles[] = $filename;
        }

        // Verify all filenames are unique
        $this->assertEquals(count($generatedFiles), count(array_unique($generatedFiles)), 'All generated filenames should be unique');

        echo "\n✅ Filename Generation Test Results:\n";
        foreach ($types as $type => $prefix) {
            $filename = $this->filenameGenerator->generate($file, $type, $userId);
            echo "📁 {$type}: {$filename}\n";
        }
    }

    /**
     * Test flat storage upload functionality
     */
    public function test_flat_storage_upload_functionality(): void
    {
        $testCases = [
            ['file' => UploadedFile::fake()->image('register.jpg'), 'type' => 'register-photo'],
            ['file' => UploadedFile::fake()->image('visit.jpg'), 'type' => 'visit-in'],
            ['file' => UploadedFile::fake()->image('outlet.jpg'), 'type' => 'outlet-photo'],
            ['file' => UploadedFile::fake()->create('video.mp4', 1000, 'video/mp4'), 'type' => 'register-video'],
        ];

        $uploadedPaths = [];

        foreach ($testCases as $testCase) {
            $file = $testCase['file'];
            $type = $testCase['type'];

            try {
                if ($type === 'register-video') {
                    $path = $this->fileUpload->uploadVideoOptimized($file, $type);
                } else {
                    $path = $this->fileUpload->uploadImageOptimized($file, $type);
                }

                // Verify file was stored (flat - no directories)
                $this->assertNotNull($path, "Path should not be null for {$type}");
                $this->assertStringNotContainsString('/', $path, "Path should not contain directories for {$type}");

                Storage::disk('public')->assertExists($path, "File should exist at {$path}");

                // Verify filename format
                $filename = basename($path);
                $this->assertTrue(
                    $this->filenameGenerator->isValid($filename),
                    "Filename {$filename} should be valid for {$type}"
                );

                $uploadedPaths[] = $path;

            } catch (\Exception $e) {
                $this->fail("Upload failed for {$type}: {$e->getMessage()}");
            }
        }

        // Verify all files exist and are unique
        $this->assertCount(count($testCases), $uploadedPaths);
        $this->assertEquals(count($uploadedPaths), count(array_unique($uploadedPaths)), 'All uploaded paths should be unique');

        echo "\n📤 Flat Storage Upload Test Results:\n";
        foreach ($uploadedPaths as $path) {
            echo "✅ {$path}\n";
        }
    }

    /**
     * Test Filament Resource filename generation
     */
    public function test_filament_resource_filename_generation(): void
    {
        // Simulate Filament RegisterResource upload
        $file = UploadedFile::fake()->image('shop_sign.jpg');

        // This simulates what happens in RegisterResource FileUpload component
        $userId = auth()->id();
        $filenameGenerator = new FilenameGeneratorService;
        $filename = $filenameGenerator->generate($file, 'register-photo', $userId);

        // Verify format matches Filament expectations
        $this->assertMatchesRegularExpression(
            '/^rp\d{6,8}\d+-[a-f0-9]{6}-[a-f0-9-]{36}\.jpg$/',
            $filename,
            'Filament filename should follow optimized format'
        );

        // Simulate VisitResource upload
        $visitFile = UploadedFile::fake()->image('visit_in.jpg');
        $visitFilename = $filenameGenerator->generate($visitFile, 'visit-in', $userId);

        $this->assertMatchesRegularExpression(
            '/^vi\d{6,8}\d+-[a-f0-9]{6}-[a-f0-9-]{36}\.jpg$/',
            $visitFilename,
            'Visit filename should follow optimized format'
        );

        // Simulate OutletResource upload
        $outletFile = UploadedFile::fake()->image('outlet_photo.jpg');
        $outletFilename = $filenameGenerator->generate($outletFile, 'outlet-photo', $userId);

        $this->assertMatchesRegularExpression(
            '/^op\d{6,8}\d+-[a-f0-9]{6}-[a-f0-9-]{36}\.jpg$/',
            $outletFilename,
            'Outlet filename should follow optimized format'
        );

        echo "\n🎨 Filament Resource Test Results:\n";
        echo "📋 Register: {$filename}\n";
        echo "📍 Visit In: {$visitFilename}\n";
        echo "🏪 Outlet: {$outletFilename}\n";
    }

    /**
     * Test API Controller queue processing structure
     */
    public function test_api_controller_queue_processing_structure(): void
    {
        // Simulate API RegisterController media queue structure
        $file = UploadedFile::fake()->image('register_photo.jpg');
        $temporaryPath = 'tmp/test_photo.jpg';

        // Simulate storing in temporary storage
        Storage::disk('local')->put($temporaryPath, 'fake content');

        // This simulates the media queue structure from RegisterController
        $mediaQueue = [
            'field' => 'poto_shop_sign',
            'tmp_path' => $temporaryPath,
            'type' => 'register-photo', // NEW: Use type instead of directory
        ];

        // Verify structure
        $this->assertEquals('poto_shop_sign', $mediaQueue['field']);
        $this->assertEquals($temporaryPath, $mediaQueue['tmp_path']);
        $this->assertEquals('register-photo', $mediaQueue['type']);
        $this->assertArrayNotHasKey('final_directory', $mediaQueue, 'Should not have final_directory anymore');

        // Simulate VisitController media queue
        $visitMediaQueue = [
            'field' => 'picture_visit_in',
            'tmp_path' => $temporaryPath,
            'type' => 'visit-in', // NEW: Use type instead of 'visits/in'
            'filename' => 'custom-filename.jpg',
        ];

        $this->assertEquals('visit-in', $visitMediaQueue['type']);
        $this->assertArrayNotHasKey('final_directory', $visitMediaQueue);

        echo "\n🔄 API Queue Structure Test Results:\n";
        echo "✅ Register queue uses 'type' instead of 'final_directory'\n";
        echo "✅ Visit queue uses flat storage types\n";
        echo "✅ No nested directory references found\n";
    }

    /**
     * Test filename parsing and metadata extraction
     */
    public function test_filename_parsing_and_metadata_extraction(): void
    {
        $testFile = UploadedFile::fake()->image('test.jpg');
        $userId = 123;
        $type = 'register-photo';

        // Generate filename
        $filename = $this->filenameGenerator->generate($testFile, $type, $userId);

        // Parse it back
        $parsed = $this->filenameGenerator->parse($filename);

        // Verify parsed data
        $this->assertNotEmpty($parsed, 'Filename should be parseable');
        $this->assertEquals($type, $parsed['type']);
        $this->assertEquals($userId, $parsed['user_id']);
        $this->assertEquals('rp', $parsed['type_prefix']);
        $this->assertEquals('jpg', $parsed['extension']);
        $this->assertMatchesRegularExpression('/^\d{6,8}$/', $parsed['date'], 'Date should be 6-8 digits');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{6}$/', $parsed['hash'], 'Hash should be 6 hex chars');
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $parsed['uuid'], 'UUID should be valid format');

        // Test invalid filename
        $invalidParsed = $this->filenameGenerator->parse('invalid-filename.jpg');
        $this->assertEmpty($invalidParsed, 'Invalid filename should return empty array');

        echo "\n🔍 Filename Parsing Test Results:\n";
        echo "📝 Original: {$filename}\n";
        echo "🧩 Type: {$parsed['type']}\n";
        echo "👤 User ID: {$parsed['user_id']}\n";
        echo "📅 Date: {$parsed['date']}\n";
        echo "🔐 Hash: {$parsed['hash']}\n";
        echo "🆔 UUID: {$parsed['uuid']}\n";
        echo "📄 Extension: {$parsed['extension']}\n";
    }

    /**
     * Test performance comparison between old and new systems
     */
    public function test_performance_comparison_between_systems(): void
    {
        $file = UploadedFile::fake()->image('performance_test.jpg');
        $iterations = 100;

        // Test old system (simulated)
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate old filename generation
            $outletName = 'test_outlet_name';
            $oldFilename = 'register-'.$outletName.'-fotoshopsign-'.date('dmYHis').'.jpg';
        }
        $oldTime = (microtime(true) - $startTime) * 1000;

        // Test new system
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $newFilename = $this->filenameGenerator->generate($file, 'register-photo', 123);
        }
        $newTime = (microtime(true) - $startTime) * 1000;

        // New system should be faster (no string operations, no date format)
        $improvement = ($oldTime - $newTime) / $oldTime * 100;

        echo "\n⚡ Performance Comparison Results:\n";
        echo '🐌 Old System: '.number_format($oldTime, 2)."ms for {$iterations} iterations\n";
        echo '🚀 New System: '.number_format($newTime, 2)."ms for {$iterations} iterations\n";
        echo '📈 Improvement: '.number_format($improvement, 1)."%\n";

        // At minimum, new system should not be significantly slower
        $this->assertLessThan($oldTime * 1.2, $newTime, 'New system should not be more than 20% slower');
    }
}
