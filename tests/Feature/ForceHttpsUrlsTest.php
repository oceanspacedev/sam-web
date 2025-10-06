<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

class ForceHttpsUrlsTest extends FeatureTestCase
{
    protected function tearDown(): void
    {
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_urls_are_generated_with_https_when_environment_requires_it(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.url', 'http://example.test');

        $provider = new AppServiceProvider($this->app);

        $provider->boot();

        $this->assertStringStartsWith('https://', url('/livewire/livewire.min.js'));
    }
}
