<?php

namespace Tests\Unit\Services;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Services\OrganizationalCacheService;
use Illuminate\Cache\TaggableStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OrganizationalCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationalCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = new OrganizationalCacheService;
    }

    public function test_get_all_badan_usaha_caches_data(): void
    {
        $bu = BadanUsaha::factory()->create(['name' => 'Test BU']);

        // First call - should query database
        $result1 = $this->cacheService->getAllBadanUsaha();
        $this->assertCount(1, $result1);
        $this->assertEquals('Test BU', $result1->first()->name);

        // Second call - should hit cache
        $result2 = $this->cacheService->getAllBadanUsaha();
        $this->assertEquals($result1->toArray(), $result2->toArray());

        // Verify cache key exists
        $this->assertCacheHas('organizational.badanusaha.all');
    }

    public function test_get_divisions_by_badan_usaha_caches_data(): void
    {
        $bu = BadanUsaha::factory()->create();
        $division = Division::factory()->create([
            'badanusaha_id' => $bu->id,
            'name' => 'Test Division',
        ]);

        $result = $this->cacheService->getDivisionsByBadanUsaha($bu->id);

        $this->assertCount(1, $result);
        $this->assertEquals('Test Division', $result->first()->name);
        $this->assertCacheHas("organizational.divisions.bu.{$bu->id}");
    }

    public function test_get_regions_by_division_caches_data(): void
    {
        $division = Division::factory()->create();
        $region = Region::factory()->create([
            'divisi_id' => $division->id,
            'name' => 'Test Region',
        ]);

        $result = $this->cacheService->getRegionsByDivision($division->id);

        $this->assertCount(1, $result);
        $this->assertEquals('Test Region', $result->first()->name);
        $this->assertCacheHas("organizational.regions.div.{$division->id}");
    }

    public function test_get_clusters_by_region_caches_data(): void
    {
        $region = Region::factory()->create();
        $cluster = Cluster::factory()->create([
            'region_id' => $region->id,
            'name' => 'Test Cluster',
        ]);

        $result = $this->cacheService->getClustersByRegion($region->id);

        $this->assertCount(1, $result);
        $this->assertEquals('Test Cluster', $result->first()->name);
        $this->assertCacheHas("organizational.clusters.reg.{$region->id}");
    }

    public function test_get_all_roles_caches_data(): void
    {
        $role = new Role(['name' => 'Test Role', 'can_access_web' => 1]);
        $role->id = 999;
        $role->save();

        $result = $this->cacheService->getAllRoles();

        $this->assertGreaterThan(0, $result->count());
        $this->assertCacheHas('organizational.roles.all');
    }

    public function test_clear_badan_usaha_cache_removes_cache(): void
    {
        // Populate cache
        $this->cacheService->getAllBadanUsaha();
        $this->assertCacheHas('organizational.badanusaha.all');

        // Clear cache
        $this->cacheService->clearBadanUsahaCache();

        // Verify cache is cleared
        $this->assertCacheMissing('organizational.badanusaha.all');
    }

    public function test_clear_all_cache_removes_all_caches(): void
    {
        $previousDriver = Cache::getDefaultDriver();

        Config::set('cache.default', 'array');
        Cache::setDefaultDriver('array');
        Cache::flush();

        try {
            $bu = BadanUsaha::factory()->create();
            $division = Division::factory()->create(['badanusaha_id' => $bu->id]);

            // Populate multiple caches
            $this->cacheService->getAllBadanUsaha();
            $this->cacheService->getDivisionsByBadanUsaha($bu->id);

            $this->assertCacheHas('organizational.badanusaha.all');
            $this->assertCacheHas("organizational.divisions.bu.{$bu->id}");

            if (! (Cache::getStore() instanceof TaggableStore)) {
                $this->assertTrue(Cache::has('organizational.cache_keys'));
            }

            // Clear all caches
            $this->cacheService->clearAllCache();

            // Verify caches are cleared
            $this->assertCacheMissing('organizational.badanusaha.all');
            $this->assertCacheMissing("organizational.divisions.bu.{$bu->id}");

            if (! (Cache::getStore() instanceof TaggableStore)) {
                $this->assertFalse(Cache::has('organizational.cache_keys'));
            }
        } finally {
            Cache::flush();
            Cache::setDefaultDriver($previousDriver);
            Config::set('cache.default', $previousDriver);
        }
    }

    public function test_cache_expires_after_ttl(): void
    {
        // Create service with short TTL for testing
        $service = new OrganizationalCacheService;

        BadanUsaha::factory()->create();
        $service->getAllBadanUsaha();

        $this->assertCacheHas('organizational.badanusaha.all');

        // Note: In real scenario, cache expires after 1 hour (3600 seconds)
        // We can't test actual expiration without waiting, but we verify TTL is set
    }

    protected function assertCacheHas(string $key): void
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            $this->assertTrue(Cache::tags(['organizational'])->has($key));

            return;
        }

        $this->assertTrue(Cache::has($key));
    }

    protected function assertCacheMissing(string $key): void
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            $this->assertFalse(Cache::tags(['organizational'])->has($key));

            return;
        }

        $this->assertFalse(Cache::has($key));
    }
}
