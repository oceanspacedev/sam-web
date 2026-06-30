<?php

namespace App\Support;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ImportOrganizationalResolver
{
    /**
     * @var array<string, int>
     */
    private array $cache = [];

    public function resolveBadanUsahaId(string $name): int
    {
        return $this->remember('badan_usaha', $name, function () use ($name): int {
            $record = $this->findByNormalizedName(BadanUsaha::query(), $name);

            if (! $record) {
                throw new Exception("Badan usaha '{$name}' tidak ditemukan.");
            }

            return $record->id;
        });
    }

    public function resolveDivisionId(string $name, int $badanusahaId): int
    {
        $cacheKey = "division:{$badanusahaId}:{$name}";

        return $this->remember($cacheKey, $name, function () use ($name, $badanusahaId): int {
            $record = $this->findByNormalizedName(
                Division::query()->where('badanusaha_id', $badanusahaId),
                $name,
            );

            if (! $record) {
                $badanUsahaName = BadanUsaha::query()->whereKey($badanusahaId)->value('name') ?? (string) $badanusahaId;

                throw new Exception("Divisi '{$name}' tidak ditemukan di badan usaha '{$badanUsahaName}'.");
            }

            return $record->id;
        });
    }

    public function resolveRegionId(string $name, int $divisiId, int $badanusahaId): int
    {
        $cacheKey = "region:{$badanusahaId}:{$divisiId}:{$name}";

        return $this->remember($cacheKey, $name, function () use ($name, $divisiId, $badanusahaId): int {
            $record = $this->findByNormalizedName(
                Region::query()
                    ->where('divisi_id', $divisiId)
                    ->where('badanusaha_id', $badanusahaId),
                $name,
            );

            if (! $record) {
                $divisionName = Division::query()->whereKey($divisiId)->value('name') ?? (string) $divisiId;

                throw new Exception("Region '{$name}' tidak ditemukan di divisi '{$divisionName}'.");
            }

            return $record->id;
        });
    }

    public function resolveClusterId(string $name, int $badanusahaId, int $divisiId, int $regionId): int
    {
        $cacheKey = "cluster:{$badanusahaId}:{$divisiId}:{$regionId}:{$name}";

        return $this->remember($cacheKey, $name, function () use ($name, $badanusahaId, $divisiId, $regionId): int {
            $record = $this->findByNormalizedName(
                Cluster::query()
                    ->where('badanusaha_id', $badanusahaId)
                    ->where('divisi_id', $divisiId)
                    ->where('region_id', $regionId),
                $name,
            );

            if (! $record) {
                $regionName = Region::query()->whereKey($regionId)->value('name') ?? (string) $regionId;

                throw new Exception("Cluster '{$name}' tidak ditemukan di region '{$regionName}'.");
            }

            return $record->id;
        });
    }

    public function resolveDivisionModel(string $name, ?int $badanusahaId = null): Division
    {
        if ($badanusahaId !== null) {
            $divisionId = $this->resolveDivisionId($name, $badanusahaId);

            return Division::query()->findOrFail($divisionId);
        }

        $matches = $this->matchingRecords(Division::query(), $name);

        if ($matches->isEmpty()) {
            throw new Exception("Divisi '{$name}' tidak ditemukan.");
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        throw new Exception(
            "Divisi '{$name}' ditemukan di {$matches->count()} badan usaha. Isi kolom badan_usaha pada baris import."
        );
    }

    public function resolveDivisionModelForImport(string $divisionName, ?string $badanUsahaName, ?User $importer): Division
    {
        $badanUsahaName = trim((string) $badanUsahaName);

        if ($badanUsahaName !== '') {
            $badanusahaId = $this->resolveBadanUsahaId($badanUsahaName);

            return $this->resolveDivisionModel($divisionName, $badanusahaId);
        }

        if ($importer) {
            $badanUsahaIds = $importer->getOrganizationalIds()['badanusaha'] ?? [];

            if (count($badanUsahaIds) === 1) {
                return $this->resolveDivisionModel($divisionName, $badanUsahaIds[0]);
            }
        }

        return $this->resolveDivisionModel($divisionName);
    }

    /**
     * @deprecated Use resolveDivisionModel() with explicit badan usaha scope.
     */
    public function resolveDivision(string $name): ?Division
    {
        return $this->findByNormalizedName(Division::query(), $name);
    }

    private function remember(string $scope, string $name, callable $resolver): int
    {
        $normalized = OrganizationalName::normalizeLookup($name);
        $cacheKey = "{$scope}:{$normalized}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        return $this->cache[$cacheKey] = $resolver();
    }

    private function findByNormalizedName(Builder $query, string $name): ?Model
    {
        return $this->matchingRecords($query, $name)->first();
    }

    /**
     * @return Collection<int, Model>
     */
    private function matchingRecords(Builder $query, string $name): Collection
    {
        $normalized = OrganizationalName::normalizeLookup($name);

        return $query
            ->select(['id', 'code', 'name'])
            ->where(function (Builder $query) use ($normalized): void {
                $query
                    ->whereRaw('REPLACE(REPLACE(UPPER(code), " ", ""), "_", "") = ?', [$normalized])
                    ->orWhereRaw('REPLACE(REPLACE(UPPER(name), " ", ""), "_", "") = ?', [$normalized]);
            })
            ->get();
    }
}
