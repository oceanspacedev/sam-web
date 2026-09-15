<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.default' => 'public',
            'filament.default_filesystem_disk' => 'public',
        ]);
    }
}
