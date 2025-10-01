<?php

namespace Tests\Feature;

use App\Support\StorageDisk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the configured default disk (and public for backwards compatibility) to prevent real filesystem writes.
        $defaultDisk = StorageDisk::default();
        Storage::fake($defaultDisk);

        if ($defaultDisk !== 'public') {
            Storage::fake('public');
        }
    }
}
