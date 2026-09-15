<?php

namespace App\Http\Resources\Outlet;

use App\Support\OrganizationalName;
use App\Support\StorageDisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full Outlet resource with all fields including media files.
 * Use this for detail views and complete outlet information.
 *
 * @property int $id
 * @property string $kode_outlet
 * @property string $nama_outlet
 * @property string $alamat_outlet
 * @property string $nama_pemilik_outlet
 * @property string $nomer_tlp_outlet
 * @property string $distric
 * @property string $poto_shop_sign
 * @property string $poto_depan
 * @property string $poto_kiri
 * @property string $poto_kanan
 * @property string $poto_ktp
 * @property string|null $video
 * @property int $limit
 * @property int $radius
 * @property string $latlong
 * @property \Illuminate\Support\Carbon|null $last_reset_at
 * @property int $reset_count_yearly
 * @property string $status_outlet
 */
class OutletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_outlet' => $this->kode_outlet,
            'nama_outlet' => $this->nama_outlet,
            'alamat_outlet' => str_replace(["\r", "\n"], ' ', $this->alamat_outlet),
            'nama_pemilik_outlet' => $this->nama_pemilik_outlet,
            'nomer_tlp_outlet' => $this->nomer_tlp_outlet,
            'distric' => $this->distric,
            'poto_shop_sign' => $this->poto_shop_sign,
            'poto_depan' => $this->poto_depan,
            'poto_kiri' => $this->poto_kiri,
            'poto_kanan' => $this->poto_kanan,
            'poto_ktp' => $this->poto_ktp,
            'video' => $this->video,
            'poto_shop_sign_url' => StorageDisk::url($this->poto_shop_sign),
            'poto_depan_url' => StorageDisk::url($this->poto_depan),
            'poto_kiri_url' => StorageDisk::url($this->poto_kiri),
            'poto_kanan_url' => StorageDisk::url($this->poto_kanan),
            'poto_ktp_url' => StorageDisk::url($this->poto_ktp),
            'video_url' => StorageDisk::url($this->video),
            'limit' => $this->limit,
            'radius' => $this->radius,
            'latlong' => $this->latlong,
            'last_reset_at' => $this->last_reset_at,
            'reset_count_yearly' => $this->reset_count_yearly,
            'status_outlet' => $this->status_outlet,
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return OrganizationalName::resource($this->badanusaha);
            }),
            'region' => $this->whenLoaded('region', function () {
                return OrganizationalName::resource($this->region);
            }),
            'cluster' => $this->whenLoaded('cluster', function () {
                return OrganizationalName::resource($this->cluster);
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return OrganizationalName::resource($this->divisi);
            }),

            // SDUI: Actions based on user permissions
            'actions' => $this->getActions($request),
        ];
    }

    /**
     * Get available actions for this outlet based on user permissions.
     *
     * @return array<string, bool>
     */
    protected function getActions(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [
                'can_reset' => false,
                'can_reset_location' => false,
                'can_delete' => false,
            ];
        }

        return [
            'can_reset' => $user->can('Reset:Outlet'),
            'can_reset_location' => $user->can('ResetLocation:Outlet'),
            'can_delete' => $user->can('Delete:Outlet'),
        ];
    }
}
