<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->cleanupOrganizationalPivots($user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->cleanupOrganizationalPivots($user);
    }

    /**
     * Clean up organizational pivot tables based on user's role scope level.
     * This ensures users only have organizational assignments relevant to their scope.
     */
    protected function cleanupOrganizationalPivots(User $user): void
    {
        // Reload role relation to ensure we have the latest data
        $user->load('role');

        if (! $user->role) {
            Log::warning('User has no role assigned', ['user_id' => $user->id]);

            return;
        }

        $scopeLevel = $user->role->organizational_scope_level ?? 'cluster';

        Log::info('Cleaning up organizational pivots', [
            'user_id' => $user->id,
            'scope_level' => $scopeLevel,
        ]);

        // Cleanup based on hierarchy: all > badanusaha > divisi > region > cluster
        switch ($scopeLevel) {
            case 'all':
                // Full access: remove all specific organizational assignments
                $user->badanUsahas()->detach();
                $user->divisis()->detach();
                $user->regions()->detach();
                $user->clusters()->detach();
                Log::info('Detached all organizational assignments (scope: all)', ['user_id' => $user->id]);
                break;

            case 'badanusaha':
                // Keep badan usaha, remove lower levels
                $user->divisis()->detach();
                $user->regions()->detach();
                $user->clusters()->detach();
                Log::info('Detached divisi, region, cluster (scope: badanusaha)', ['user_id' => $user->id]);
                break;

            case 'divisi':
                // Keep badan usaha and divisi, remove lower levels
                $user->regions()->detach();
                $user->clusters()->detach();
                Log::info('Detached region, cluster (scope: divisi)', ['user_id' => $user->id]);
                break;

            case 'region':
                // Keep badan usaha, divisi, and region, remove only cluster
                $user->clusters()->detach();
                Log::info('Detached cluster (scope: region)', ['user_id' => $user->id]);
                break;

            case 'cluster':
                // No cleanup needed - this is the lowest level
                Log::info('No cleanup needed (scope: cluster)', ['user_id' => $user->id]);
                break;

            default:
                Log::warning('Unknown scope level, defaulting to no cleanup', [
                    'user_id' => $user->id,
                    'scope_level' => $scopeLevel,
                ]);
                break;
        }
    }
}
