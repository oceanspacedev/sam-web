<?php

namespace App\Models;

use App\Traits\CleansUpMedia;
use App\Traits\HasOrganizationalScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Register extends Model
{
    use CleansUpMedia;
    use HasFactory;
    use HasOrganizationalScope;
    use SoftDeletes;

    protected $table = 'registers';

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
                $query->where('nama_outlet', 'like', "%{$search}%");
            });
        });
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class)->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)->withTrashed();
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class)->withTrashed();
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class)->withTrashed();
    }

    public function tm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tm_id')->withTrashed();
    }

    public function outlet(): HasOne
    {
        return $this->hasOne(Outlet::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if (! $model->isDirty('status') || $model->status !== 'APPROVED') {
                return;
            }

            $outletQuery = Outlet::query()
                ->select(['id'])
                ->where(function (Builder $query) use ($model): void {
                    $query->where('register_id', $model->id)
                        ->orWhere('kode_outlet', $model->kode_outlet)
                        ->orWhere('kode_outlet', 'LEAD'.$model->id);
                })
                ->orderByRaw('case when register_id = ? then 1 when kode_outlet = ? then 2 when kode_outlet = ? then 3 else 4 end', [
                    $model->id,
                    $model->kode_outlet,
                    'LEAD'.$model->id,
                ]);

            $outlet = $model->outlet()->first() ?? $outletQuery->first();

            $payload = [
                'register_id' => $model->id,
                'kode_outlet' => $model->kode_outlet,
                'nama_outlet' => $model->nama_outlet,
                'alamat_outlet' => $model->alamat_outlet,
                'nama_pemilik_outlet' => $model->nama_pemilik_outlet,
                'nomer_tlp_outlet' => $model->nomer_tlp_outlet,
                'badanusaha_id' => $model->badanusaha_id,
                'divisi_id' => $model->divisi_id,
                'region_id' => $model->region_id,
                'cluster_id' => $model->cluster_id,
                'distric' => $model->distric,
                'poto_shop_sign' => $model->poto_shop_sign,
                'poto_depan' => $model->poto_depan,
                'poto_kanan' => $model->poto_kanan,
                'poto_kiri' => $model->poto_kiri,
                'poto_ktp' => $model->poto_ktp,
                'video' => $model->video,
                'limit' => $model->limit,
                'radius' => $model->radius ?? 100,
                'latlong' => $model->latlong,
                'status_outlet' => 'MAINTAIN',
            ];

            if ($outlet) {
                $outlet->forceFill($payload)->save();
            } else {
                Outlet::create($payload);
            }
        });
    }

    public function formatForAPI(): array
    {
        return [
            'id' => $this->id,
            'kode_outlet' => $this->kode_outlet,
            'nama_outlet' => $this->nama_outlet,
            'alamat_outlet' => $this->alamat_outlet,
            'nama_pemilik_outlet' => $this->nama_pemilik_outlet,
            'nomer_tlp_outlet' => $this->nomer_tlp_outlet,
            'nomer_wakil_outlet' => $this->nomer_wakil_outlet,
            'ktp_outlet' => $this->ktp_outlet,
            'distric' => $this->distric,
            'region' => $this->region ? $this->region->only(['id', 'name']) : null,
            'poto_shop_sign' => $this->poto_shop_sign,
            'poto_depan' => $this->poto_depan,
            'poto_kiri' => $this->poto_kiri,
            'poto_kanan' => $this->poto_kanan,
            'poto_ktp' => $this->poto_ktp,
            'video' => $this->video,
            'oppo' => $this->oppo,
            'vivo' => $this->vivo,
            'realme' => $this->realme,
            'samsung' => $this->samsung,
            'xiaomi' => $this->xiaomi,
            'fl' => $this->fl,
            'latlong' => $this->latlong,
            'limit' => $this->limit,
            'status' => $this->status,
            'rejected_at' => $this->rejected_at ? Carbon::parse($this->rejected_at)->getPreciseTimestamp(3) : null,
            'rejected_by' => $this->rejected_by,
            'confirmed_at' => $this->confirmed_at ? Carbon::parse($this->confirmed_at)->getPreciseTimestamp(3) : null,
            'confirmed_by' => $this->confirmed_by,
            'approved_at' => $this->approved_at ? Carbon::parse($this->approved_at)->getPreciseTimestamp(3) : null,
            'approved_by' => $this->approved_by,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->getPreciseTimestamp(3) : null,
            'created_at' => Carbon::parse($this->created_at)->getPreciseTimestamp(3),
            'updated_at' => Carbon::parse($this->updated_at)->getPreciseTimestamp(3),
            'keterangan' => $this->keterangan,
            'cluster' => $this->cluster ? $this->cluster->only(['id', 'name']) : null,
            'badanusaha' => $this->badanusaha ? $this->badanusaha->only(['id', 'name']) : null,
            'divisi' => $this->divisi ? $this->divisi->only(['id', 'name']) : null,
            'created_by' => $this->created_by,
        ];
    }
}
