<?php

namespace App\Services;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class OrganizationalCacheService
{
    protected int $ttl = 3600; // 1 hour cache

    public function getAllBadanUsaha(): Collection
    {
        return Cache::remember('organizational.badanusaha.all', $this->ttl, function () {
            return BadanUsaha::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        });
    }

    public function getDivisionsByBadanUsaha(int $badanUsahaId): Collection
    {
        return Cache::remember("organizational.divisions.bu.{$badanUsahaId}", $this->ttl, function () use ($badanUsahaId) {
            return Division::query()
                ->where('badanusaha_id', $badanUsahaId)
                ->select('id', 'name', 'badanusaha_id')
                ->orderBy('name')
                ->get();
        });
    }

    public function getRegionsByDivision(int $divisionId): Collection
    {
        return Cache::remember("organizational.regions.div.{$divisionId}", $this->ttl, function () use ($divisionId) {
            return Region::query()
                ->where('divisi_id', $divisionId)
                ->select('id', 'name', 'divisi_id', 'badanusaha_id')
                ->orderBy('name')
                ->get();
        });
    }

    public function getClustersByRegion(int $regionId): Collection
    {
        return Cache::remember("organizational.clusters.reg.{$regionId}", $this->ttl, function () use ($regionId) {
            return Cluster::query()
                ->where('region_id', $regionId)
                ->select('id', 'name', 'region_id', 'divisi_id', 'badanusaha_id')
                ->orderBy('name')
                ->get();
        });
    }

    public function getAllRoles(): Collection
    {
        return Cache::remember('organizational.roles.all', $this->ttl, function () {
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
        Cache::forget('organizational.badanusaha.all');
    }

    public function clearDivisionCache(int $badanUsahaId): void
    {
        Cache::forget("organizational.divisions.bu.{$badanUsahaId}");
    }

    public function clearRegionCache(int $divisionId): void
    {
        Cache::forget("organizational.regions.div.{$divisionId}");
    }

    public function clearClusterCache(int $regionId): void
    {
        Cache::forget("organizational.clusters.reg.{$regionId}");
    }

    public function clearRoleCache(): void
    {
        Cache::forget('organizational.roles.all');
    }

    /**
     * Clear semua organizational cache (use saat bulk import).
     */
    public function clearAllCache(): void
    {
        Cache::tags(['organizational'])->flush();
    }
}
