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

    public function scopeFilter(Builder $query, ?string $term = null): Builder
    {
        $term ??= request('search');

        return $query->when($term, function (Builder $query, string $search): void {
            $query->where('nama_lengkap', 'like', "%{$search}%");
        });
    }

    /**
     * Cached organizational IDs to avoid multiple queries
     */
    protected ?array $cachedOrganizationalIds = null;

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

    /**
     * Get outlets accessible to this user based on their organizational assignments.
     * Uses the accessibleTo scope on Outlet model.
     *
     * @deprecated Use Outlet::query()->accessibleTo($user) instead for better clarity
     */
    public function outlet(): HasMany
    {
        // This is a workaround to maintain backward compatibility
        // We create a HasMany relationship but apply the accessibleTo scope
        $instance = new Outlet;
        $query = $instance->newQuery()->accessibleTo($this);

        return new HasMany($query, $this, 'user_id', 'id');
    }

    public function registerTm(): HasMany
    {
        return $this->hasMany(Register::class, 'tm_id');
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
