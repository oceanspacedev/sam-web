<?php

namespace App\Observers;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Services\OrganizationalCacheService;
use Illuminate\Database\Eloquent\Model;

class OrganizationalObserver
{
    public function __construct(
        protected OrganizationalCacheService $cacheService
    ) {}

    public function saved(Model $model): void
    {
        $this->clearRelevantCache($model);
    }

    public function deleted(Model $model): void
    {
        $this->clearRelevantCache($model);
    }

    public function restored(Model $model): void
    {
        $this->clearRelevantCache($model);
    }

    protected function clearRelevantCache(Model $model): void
    {
        $class = get_class($model);

        match ($class) {
            BadanUsaha::class => $this->cacheService->clearBadanUsahaCache(),
            Division::class => $this->cacheService->clearDivisionCache($model->badanusaha_id),
            Region::class => $this->cacheService->clearRegionCache($model->divisi_id),
            Cluster::class => $this->cacheService->clearClusterCache($model->region_id),
            Role::class => $this->cacheService->clearRoleCache(),
            default => null,
        };
    }
}
