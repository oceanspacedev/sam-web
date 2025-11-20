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
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    public function canImpersonate()
    {
        return $this->nama_lengkap === 'APP DEVELOPER';
    }

    public function getFilamentName(): string
    {
        return "{$this->nama_lengkap}";
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) ($this->role?->can_access_web);
    }

    public function scopeFilter(Builder $query, ?string $term = null): Builder
    {
        $term ??= request('search');

        return $query->when($term, function (Builder $query, string $search): void {
            $query->where('nama_lengkap', 'like', "%{$search}%");
        });
    }

    /**
     * Get outlets accessible to this user based on their organizational assignments.
     * Uses pivot tables to determine access via badanusaha, divisi, region, and cluster.
     */
    public function outlet(): HasMany
    {
        $role = $this->role;
        $scopeLevel = $role->organizational_scope_level ?? 'cluster';

        // Get user's organizational IDs from pivot tables
        $badanUsahaIds = $this->badanUsahas()->pluck('badan_usahas.id')->toArray();
        $divisiIds = $this->divisis()->pluck('divisions.id')->toArray();
        $regionIds = $this->regions()->pluck('regions.id')->toArray();
        $clusterIds = $this->clusters()->pluck('clusters.id')->toArray();

        // Start with base hasMany relationship
        $relation = $this->hasMany(Outlet::class, 'id', 'id');

        // HACK: Remove the default foreign key constraint added by HasMany
        // Standard HasMany adds "where outlets.id = user.id" which is incorrect for this virtual relation.
        // We remove this specific constraint to allow our RBAC logic to define the scope.
        $query = $relation->getQuery();
        $baseQuery = $query->getQuery();

        $baseQuery->wheres = array_values(array_filter($baseQuery->wheres, function ($where) {
            // Remove the constraint that matches foreign key (outlets.id)
            return ! ($where['type'] === 'Basic' &&
                str_ends_with($where['column'], '.id') &&
                $where['operator'] === '=');
        }));

        // Also remove the binding for the removed constraint
        // The binding value is $this->id
        $bindings = $baseQuery->getRawBindings()['where'];
        $keyToRemove = array_search($this->id, $bindings);
        if ($keyToRemove !== false) {
            unset($bindings[$keyToRemove]);
            $baseQuery->setBindings(array_values($bindings), 'where');
        }

        // Apply RBAC constraints using where callback
        $relation->where(function ($query) use ($scopeLevel, $badanUsahaIds, $divisiIds, $regionIds, $clusterIds) {
            // If full access, no filtering needed
            if ($scopeLevel === 'all') {
                return;
            }

            // Apply badan usaha filter
            if (! empty($badanUsahaIds)) {
                $query->whereIn('badanusaha_id', $badanUsahaIds);
            }

            // Apply divisi filter
            if (! empty($divisiIds)) {
                $query->whereIn('divisi_id', $divisiIds);
            }

            // Apply region and cluster filters for cluster-level scope
            if ($scopeLevel === 'cluster') {
                if (! empty($regionIds)) {
                    $query->whereIn('region_id', $regionIds);
                }
                if (! empty($clusterIds)) {
                    $query->whereIn('cluster_id', $clusterIds);
                }
            }
        });

        return $relation;
    }

    public function registerTm(): HasMany
    {
        return $this->hasMany(Register::class, 'tm_id');
    }

    /**
     * @deprecated Use registerTm() instead.
     */
    public function nootm(): HasMany
    {
        return $this->registerTm();
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
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id', 'role_id');
    }

    public function tm(): BelongsTo|Builder
    {
        return $this->belongsTo(User::class, 'tm_id')->withTrashed();
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
        'two_factor_recovery_codes',
        'two_factor_secret',
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

    public function formatForAPI(): array
    {
        return [
            'username' => $this->username,
            'nama_lengkap' => $this->nama_lengkap,
            // Use many-to-many pivot relations
            'badanusaha' => $this->badanUsahas->first() ? [
                'id' => $this->badanUsahas->first()->id,
                'name' => $this->badanUsahas->first()->name,
            ] : null,
            'divisi' => $this->divisis->first() ? [
                'id' => $this->divisis->first()->id,
                'name' => $this->divisis->first()->name,
            ] : null,
            'region' => $this->regions->first() ? [
                'id' => $this->regions->first()->id,
                'name' => $this->regions->first()->name,
            ] : null,
            'cluster' => $this->clusters->first() ? [
                'id' => $this->clusters->first()->id,
                'name' => $this->clusters->first()->name,
            ] : null,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ] : null,
            'id_notif' => $this->id_notif,
        ];
    }

    /**
     * Override Jetstream's defaultProfilePhotoUrl to use nama_lengkap instead of name
     * Fixes deprecation warning when name field is null
     */
    protected function defaultProfilePhotoUrl(): string
    {
        $name = $this->nama_lengkap ?? $this->username ?? 'User';

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }
}
