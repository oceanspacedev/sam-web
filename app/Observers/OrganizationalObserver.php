<?php

namespace App\Observers;

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
            \App\Models\BadanUsaha::class => $this->cacheService->clearBadanUsahaCache(),
            \App\Models\Division::class => $this->cacheService->clearDivisionCache($model->badanusaha_id),
            \App\Models\Region::class => $this->cacheService->clearRegionCache($model->divisi_id),
            \App\Models\Cluster::class => $this->cacheService->clearClusterCache($model->region_id),
            \App\Models\Role::class => $this->cacheService->clearRoleCache(),
            default => null,
        };
    }
}
