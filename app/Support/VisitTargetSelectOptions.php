<?php

namespace App\Support;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\User;
use App\Services\SystemSettingResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class VisitTargetSelectOptions
{
    /**
     * @param  array<int, int>|null  $allowedIds
     * @return array<int, string>
     */
    public static function searchOutlets(string $search, ?User $scopeUser, int $limit = 50, ?array $allowedIds = null): array
    {
        if (! $scopeUser) {
            return [];
        }

        $query = Outlet::query()
            ->select(['id', 'kode_outlet', 'nama_outlet', 'badanusaha_id', 'divisi_id'])
            ->with(['badanusaha:id,name', 'divisi:id,name']);

        self::applyOrganizationalScope($query, $scopeUser, 'outlets');

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return [];
            }

            $query->whereIn('outlets.id', $allowedIds);
        }

        $keyword = trim($search);

        $query->when($keyword !== '', function (Builder $builder) use ($keyword): void {
            $builder->where(function (Builder $scope) use ($keyword): void {
                $scope
                    ->where('nama_outlet', 'like', '%'.$keyword.'%')
                    ->orWhere('kode_outlet', 'like', '%'.$keyword.'%');
            });
        });

        return $query
            ->orderBy('nama_outlet')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (Outlet $outlet): array => [$outlet->id => self::formatOutletLabel($outlet)])
            ->all();
    }

    /**
     * @param  array<int, int>|null  $allowedIds
     * @return array<int, string>
     */
    public static function searchRegisters(
        string $search,
        ?User $scopeUser,
        SystemSettingResolver $resolver,
        int $limit = 50,
        ?array $allowedIds = null,
    ): array {
        if (! $scopeUser) {
            return [];
        }

        $query = Register::query()
            ->select([
                'id',
                'kode_outlet',
                'nama_outlet',
                'badanusaha_id',
                'divisi_id',
                'region_id',
                'cluster_id',
                'status',
            ])
            ->where(function (Builder $builder): void {
                $builder->whereNull('status')->orWhere('status', '!=', 'APPROVED');
            })
            ->whereDoesntHave('outlet');

        self::applyOrganizationalScope($query, $scopeUser, 'registers');

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return [];
            }

            $query->whereIn('registers.id', $allowedIds);
        }

        $keyword = trim($search);

        $query->when($keyword !== '', function (Builder $builder) use ($keyword): void {
            $builder->where(function (Builder $scope) use ($keyword): void {
                $scope
                    ->where('nama_outlet', 'like', '%'.$keyword.'%')
                    ->orWhere('kode_outlet', 'like', '%'.$keyword.'%');
            });
        });

        $candidateLimit = max($limit * 4, 120);

        return $query
            ->orderBy('nama_outlet')
            ->limit($candidateLimit)
            ->get()
            ->filter(fn (Register $register): bool => $resolver->allowsRegisterVisitForModel($register))
            ->take($limit)
            ->mapWithKeys(fn (Register $register): array => [$register->id => self::formatRegisterLabel($register)])
            ->all();
    }

    public static function label(string $type, int|string|null $id): ?string
    {
        if (! $id) {
            return null;
        }

        if ($type === Outlet::class) {
            $outlet = Outlet::query()
                ->select(['id', 'kode_outlet', 'nama_outlet', 'badanusaha_id', 'divisi_id'])
                ->with(['badanusaha:id,name', 'divisi:id,name'])
                ->find($id);

            return $outlet ? self::formatOutletLabel($outlet) : null;
        }

        if ($type === Register::class) {
            $register = Register::query()
                ->select(['id', 'kode_outlet', 'nama_outlet'])
                ->find($id);

            return $register ? self::formatRegisterLabel($register) : null;
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    public static function plannedVisitableIdsForDate(int|string $userId, string $visitableType, mixed $visitDate): array
    {
        if (! $visitDate) {
            return [];
        }

        $date = Carbon::parse($visitDate);

        return PlanVisit::query()
            ->where('user_id', $userId)
            ->where('visitable_type', $visitableType)
            ->whereNull('realized_at')
            ->where(function (Builder $query) use ($date): void {
                $query
                    ->where(function (Builder $daily) use ($date): void {
                        $daily
                            ->where('schedule_scope', 'daily')
                            ->whereDate('tanggal_visit', $date);
                    })
                    ->orWhere(function (Builder $weekly) use ($date): void {
                        $weekly
                            ->where('schedule_scope', 'weekly')
                            ->whereDate('period_start', '<=', $date)
                            ->whereDate('period_end', '>=', $date);
                    });
            })
            ->pluck('visitable_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected static function applyOrganizationalScope(Builder $query, User $user, string $table): void
    {
        if (! $user->role) {
            $query->whereRaw('1 = 0');

            return;
        }

        $scopeLevel = strtolower((string) ($user->role->organizational_scope_level ?? ''));

        if ($scopeLevel === 'all') {
            return;
        }

        $ids = $user->getOrganizationalIds();
        $badanUsahaIds = $ids['badanusaha'] ?? [];
        $divisiIds = $ids['divisi'] ?? [];
        $regionIds = $ids['region'] ?? [];
        $clusterIds = $ids['cluster'] ?? [];

        $hasAnyAssignment = $badanUsahaIds !== [] || $divisiIds !== [] || $regionIds !== [] || $clusterIds !== [];
        if (! $hasAnyAssignment) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($badanUsahaIds !== []) {
            $query->whereIn($table.'.badanusaha_id', $badanUsahaIds);
        }

        if ($divisiIds !== []) {
            $query->whereIn($table.'.divisi_id', $divisiIds);
        }

        if ($regionIds !== []) {
            $query->whereIn($table.'.region_id', $regionIds);
        }

        if ($clusterIds !== []) {
            $query->whereIn($table.'.cluster_id', $clusterIds);
        }
    }

    protected static function formatOutletLabel(Outlet $outlet): string
    {
        $badanusahaName = $outlet->badanusaha->name ?? '-';
        $divisiName = $outlet->divisi->name ?? '-';

        return "[{$outlet->kode_outlet}] {$outlet->nama_outlet} - {$badanusahaName} / {$divisiName}";
    }

    protected static function formatRegisterLabel(Register $register): string
    {
        return "[{$register->kode_outlet}] {$register->nama_outlet} - Register";
    }
}
