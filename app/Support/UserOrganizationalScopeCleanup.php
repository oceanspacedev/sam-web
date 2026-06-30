<?php

namespace App\Support;

use App\Models\User;

class UserOrganizationalScopeCleanup
{
    public static function pruneAssignments(User $user): void
    {
        $user->loadMissing('role');

        $scopeLevel = $user->role?->organizational_scope_level ?? 'cluster';

        match ($scopeLevel) {
            'all' => self::detach($user, detachBadanUsaha: true, detachDivisi: true, detachRegion: true, detachCluster: true),
            'badanusaha' => self::detach($user, detachDivisi: true, detachRegion: true, detachCluster: true),
            'divisi' => self::detach($user, detachRegion: true, detachCluster: true),
            'region' => self::detach($user, detachCluster: true),
            default => null,
        };

        $user->forgetOrganizationalIdsCache();
    }

    private static function detach(
        User $user,
        bool $detachBadanUsaha = false,
        bool $detachDivisi = false,
        bool $detachRegion = false,
        bool $detachCluster = false
    ): void {
        if ($detachBadanUsaha) {
            $user->badanUsahas()->detach();
        }

        if ($detachDivisi) {
            $user->divisis()->detach();
        }

        if ($detachRegion) {
            $user->regions()->detach();
        }

        if ($detachCluster) {
            $user->clusters()->detach();
        }
    }
}
