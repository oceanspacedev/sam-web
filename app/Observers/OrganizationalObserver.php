<?php

namespace App\Observers;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Services\OrganizationalCacheService;
use App\Support\OrganizationalPivotSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class OrganizationalObserver
{
    public function __construct(
        protected OrganizationalCacheService $cacheService,
        protected OrganizationalPivotSync $pivotSync
    ) {}

    public function created(Model $model): void
    {
        $this->pivotSync->attachCreatedRecordToCreator($model);
    }

    public function saved(Model $model): void
    {
        $this->clearRelevantCache($model);
    }

    public function deleted(Model $model): void
    {
        $this->pivotSync->detachDeletedRecordFromUsers($model);
        $this->clearRelevantCache($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->pivotSync->detachDeletedRecordFromUsers($model);
    }

    public function restored(Model $model): void
    {
        $this->clearRelevantCache($model);
    }

    protected function clearRelevantCache(Model $model): void
    {
        $class = get_class($model);

        Cache::forget('diagram_jabatan:tree:v2');

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
