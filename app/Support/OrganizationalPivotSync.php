<?php

namespace App\Support;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizationalPivotSync
{
    /**
     * Attach newly-created hierarchy records to the authenticated creator when
     * the record level is within the creator's organizational scope.
     */
    public function attachCreatedRecordToCreator(Model $record, ?User $user = null): void
    {
        $user ??= Auth::user();

        if (! $user instanceof User || ! $record->getKey()) {
            return;
        }

        $mapping = $this->mappingFor($record);

        if ($mapping === null || ! $this->scopeCanStoreLevel($user, $mapping['level'])) {
            return;
        }

        $user->{$mapping['relation']}()->syncWithoutDetaching([(int) $record->getKey()]);
        $user->forgetOrganizationalIdsCache();
    }

    /**
     * Soft deletes do not trigger database cascades, so direct pivots must be
     * removed explicitly for every user when hierarchy records are deleted.
     */
    public function detachDeletedRecordFromUsers(Model $record): void
    {
        $mapping = $this->mappingFor($record);

        if ($mapping === null || ! $record->getKey()) {
            return;
        }

        DB::table($mapping['table'])
            ->where($mapping['column'], $record->getKey())
            ->delete();

        $user = Auth::user();

        if ($user instanceof User) {
            $user->forgetOrganizationalIdsCache();
        }
    }

    /**
     * @return array{level: string, relation: string, table: string, column: string}|null
     */
    private function mappingFor(Model $record): ?array
    {
        return match ($record::class) {
            BadanUsaha::class => [
                'level' => 'badanusaha',
                'relation' => 'badanUsahas',
                'table' => 'user_badan_usaha',
                'column' => 'badanusaha_id',
            ],
            Division::class => [
                'level' => 'divisi',
                'relation' => 'divisis',
                'table' => 'user_divisi',
                'column' => 'divisi_id',
            ],
            Region::class => [
                'level' => 'region',
                'relation' => 'regions',
                'table' => 'user_regions',
                'column' => 'region_id',
            ],
            Cluster::class => [
                'level' => 'cluster',
                'relation' => 'clusters',
                'table' => 'user_clusters',
                'column' => 'cluster_id',
            ],
            default => null,
        };
    }

    private function scopeCanStoreLevel(User $user, string $recordLevel): bool
    {
        $scopeLevel = strtolower($user->role?->organizational_scope_level ?? 'cluster');

        if ($scopeLevel === 'all') {
            return false;
        }

        $levels = [
            'badanusaha' => 1,
            'divisi' => 2,
            'region' => 3,
            'cluster' => 4,
        ];

        return isset($levels[$scopeLevel], $levels[$recordLevel])
            && $levels[$scopeLevel] >= $levels[$recordLevel];
    }
}
