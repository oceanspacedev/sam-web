<?php

namespace App\Services;

use App\Exceptions\Api\BadRequestException;
use App\Models\Outlet;
use App\Models\OutletChangeArchive;
use App\Models\Register;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterApprovalService
{
    public const DUPLICATE_BRANCH = 'branch';

    public const DUPLICATE_OVERRIDE = 'override';

    public const DUPLICATE_REJECT = 'reject';

    public function __construct(
        protected SystemSettingResolver $systemSettings,
    ) {}

    /**
     * @param  array<string, mixed>|null  $requestMeta
     * @return array{
     *     register:Register,
     *     outlet:Outlet,
     *     duplicate_resolution:string,
     *     original_kode_outlet:string|null,
     *     final_kode_outlet:string|null,
     *     outlet_action:string,
     *     archive:OutletChangeArchive|null
     * }
     */
    public function approve(
        Register $register,
        ?User $actor,
        string $duplicateResolution = self::DUPLICATE_BRANCH,
        ?array $requestMeta = null,
    ): array {
        $duplicateResolution = $this->normalizeDuplicateResolution($duplicateResolution);

        return DB::transaction(function () use ($register, $actor, $duplicateResolution, $requestMeta): array {
            $register = Register::query()
                ->whereKey($register->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (strtoupper((string) $register->type) !== 'NOO') {
                throw new BadRequestException('Register bukan NOO');
            }

            if ($register->status !== 'CONFIRMED') {
                throw new BadRequestException('Register belum dikonfirmasi');
            }

            if (! $register->kode_outlet) {
                throw new BadRequestException('Kode outlet wajib diisi sebelum approval');
            }

            $originalKodeOutlet = $register->kode_outlet;
            $linkedOutlet = Outlet::query()
                ->where('register_id', $register->id)
                ->lockForUpdate()
                ->first();
            $duplicateOutlet = $this->findDuplicateOutlet($register, $linkedOutlet?->id);
            $outletAction = 'created';
            $archive = null;

            if ($duplicateOutlet && ! $linkedOutlet) {
                if ($duplicateResolution === self::DUPLICATE_REJECT) {
                    throw (new BadRequestException("Kode outlet {$originalKodeOutlet} sudah digunakan di divisi ini."))
                        ->withData([
                            'kode_outlet' => $originalKodeOutlet,
                            'duplicate_outlet_id' => $duplicateOutlet->id,
                            'available_resolutions' => [
                                self::DUPLICATE_BRANCH,
                                self::DUPLICATE_OVERRIDE,
                            ],
                        ]);
                }

                if ($duplicateResolution === self::DUPLICATE_BRANCH) {
                    $register->kode_outlet = $this->previewNextBranchCode($originalKodeOutlet, (int) $register->divisi_id);
                }
            } elseif ($duplicateOutlet && $linkedOutlet && $duplicateResolution === self::DUPLICATE_BRANCH) {
                $register->kode_outlet = $this->previewNextBranchCode($originalKodeOutlet, (int) $register->divisi_id);
            }

            $payload = $this->outletPayload($register);

            Register::withoutEvents(function () use ($register, $actor): void {
                $register->forceFill([
                    'approved_by_id' => $actor?->id,
                    'approved_at' => now(),
                    'status' => 'APPROVED',
                ])->save();
            });

            if ($duplicateOutlet && ! $linkedOutlet && $duplicateResolution === self::DUPLICATE_OVERRIDE) {
                $beforeArchive = $duplicateOutlet->changeArchiveSnapshot();
                $duplicateOutlet->forceFill($payload)->save();
                $duplicateOutlet->refresh();

                $archive = $duplicateOutlet->recordChangeArchive(
                    OutletChangeArchive::ACTION_APPROVAL_OVERRIDE,
                    $actor,
                    $beforeArchive,
                    $duplicateOutlet->changeArchiveSnapshot(),
                    $requestMeta
                );

                $outlet = $duplicateOutlet;
                $outletAction = 'overridden';
            } elseif ($linkedOutlet) {
                $beforeArchive = $linkedOutlet->changeArchiveSnapshot();
                $linkedOutlet->forceFill($payload)->save();
                $linkedOutlet->refresh();

                $archive = $linkedOutlet->recordChangeArchive(
                    OutletChangeArchive::ACTION_UPDATE,
                    $actor,
                    $beforeArchive,
                    $linkedOutlet->changeArchiveSnapshot(),
                    $requestMeta
                );

                $outlet = $linkedOutlet;
                $outletAction = 'updated';
            } else {
                $outlet = Outlet::query()->create($payload);
            }

            return [
                'register' => $register->refresh(),
                'outlet' => $outlet,
                'duplicate_resolution' => $duplicateResolution,
                'original_kode_outlet' => $originalKodeOutlet,
                'final_kode_outlet' => $register->kode_outlet,
                'outlet_action' => $outletAction,
                'archive' => $archive,
            ];
        });
    }

    public function normalizeDuplicateResolution(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return match ($value) {
            self::DUPLICATE_OVERRIDE => self::DUPLICATE_OVERRIDE,
            self::DUPLICATE_REJECT => self::DUPLICATE_REJECT,
            default => self::DUPLICATE_BRANCH,
        };
    }

    protected function findDuplicateOutlet(Register $register, ?int $ignoreOutletId = null): ?Outlet
    {
        return Outlet::query()
            ->where('divisi_id', $register->divisi_id)
            ->where('kode_outlet', $register->kode_outlet)
            ->when($ignoreOutletId, fn ($query) => $query->whereKeyNot($ignoreOutletId))
            ->lockForUpdate()
            ->first();
    }

    public function previewNextBranchCode(string $kodeOutlet, int $divisionId): string
    {
        $baseCode = preg_replace('/-CB\d+$/i', '', trim($kodeOutlet)) ?: trim($kodeOutlet);
        $codes = Outlet::withTrashed()
            ->where('divisi_id', $divisionId)
            ->where(function ($query) use ($baseCode): void {
                $query->where('kode_outlet', $baseCode)
                    ->orWhere('kode_outlet', 'like', $baseCode.'-CB%');
            })
            ->pluck('kode_outlet');

        $next = 1;
        foreach ($codes as $code) {
            if (preg_match('/^'.preg_quote($baseCode, '/').'-CB(\d+)$/i', (string) $code, $matches)) {
                $next = max($next, ((int) $matches[1]) + 1);
            }
        }

        do {
            $candidate = $baseCode.'-CB'.$next;
            $next++;
        } while (
            Outlet::withTrashed()
                ->where('divisi_id', $divisionId)
                ->where('kode_outlet', $candidate)
                ->exists()
        );

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    protected function outletPayload(Register $register): array
    {
        $radius = $this->systemSettings->defaultRegisterRadiusForIds(
            $register->badanusaha_id ? (int) $register->badanusaha_id : null,
            $register->divisi_id ? (int) $register->divisi_id : null,
            $register->region_id ? (int) $register->region_id : null,
            $register->cluster_id ? (int) $register->cluster_id : null,
            100
        );

        return [
            'register_id' => $register->id,
            'kode_outlet' => $register->kode_outlet,
            'badanusaha_id' => $register->badanusaha_id,
            'nama_outlet' => $register->nama_outlet,
            'divisi_id' => $register->divisi_id,
            'alamat_outlet' => $register->alamat_outlet,
            'nama_pemilik_outlet' => $register->nama_pemilik_outlet,
            'nomer_tlp_outlet' => $register->nomer_tlp_outlet,
            'distric' => $register->distric,
            'region_id' => $register->region_id,
            'cluster_id' => $register->cluster_id,
            'poto_shop_sign' => $register->poto_shop_sign,
            'poto_depan' => $register->poto_depan,
            'poto_kanan' => $register->poto_kanan,
            'poto_kiri' => $register->poto_kiri,
            'poto_ktp' => $register->poto_ktp,
            'video' => $register->video,
            'radius' => $radius,
            'latlong' => $register->latlong,
            'status_outlet' => 'MAINTAIN',
            'limit' => $register->limit,
        ];
    }
}
