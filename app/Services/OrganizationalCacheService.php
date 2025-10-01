<?php

namespace App\Services;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class OrganizationalCacheService
{
    protected int $ttl = 3600; // 1 hour cache

    protected string $cacheIndexKey = 'organizational.cache_keys';

    public function getAllBadanUsaha(): Collection
    {
        return $this->remember('organizational.badanusaha.all', function () {
            return BadanUsaha::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });
    }

    public function getDivisionsByBadanUsaha(int $badanUsahaId): Collection
    {
        return $this->remember("organizational.divisions.bu.{$badanUsahaId}", function () use ($badanUsahaId) {
            return Division::query()
                ->where('badanusaha_id', $badanUsahaId)
                ->select('id', 'name', 'badanusaha_id')
                ->orderBy('name')
                ->get();
        });
    }

    public function getRegionsByDivision(int $divisionId): Collection
    {
        return $this->remember("organizational.regions.div.{$divisionId}", function () use ($divisionId) {
            return Region::query()
                ->where('divisi_id', $divisionId)
                ->select('id', 'name', 'divisi_id', 'badanusaha_id')
                ->orderBy('name')
                ->get();
        });
    }

    public function getClustersByRegion(int $regionId): Collection
    {
        return $this->remember("organizational.clusters.reg.{$regionId}", function () use ($regionId) {
            return Cluster::query()
                ->where('region_id', $regionId)
                ->select('id', 'name', 'region_id', 'divisi_id', 'badanusaha_id')
                ->orderBy('name')
                ->get();
        });
    }

    public function getAllRoles(): Collection
    {
        return $this->remember('organizational.roles.all', function () {
            return Role::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Clear cache saat data organizational berubah.
     */
    public function clearBadanUsahaCache(): void
    {
        $this->forgetKey('organizational.badanusaha.all');
    }

    public function clearDivisionCache(int $badanUsahaId): void
    {
        $key = "organizational.divisions.bu.{$badanUsahaId}";
        $this->forgetKey($key);
    }

    public function clearRegionCache(int $divisionId): void
    {
        $key = "organizational.regions.div.{$divisionId}";
        $this->forgetKey($key);
    }

    public function clearClusterCache(int $regionId): void
    {
        $key = "organizational.clusters.reg.{$regionId}";
        $this->forgetKey($key);
    }

    public function clearRoleCache(): void
    {
        $this->forgetKey('organizational.roles.all');
    }

    /**
     * Clear semua organizational cache (use saat bulk import).
     */
    public function clearAllCache(): void
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            Cache::tags(['organizational'])->flush();

            return;
        }

        $keys = Cache::pull($this->cacheIndexKey, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    protected function remember(string $key, Closure $callback): Collection
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            return Cache::tags(['organizational'])->remember($key, $this->ttl, $callback);
        }

        $this->registerCacheKey($key);

        return Cache::remember($key, $this->ttl, $callback);
    }

    protected function registerCacheKey(string $key): void
    {
        $keys = Cache::get($this->cacheIndexKey, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::forever($this->cacheIndexKey, $keys);
        }
    }

    protected function removeCacheKey(string $key): void
    {
        $keys = Cache::get($this->cacheIndexKey, []);

        if ($keys === []) {
            return;
        }

        $filtered = array_values(array_filter($keys, fn ($storedKey) => $storedKey !== $key));

        if ($filtered === []) {
            Cache::forget($this->cacheIndexKey);

            return;
        }

        Cache::forever($this->cacheIndexKey, $filtered);
    }

    protected function forgetKey(string $key): void
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            Cache::tags(['organizational'])->forget($key);

            return;
        }

        Cache::forget($key);
        $this->removeCacheKey($key);
    }
}
