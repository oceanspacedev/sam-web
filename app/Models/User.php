<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    public function canImpersonate(): bool
    {
        // Check if user has impersonation permission via their role
        return $this->permissions->contains('name', 'impersonate');
    }

    public function getFilamentName(): string
    {
        return "{$this->nama_lengkap}";
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) ($this->role?->can_access_web);
    }

    /**
     * Cached organizational IDs to avoid multiple queries
     */
    protected ?array $cachedOrganizationalIds = null;

    /**
     * Scope query berdasarkan organizational hierarchy user.
     * Uses many-to-many pivot tables for User model.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Check if user has a role
        if (! $user->role) {
            return $query->whereRaw('1 = 0'); // Return empty result
        }

        // If role has full access, no filtering needed
        if ($user->role->hasFullAccess()) {
            return $query;
        }

        // Use cached organizational IDs from user to avoid N+1 queries
        $ids = $user->getOrganizationalIds();
        $scopeLevel = $ids['scope_level'];

        // Apply hierarchical filtering based on scope level using pivot tables
        switch ($scopeLevel) {
            case 'badanusaha':
                if (! empty($ids['badanusaha'])) {
                    $query->whereHas('badanUsahas', function ($q) use ($ids) {
                        $q->whereIn('badan_usahas.id', $ids['badanusaha']);
                    });
                }
                break;

            case 'divisi':
                if (! empty($ids['badanusaha'])) {
                    $query->whereHas('badanUsahas', function ($q) use ($ids) {
                        $q->whereIn('badan_usahas.id', $ids['badanusaha']);
                    });
                }
                if (! empty($ids['divisi'])) {
                    $query->whereHas('divisis', function ($q) use ($ids) {
                        $q->whereIn('divisions.id', $ids['divisi']);
                    });
                }
                break;

            case 'region':
                if (! empty($ids['badanusaha'])) {
                    $query->whereHas('badanUsahas', function ($q) use ($ids) {
                        $q->whereIn('badan_usahas.id', $ids['badanusaha']);
                    });
                }
                if (! empty($ids['divisi'])) {
                    $query->whereHas('divisis', function ($q) use ($ids) {
                        $q->whereIn('divisions.id', $ids['divisi']);
                    });
                }
                if (! empty($ids['region'])) {
                    $query->whereHas('regions', function ($q) use ($ids) {
                        $q->whereIn('regions.id', $ids['region']);
                    });
                }
                break;

            case 'cluster':
                if (! empty($ids['badanusaha'])) {
                    $query->whereHas('badanUsahas', function ($q) use ($ids) {
                        $q->whereIn('badan_usahas.id', $ids['badanusaha']);
                    });
                }
                if (! empty($ids['divisi'])) {
                    $query->whereHas('divisis', function ($q) use ($ids) {
                        $q->whereIn('divisions.id', $ids['divisi']);
                    });
                }
                if (! empty($ids['region'])) {
                    $query->whereHas('regions', function ($q) use ($ids) {
                        $q->whereIn('regions.id', $ids['region']);
                    });
                }
                if (! empty($ids['cluster'])) {
                    $query->whereHas('clusters', function ($q) use ($ids) {
                        $q->whereIn('clusters.id', $ids['cluster']);
                    });
                }
                break;
        }

        return $query;
    }

    /**
     * Get user's organizational IDs from pivot tables (cached).
     * Returns array with keys: badanusaha, divisi, region, cluster, scope_level
     */
    public function getOrganizationalIds(): array
    {
        if ($this->cachedOrganizationalIds !== null) {
            return $this->cachedOrganizationalIds;
        }

        $role = $this->role;
        $scopeLevel = $role?->organizational_scope_level ?? 'cluster';

        $this->cachedOrganizationalIds = [
            'badanusaha' => $this->badanUsahas()->pluck('badan_usahas.id')->toArray(),
            'divisi' => $this->divisis()->pluck('divisions.id')->toArray(),
            'region' => $this->regions()->pluck('regions.id')->toArray(),
            'cluster' => $this->clusters()->pluck('clusters.id')->toArray(),
            'scope_level' => $scopeLevel,
        ];

        return $this->cachedOrganizationalIds;
    }

    public function outlets(): HasMany
    {
        $query = (new Outlet)->newQuery()->visibleTo($this);

        return new HasMany($query, $this, 'user_id', 'id');
    }

    public function registerTm(): HasMany
    {
        return $this->hasMany(Register::class, 'tm_id');
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class, 'created_by_id');
    }

    public function visit(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function planvisit(): HasMany
    {
        return $this->hasMany(PlanVisit::class);
    }

    // === New Many-to-Many Organizational Relations ===

    public function badanUsahas(): BelongsToMany
    {
        return $this->belongsToMany(BadanUsaha::class, 'user_badan_usaha', 'user_id', 'badanusaha_id')
            ->withTimestamps();
    }

    public function divisis(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'user_divisi', 'user_id', 'divisi_id')
            ->withTimestamps();
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'user_regions', 'user_id', 'region_id')
            ->withTimestamps();
    }

    public function clusters(): BelongsToMany
    {
        return $this->belongsToMany(Cluster::class, 'user_clusters', 'user_id', 'cluster_id')
            ->withTimestamps();
    }

    public function role(): BelongsTo|Builder
    {
        return $this->belongsTo(Role::class)->withTrashed();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions', 'role_id', 'permission_id', 'role_id');
    }

    public function tm(): BelongsTo|Builder
    {
        return $this->belongsTo(User::class, 'tm_id')->withTrashed();
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(User::class, 'tm_id');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the URL to the user's profile photo.
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo_path) {
            return asset('storage/'.$this->profile_photo_path);
        }

        $name = $this->nama_lengkap ?? $this->username ?? 'User';

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }
}
