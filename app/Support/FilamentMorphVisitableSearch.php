<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\Register;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FilamentMorphVisitableSearch
{
    /**
     * Search visit/plan-visit tables by user name or visitable nama/kode.
     *
     * Filament's default relationship search uses whereHas/whereHasMorph('*').
     * That runs DISTINCT visitable_type across the full visits table and correlated
     * EXISTS lookups, which times out on production data.
     *
     * Matching IDs are resolved first so empty outlet/register branches are omitted.
     * An OR against empty subqueries prevents MySQL from using visits.user_id.
     */
    public static function apply(Builder $query, string $search): void
    {
        $keyword = trim($search);

        if ($keyword === '') {
            return;
        }

        $table = $query->getModel()->getTable();
        $like = '%'.addcslashes($keyword, '%_\\').'%';

        $userIds = self::matchingUserIds($like);
        $outletIds = self::matchingTargetIds(Outlet::query(), $like);
        $registerIds = self::matchingTargetIds(Register::query(), $like);

        if ($userIds->isEmpty() && $outletIds->isEmpty() && $registerIds->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($table, $userIds, $outletIds, $registerIds): void {
            $applied = false;

            if ($userIds->isNotEmpty()) {
                $query->whereIn($table.'.user_id', $userIds);
                $applied = true;
            }

            if ($outletIds->isNotEmpty()) {
                $method = $applied ? 'orWhere' : 'where';
                $query->{$method}(function (Builder $query) use ($table, $outletIds): void {
                    $query
                        ->where($table.'.visitable_type', Outlet::class)
                        ->whereIn($table.'.visitable_id', $outletIds);
                });
                $applied = true;
            }

            if ($registerIds->isNotEmpty()) {
                $method = $applied ? 'orWhere' : 'where';
                $query->{$method}(function (Builder $query) use ($table, $registerIds): void {
                    $query
                        ->where($table.'.visitable_type', Register::class)
                        ->whereIn($table.'.visitable_id', $registerIds);
                });
            }
        });
    }

    /**
     * @return Collection<int, int>
     */
    protected static function matchingUserIds(string $like): Collection
    {
        return User::query()
            ->withTrashed()
            ->where('nama_lengkap', 'like', $like)
            ->pluck('id');
    }

    /**
     * @param  Builder<\App\Models\Outlet|\App\Models\Register>  $query
     * @return Collection<int, int>
     */
    protected static function matchingTargetIds(Builder $query, string $like): Collection
    {
        return $query
            ->withTrashed()
            ->where(function (Builder $query) use ($like): void {
                $query
                    ->where('nama_outlet', 'like', $like)
                    ->orWhere('kode_outlet', 'like', $like);
            })
            ->pluck('id');
    }
}
