<?php

namespace App\Observers;

use App\Models\Outlet;
use App\Models\Register;
use Illuminate\Database\Eloquent\Builder;

class RegisterObserver
{
    /**
     * Handle the Register "updating" event.
     */
    public function updating(Register $model): void
    {
        if (! $model->isDirty('status') || $model->status !== 'APPROVED') {
            return;
        }

        $outletQuery = Outlet::query()
            ->select(['id'])
            ->where('divisi_id', $model->divisi_id)
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
    }
}
