<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Outlet extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    public function scopeFilter($query)
    {
        if (request('search')) {
            $query->where('nama_outlet', 'like', '%'.request('search').'%')
                ->orWhere('kode_outlet', 'like', '%'.request('search').'%');
        }
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

    public function scopeWithTmAscDsf(Builder $query): Builder
    {
        return $query
            ->leftJoin('users as tm_users', function ($join) {
                $join->on('tm_users.divisi_id', '=', 'outlets.divisi_id')
                    ->on('tm_users.region_id', '=', 'outlets.region_id')
                    ->where('tm_users.role_id', 2);
            })
            ->leftJoin('users as asc_users', function ($join) {
                $join->on('asc_users.divisi_id', '=', 'outlets.divisi_id')
                    ->on('asc_users.region_id', '=', 'outlets.region_id')
                    ->where('asc_users.role_id', 2);
            })
            ->leftJoin('users as dsf_users', function ($join) {
                $join->on('dsf_users.divisi_id', '=', 'outlets.divisi_id')
                    ->on('dsf_users.region_id', '=', 'outlets.region_id')
                    ->on('dsf_users.cluster_id', '=', 'outlets.cluster_id')
                    ->where('dsf_users.role_id', 3);
            })
            ->select('outlets.*',
                'tm_users.nama_lengkap as tm_nama_lengkap',
                'asc_users.nama_lengkap as asc_nama_lengkap',
                'dsf_users.nama_lengkap as dsf_nama_lengkap'
            );
    }

    protected static function booted()
    {
        static::updating(function ($model) {
            $fields = [
                'poto_shop_sign',
                'poto_depan',
                'poto_kiri',
                'poto_kanan',
                'poto_ktp',
                'video',
            ];

            foreach ($fields as $field) {
                if ($model->isDirty($field) && $model->getOriginal($field)) {
                    $oldFile = $model->getOriginal($field);
                    if (Storage::disk('public')->exists($oldFile)) {
                        Storage::disk('public')->delete($oldFile);
                    }
                }
            }
        });
    }

    public function formatForAPI()
    {
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
