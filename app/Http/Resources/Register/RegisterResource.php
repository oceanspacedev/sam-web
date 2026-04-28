<?php

namespace App\Http\Resources\Register;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full Register resource with all fields including workflow state.
 * Use this for detail views and complete register information.
 *
 * @property int $id
 * @property string|null $kode_outlet
 * @property string $nama_outlet
 * @property string $alamat_outlet
 * @property string $nama_pemilik_outlet
 * @property string $nomer_tlp_outlet
 * @property string|null $nomer_wakil_outlet
 * @property string $ktp_outlet
 * @property string $distric
 * @property string $poto_shop_sign
 * @property string $poto_depan
 * @property string $poto_kiri
 * @property string $poto_kanan
 * @property string $poto_ktp
 * @property string|null $video
 * @property string $latlong
 * @property int|null $limit
 * @property string|null $status
 * @property string|null $type
 * @property string|null $keterangan
 * @property int $created_by_id
 * @property string|null $rejected_at
 * @property int|null $rejected_by_id
 * @property string|null $confirmed_at
 * @property int|null $confirmed_by_id
 * @property string|null $approved_at
 * @property int|null $approved_by_id
 * @property string $created_at
 * @property string $updated_at
 */
class RegisterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $systemSettings = $request->attributes->get('system_setting_resolver');
        if (! $systemSettings instanceof \App\Services\SystemSettingResolver) {
            $systemSettings = app(\App\Services\SystemSettingResolver::class);
            $request->attributes->set('system_setting_resolver', $systemSettings);
        }

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
            'poto_shop_sign' => $this->poto_shop_sign,
            'poto_depan' => $this->poto_depan,
            'poto_kiri' => $this->poto_kiri,
            'poto_kanan' => $this->poto_kanan,
            'poto_ktp' => $this->poto_ktp,
            'video' => $this->video,
            'latlong' => $this->latlong,
            'radius' => $systemSettings->defaultRegisterRadiusForIds(
                $this->badanusaha_id ? (int) $this->badanusaha_id : null,
                $this->divisi_id ? (int) $this->divisi_id : null,
                $this->region_id ? (int) $this->region_id : null,
                $this->cluster_id ? (int) $this->cluster_id : null,
            ),
            'oppo' => $this->oppo,
            'vivo' => $this->vivo,
            'realme' => $this->realme,
            'samsung' => $this->samsung,
            'xiaomi' => $this->xiaomi,
            'fl' => $this->fl,
            'limit' => $this->limit,
            'status' => $this->status,
            'type' => $this->type,
            'keterangan' => $this->keterangan,
            'created_by_id' => $this->created_by_id,
            'created_by' => $this->createdBy?->nama_lengkap,
            'rejected_at' => $this->rejected_at ? Carbon::parse($this->rejected_at)->getPreciseTimestamp(3) : null,
            'rejected_by_id' => $this->rejected_by_id,
            'rejected_by' => $this->rejectedBy?->nama_lengkap,
            'confirmed_at' => $this->confirmed_at ? Carbon::parse($this->confirmed_at)->getPreciseTimestamp(3) : null,
            'confirmed_by_id' => $this->confirmed_by_id,
            'confirmed_by' => $this->confirmedBy?->nama_lengkap,
            'approved_at' => $this->approved_at ? Carbon::parse($this->approved_at)->getPreciseTimestamp(3) : null,
            'approved_by_id' => $this->approved_by_id,
            'approved_by' => $this->approvedBy?->nama_lengkap,
            'created_at' => Carbon::parse($this->created_at)->getPreciseTimestamp(3),
            'updated_at' => Carbon::parse($this->updated_at)->getPreciseTimestamp(3),

            // Relationships
            'region' => $this->whenLoaded('region', function () {
                return $this->region ? $this->region->only(['id', 'name']) : null;
            }),
            'cluster' => $this->whenLoaded('cluster', function () {
                return $this->cluster ? $this->cluster->only(['id', 'name']) : null;
            }),
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return $this->badanusaha ? $this->badanusaha->only(['id', 'name']) : null;
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return $this->divisi ? $this->divisi->only(['id', 'name']) : null;
            }),

            // SDUI: Actions based on user permissions
            'actions' => $this->getActions($request),
        ];
    }

    /**
     * Get available actions for this register based on user permissions.
     *
     * @return array<string, bool>
     */
    protected function getActions(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [
                'can_confirm' => false,
                'can_reject' => false,
                'can_approve' => false,
            ];
        }

        return [
            'can_confirm' => $user->can('confirm', $this->resource),
            'can_reject' => $user->can('reject', $this->resource),
            'can_approve' => $user->can('approve', $this->resource),
        ];
    }
}
