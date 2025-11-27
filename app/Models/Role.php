<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
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

    /**
     * Get the organizational scope level for this role
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_role_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_role_id');
    }

    public function user(): HasMany
    {
        return $this->hasMany(User::class);
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
}
