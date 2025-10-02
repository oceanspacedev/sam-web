<?php

namespace Tests\Unit;

use App\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\TestCase;

class AuthServiceProviderTest extends TestCase
{
    public function test_dynamic_permission_registration_handles_connection_exception(): void
    {
        Schema::shouldReceive('hasTable')->once()->andThrow(new PDOException('test connection issue'));
        Schema::shouldReceive('hasColumn')->never();

        $provider = new AuthServiceProvider($this->app);

        $provider->boot();

        $this->assertTrue(Gate::has('viewPulse'));
    }
}
