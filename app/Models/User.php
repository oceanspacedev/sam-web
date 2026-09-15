<?php

namespace App\Models;

use App\Support\StorageDisk;
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
        // Check if user has impersonation permission (either via Gate/Spatie or direct/role permissions)
        return $this->can('Impersonate')
            || $this->can('impersonate')
            || $this->permissions->contains('name', 'Impersonate')
            || $this->permissions->contains('name', 'impersonate');
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
     * Cached effective OR grants derived from pivots.
     *
     * @var array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}|null
     */
    protected ?array $cachedEffectiveGrants = null;

    /**
     * Cached downward-expanded accessible IDs from effective grants.
     *
     * @var array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}|null
     */
    protected ?array $cachedExpandedOrganizationalIds = null;

    /**
     * Scope query berdasarkan organizational hierarchy user.
     * Uses many-to-many pivot tables for User model.
     * Visibility is the OR of overlapping assignments under effective grants.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role->hasFullAccess()) {
            return $query;
        }

        $grants = $user->getEffectiveOrganizationalGrants();

        if (\App\Support\OrganizationalEffectiveGrants::isEmpty($grants)) {
            return $query->whereRaw('1 = 0');
        }

        $accessible = $user->getExpandedOrganizationalIds();

        return $query->where(function (Builder $scope) use ($accessible): void {
            $applied = false;

            if ($accessible['badanusaha'] !== []) {
                $scope->whereHas('badanUsahas', function (Builder $q) use ($accessible): void {
                    $q->whereIn('badan_usahas.id', $accessible['badanusaha']);
                });
                $applied = true;
            }

            if ($accessible['divisi'] !== []) {
                $method = $applied ? 'orWhereHas' : 'whereHas';
                $scope->{$method}('divisis', function (Builder $q) use ($accessible): void {
                    $q->whereIn('divisions.id', $accessible['divisi']);
                });
                $applied = true;
            }

            if ($accessible['region'] !== []) {
                $method = $applied ? 'orWhereHas' : 'whereHas';
                $scope->{$method}('regions', function (Builder $q) use ($accessible): void {
                    $q->whereIn('regions.id', $accessible['region']);
                });
                $applied = true;
            }

            if ($accessible['cluster'] !== []) {
                $method = $applied ? 'orWhereHas' : 'whereHas';
                $scope->{$method}('clusters', function (Builder $q) use ($accessible): void {
                    $q->whereIn('clusters.id', $accessible['cluster']);
                });
                $applied = true;
            }

            if (! $applied) {
                $scope->whereRaw('1 = 0');
            }
        });
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
        $scopeLevel = strtolower($role?->organizational_scope_level ?? 'cluster');

        $this->cachedOrganizationalIds = [
            'badanusaha' => $this->badanUsahas()->pluck('badan_usahas.id')->toArray(),
            'divisi' => $this->divisis()->pluck('divisions.id')->toArray(),
            'region' => $this->regions()->pluck('regions.id')->toArray(),
            'cluster' => $this->clusters()->pluck('clusters.id')->toArray(),
            'scope_level' => $scopeLevel,
        ];

        return $this->cachedOrganizationalIds;
    }

    /**
     * @return array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}
     */
    public function getEffectiveOrganizationalGrants(): array
    {
        if ($this->cachedEffectiveGrants !== null) {
            return $this->cachedEffectiveGrants;
        }

        return $this->cachedEffectiveGrants = \App\Support\OrganizationalEffectiveGrants::forUser($this);
    }

    /**
     * @return array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}
     */
    public function getExpandedOrganizationalIds(): array
    {
        if ($this->cachedExpandedOrganizationalIds !== null) {
            return $this->cachedExpandedOrganizationalIds;
        }

        return $this->cachedExpandedOrganizationalIds = \App\Support\OrganizationalEffectiveGrants::expandAccessibleIds(
            $this->getEffectiveOrganizationalGrants(),
        );
    }

    public function forgetOrganizationalIdsCache(): void
    {
        $this->cachedOrganizationalIds = null;
        $this->cachedEffectiveGrants = null;
        $this->cachedExpandedOrganizationalIds = null;
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'whatsapp_verified_at' => 'datetime',
        ];
    }

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
        $url = StorageDisk::url($this->profile_photo_path);

        if (is_string($url) && $url !== '') {
            return $url;
        }

        $name = $this->nama_lengkap ?? $this->username ?? 'User';

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }
}
