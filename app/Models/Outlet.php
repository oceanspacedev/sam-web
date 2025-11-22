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

    protected array $mediaCleanupFields = [
        'poto_shop_sign',
        'poto_depan',
        'poto_kiri',
        'poto_kanan',
        'poto_ktp',
        'video',
    ];

    public function scopeFilter(Builder $query, ?string $term = null): Builder
    {
        $term ??= request('search');

        return $query->when($term, function (Builder $query, string $search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('nama_outlet', 'like', "%{$search}%")
                    ->orWhere('kode_outlet', 'like', "%{$search}%");
            });
        });
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
        if (!empty($ids['badanusaha'])) {
            $query->whereIn('badanusaha_id', $ids['badanusaha']);
        }

        if (!empty($ids['divisi'])) {
            $query->whereIn('divisi_id', $ids['divisi']);
        }

        // Apply region and cluster filters for cluster-level scope
        if ($scopeLevel === 'cluster') {
            if (!empty($ids['region'])) {
                $query->whereIn('region_id', $ids['region']);
            }
            if (!empty($ids['cluster'])) {
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



    /**
     * Dynamic scope to attach territory users based on organizational_scope_level
     * Replaces hardcoded scopeWithTmAscDsf
     */
    public function scopeWithTerritory(Builder $query): Builder
    {
        return $query
            ->leftJoin('users as region_users', function ($join) {
                $join->on('region_users.divisi_id', '=', 'outlets.divisi_id')
                    ->on('region_users.region_id', '=', 'outlets.region_id')
                    ->join('roles as region_role', 'region_users.role_id', '=', 'region_role.id')
                    ->where('region_role.organizational_scope_level', 'region');
            })
            ->leftJoin('users as cluster_users', function ($join) {
                $join->on('cluster_users.divisi_id', '=', 'outlets.divisi_id')
                    ->on('cluster_users.region_id', '=', 'outlets.region_id')
                    ->on('cluster_users.cluster_id', '=', 'outlets.cluster_id')
                    ->join('roles as cluster_role', 'cluster_users.role_id', '=', 'cluster_role.id')
                    ->where('cluster_role.organizational_scope_level', 'cluster');
            })
            ->select(
                'outlets.*',
                'region_users.nama_lengkap as region_manager_name',
                'cluster_users.nama_lengkap as cluster_manager_name'
            );
    }

    public function formatForAPI(): array
    {
        $this->loadMissing(['badanusaha', 'region', 'cluster', 'divisi']);

        return [
            'id' => $this->id,
            'kode_outlet' => $this->kode_outlet,
            'nama_outlet' => $this->nama_outlet,
            'alamat_outlet' => str_replace(["\r", "\n"], ' ', $this->alamat_outlet),
            'nama_pemilik_outlet' => $this->nama_pemilik_outlet,
            'nomer_tlp_outlet' => $this->nomer_tlp_outlet,
            'distric' => $this->distric,
            'badanusaha' => $this->badanusaha ? $this->badanusaha->only(['id', 'name']) : null,
            'poto_shop_sign' => $this->poto_shop_sign,
            'poto_depan' => $this->poto_depan,
            'poto_kiri' => $this->poto_kiri,
            'poto_kanan' => $this->poto_kanan,
            'poto_ktp' => $this->poto_ktp,
            'video' => $this->video,
            'limit' => $this->limit,
            'radius' => $this->radius,
            'latlong' => $this->latlong,
            'status_outlet' => $this->status_outlet,
            'region' => $this->region ? $this->region->only(['id', 'name']) : null,
            'cluster' => $this->cluster ? $this->cluster->only(['id', 'name']) : null,
            'divisi' => $this->divisi ? $this->divisi->only(['id', 'name']) : null,
        ];
    }
}
