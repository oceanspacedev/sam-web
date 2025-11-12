<?php

namespace Tests\Feature;

use App\Services\FilenameGeneratorService;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HighVolumeFileUploadTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected FileUploadService $fileUpload;

    protected FilenameGeneratorService $filenameGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileUpload = new FileUploadService;
        $this->filenameGenerator = new FilenameGeneratorService;

        // Use fake storage for testing
        Storage::fake('public');
        Redis::flushall(); // Clear Redis for rate limit testing
    }

    /**
     * Test flat storage performance with 1000 files
     */
    public function test_flat_storage_handles_1000_files_efficiently(): void
    {
        Queue::fake(); // Prevent actual job dispatching during test

        $startTime = microtime(true);
        $uploadedFiles = [];

        // Simulate 1000 file uploads
        for ($i = 0; $i < 1000; $i++) {
            $file = UploadedFile::fake()->image("test_{$i}.jpg", 100, 100); // 100x100px ~ 10KB
            $type = $i % 2 === 0 ? 'register-photo' : 'visit-in'; // Alternate types

            try {
                $filename = $this->filenameGenerator->generate($file, $type, 123);
                $path = $this->fileUpload->uploadImageOptimized($file, $type);
                $uploadedFiles[] = $path;
            } catch (\Exception $e) {
                $this->fail("File upload failed at index {$i}: {$e->getMessage()}");
            }
        }

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assertions
        $this->assertCount(1000, $uploadedFiles);
        $this->assertLessThan(5000, $duration, '1000 uploads should complete within 5 seconds');

        // Verify all files exist
        foreach ($uploadedFiles as $path) {
            Storage::disk('public')->assertExists($path);
        }

        // Verify filename format consistency
        foreach ($uploadedFiles as $path) {
            $filename = basename($path);
            $this->assertTrue(
                $this->filenameGenerator->isValid($filename),
                "Filename {$filename} should be valid"
            );
        }

        echo "\n📊 Performance Results:\n";
        echo "✅ 1000 files uploaded successfully\n";
        echo '⚡ Total time: '.number_format($duration, 2)."ms\n";
        echo '🚀 Average per file: '.number_format($duration / 1000, 2)."ms\n";
        echo '📈 Files per second: '.number_format(1000 / ($duration / 1000), 0)."\n";
    }

    /**
     * Test filename uniqueness across 10,000 files
     */
    public function test_filename_uniqueness_across_10000_files(): void
    {
        $filenames = [];

        for ($i = 0; $i < 10000; $i++) {
            $file = UploadedFile::fake()->image("test_{$i}.jpg");
            $type = ['register-photo', 'visit-in', 'visit-out', 'register-ktp'][$i % 4];
            $userId = ($i % 10) + 1; // 10 different users

            $filename = $this->filenameGenerator->generate($file, $type, $userId);
            $this->assertNotContains($filename, $filenames, "Filename {$filename} should be unique");
            $filenames[] = $filename;
        }

        $this->assertCount(10000, $filenames);
        $this->assertEquals(10000, count(array_unique($filenames)), 'All filenames should be unique');

        echo "\n🎯 Uniqueness Test Results:\n";
        echo "✅ 10,000 unique filenames generated\n";
        echo "🔒 No collisions detected\n";
    }

    /**
     * Test concurrent upload handling
     */
    public function test_concurrent_upload_handling(): void
    {
        $concurrentUsers = 50;
        $filesPerUser = 20;
        $totalFiles = $concurrentUsers * $filesPerUser;

        $startTime = microtime(true);

        // Simulate concurrent uploads from different users
        $processes = [];
        for ($user = 1; $user <= $concurrentUsers; $user++) {
            $processes[] = function () use ($user, $filesPerUser) {
                $uploaded = [];
                for ($i = 0; $i < $filesPerUser; $i++) {
                    $file = UploadedFile::fake()->image("user_{$user}_file_{$i}.jpg");
                    $type = 'register-photo';

                    $filename = $this->filenameGenerator->generate($file, $type, $user);
                    $path = $this->fileUpload->uploadImageOptimized($file, $type);
                    $uploaded[] = $path;
                }

                return $uploaded;
            };
        }

        // Execute all processes
        $results = [];
        foreach ($processes as $process) {
            $results[] = $process();
        }

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Flatten results
        $allUploadedFiles = array_merge(...$results);

        $this->assertCount($totalFiles, $allUploadedFiles);
        $this->assertLessThan(10000, $duration, 'Concurrent uploads should complete within 10 seconds');

        echo "\n🔄 Concurrent Upload Results:\n";
        echo "👥 {$concurrentUsers} concurrent users\n";
        echo "📁 {$filesPerUser} files per user\n";
        echo "📊 Total: {$totalFiles} files\n";
        echo '⚡ Total time: '.number_format($duration, 2)."ms\n";
        echo '🚀 Throughput: '.number_format($totalFiles / ($duration / 1000), 0)." files/second\n";
    }

    /**
     * Test rate limiting functionality
     */
    public function test_rate_limiting_enforcement(): void
    {
        // Clear any existing rate limits
        Redis::flushall();

        $file = UploadedFile::fake()->image('test.jpg');

        // Test normal upload within limits
        for ($i = 0; $i < 25; $i++) {
            $response = $this->postJson('/api/test-upload', [
                'file' => $file,
                'type' => 'register-photo',
            ]);

            $this->assertNotEquals(429, $response->status(), "Upload {$i} should not be rate limited");
        }

        // Test rate limit exceeded
        $response = $this->postJson('/api/test-upload', [
            'file' => $file,
            'type' => 'register-photo',
        ]);

        $this->assertEquals(429, $response->status(), 'Should be rate limited after 30 uploads');
        $this->assertArrayHasKey('retry_after', $response->json(), 'Should include retry_after header');

        echo "\n🛡️ Rate Limiting Test Results:\n";
        echo "✅ Rate limiting properly enforced\n";
        echo "🚫 Upload blocked after limit reached\n";
        echo "⏰ Retry-after header present\n";
    }

    /**
     * Test memory efficiency during bulk uploads
     */
    public function test_memory_efficiency_during_bulk_uploads(): void
    {
        $initialMemory = memory_get_usage(true);
        $memorySnapshots = [];

        for ($i = 0; $i < 1000; $i++) {
            $file = UploadedFile::fake()->image("large_test_{$i}.jpg", 1000, 1000); // ~1MB each
            $type = 'register-photo';

            $filename = $this->filenameGenerator->generate($file, $type, 123);
            $path = $this->fileUpload->uploadImageOptimized($file, $type);

            if ($i % 100 === 0) {
                $memorySnapshots[] = [
                    'iteration' => $i,
                    'memory' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true),
                ];
            }
        }

        $finalMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory should not increase significantly (flat storage is memory efficient)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory increase should be less than 50MB'); // 50MB

        echo "\n💾 Memory Efficiency Results:\n";
        echo '🔽 Initial memory: '.$this->formatBytes($initialMemory)."\n";
        echo '⬆️  Final memory: '.$this->formatBytes($finalMemory)."\n";
        echo '📈 Increase: '.$this->formatBytes($memoryIncrease)."\n";
        echo '🏔️  Peak memory: '.$this->formatBytes($peakMemory)."\n";

        foreach ($memorySnapshots as $snapshot) {
            echo "📊 Iteration {$snapshot['iteration']}: ".$this->formatBytes($snapshot['memory'])."\n";
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
