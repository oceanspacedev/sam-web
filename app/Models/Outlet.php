<?php

namespace App\Models;

use App\Traits\CleansUpMedia;
use App\Traits\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use CleansUpMedia;
    use HasFactory;
    use HasOrganizationalScope;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'last_reset_at' => 'datetime',
        'reset_count_yearly' => 'integer',
        'limit' => 'integer',
        'radius' => 'integer',
    ];

    protected array $mediaCleanupFields = [
        'poto_shop_sign',
        'poto_depan',
        'poto_kiri',
        'poto_kanan',
        'poto_ktp',
        'video',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeFilter(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('kode_outlet', 'like', "%{$search}%")
                ->orWhere('nama_outlet', 'like', "%{$search}%")
                ->orWhere('nama_pemilik_outlet', 'like', "%{$search}%");
        });
    }

    /**
     * Scope query to outlets near a given location using Haversine formula
     *
     * @param  float  $latitude  User's current latitude
     * @param  float  $longitude  User's current longitude
     * @param  int  $limit  Maximum number of results (default: 10)
     */
    public function scopeNearbyLocation(Builder $query, float $latitude, float $longitude, int $limit = 10): Builder
    {
        // Haversine formula for calculating distance in kilometers
        $distanceFormula = "
            (
                6371 * acos(
                    cos(radians(?)) 
                    * cos(radians(CAST(SUBSTRING_INDEX(latlong, ',', 1) AS DECIMAL(10, 8))))
                    * cos(radians(CAST(SUBSTRING_INDEX(latlong, ',', -1) AS DECIMAL(11, 8))) - radians(?))
                    + sin(radians(?))
                    * sin(radians(CAST(SUBSTRING_INDEX(latlong, ',', 1) AS DECIMAL(10, 8))))
                )
            ) AS distance
        ";

        // Check if columns are already selected, if so just add distance, otherwise select all
        $existingColumns = $query->getQuery()->columns;
        if (empty($existingColumns)) {
            $query->selectRaw("*, {$distanceFormula}", [$latitude, $longitude, $latitude]);
        } else {
            $query->selectRaw($distanceFormula, [$latitude, $longitude, $latitude]);
        }

        return $query
            ->whereNotNull('latlong')
            ->where('latlong', '!=', '')
            ->where('latlong', '!=', '-')
            ->orderBy('distance', 'asc')
            ->limit($limit);
    }

    /**
     * Scope query to outlets accessible by the given user based on RBAC.
     * Uses cached organizational IDs from the user to avoid N+1 queries.
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->getOrganizationalIds();
        $scopeLevel = $ids['scope_level'];

        // If full access, no filtering needed
        if ($scopeLevel === 'all') {
            return $query;
        }

        // Apply hierarchical filtering based on scope level
        if (! empty($ids['badanusaha'])) {
            $query->whereIn('badanusaha_id', $ids['badanusaha']);
        }

        if (! empty($ids['divisi'])) {
            $query->whereIn('divisi_id', $ids['divisi']);
        }

        // Apply region and cluster filters for cluster-level scope
        if ($scopeLevel === 'cluster') {
            if (! empty($ids['region'])) {
                $query->whereIn('region_id', $ids['region']);
            }
            if (! empty($ids['cluster'])) {
                $query->whereIn('cluster_id', $ids['cluster']);
            }
        }

        return $query;
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class)->withTrashed();
    }

    public function planvisit(): HasMany
    {
        return $this->hasMany(PlanVisit::class);
    }

    public function visit(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class)->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)->withTrashed();
    }

    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class)->withTrashed();
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class)->withTrashed();
    }
}
