<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        return $this->role->can_access_web == 1;
    }

    public function scopeFilter($query)
    {
        if (request('search')) {
            $query->where('nama_lengkap', 'like', '%'.request('search').'%');
        }
    }

    public function outlet(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function nootm(): HasMany
    {
        return $this->hasMany(Noo::class, 'tm_id');
    }

    public function visit(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function planvisit(): HasMany
    {
        return $this->hasMany(Planvisit::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    public function cluster2(): BelongsTo
    {
        return $this->belongsTo(Cluster::class, 'cluster_id2');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permissions()
    {
        return $this->role->permissions();
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class);
    }

    public function tm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tm_id');
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

    public function formatForAPI()
    {
        return [
            'username' => $this->username,
            'nama_lengkap' => $this->nama_lengkap,
            'region' => $this->region ? [
                'id' => $this->region->id,
                'name' => $this->region->name,
            ] : null,
            'cluster' => $this->cluster ? [
                'id' => $this->cluster->id,
                'name' => $this->cluster->name,
            ] : null,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ] : null,
            'divisi' => $this->divisi ? [
                'id' => $this->divisi->id,
                'name' => $this->divisi->name,
            ] : null,
            'badanusaha' => $this->badanusaha ? [
                'id' => $this->badanusaha->id,
                'name' => $this->badanusaha->name,
            ] : null,
            'id_notif' => $this->id_notif,
        ];
    }
}
