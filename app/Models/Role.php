<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $guarded = [
        'id',
    ];

    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Get the organizational scope level for this role
     */
    public function getOrganizationalScopeLevel(): string
    {
        return $this->organizational_scope_level ?? 'cluster';
    }

    /**
     * Check if this role has full access (no filtering)
     */
    public function hasFullAccess(): bool
    {
        return $this->organizational_scope_level === 'all';
    }

    /**
     * Check if role should filter by specific organizational level
     */
    public function shouldFilterByLevel(string $level): bool
    {
        $hierarchy = ['all', 'badanusaha', 'divisi', 'region', 'cluster'];
        $roleLevel = $this->getOrganizationalScopeLevel();

        // If role is 'all', no filtering needed
        if ($roleLevel === 'all') {
            return false;
        }

        // Filter by this level and all levels below it in hierarchy
        $roleLevelIndex = array_search($roleLevel, $hierarchy);
        $checkLevelIndex = array_search($level, $hierarchy);

        return $checkLevelIndex >= $roleLevelIndex;
    }
}
