<?php

namespace App\Support;

use App\Models\OutletChangeArchive;
use Carbon\Carbon;

class OutletChangeArchivePresenter
{
    private const FIELD_LABELS = [
        'kode_outlet' => 'Kode Outlet',
        'nama_outlet' => 'Nama Outlet',
        'alamat_outlet' => 'Alamat Outlet',
        'nama_pemilik_outlet' => 'Nama Pemilik Outlet',
        'nomer_tlp_outlet' => 'Nomor Telepon Outlet',
        'badanusaha_id' => 'Badan Usaha',
        'divisi_id' => 'Divisi',
        'region_id' => 'Region',
        'cluster_id' => 'Cluster',
        'distric' => 'Distrik',
        'poto_shop_sign' => 'Foto Tanda Toko',
        'poto_depan' => 'Foto Depan',
        'poto_kiri' => 'Foto Kiri',
        'poto_kanan' => 'Foto Kanan',
        'poto_ktp' => 'Foto KTP Pemilik',
        'video' => 'Video Toko',
        'limit' => 'Limit',
        'radius' => 'Radius',
        'latlong' => 'Latitude/Longitude',
        'status_outlet' => 'Status Outlet',
        'register_id' => 'Register',
        'last_reset_at' => 'Terakhir Reset',
        'reset_count_yearly' => 'Jumlah Reset Tahunan',
    ];

    private const MEDIA_FIELDS = [
        'poto_shop_sign',
        'poto_depan',
        'poto_kiri',
        'poto_kanan',
        'poto_ktp',
        'video',
    ];

    private const ORG_FIELDS = [
        'badanusaha_id' => 'badanUsahaLabel',
        'divisi_id' => 'divisionLabel',
        'region_id' => 'regionLabel',
        'cluster_id' => 'clusterLabel',
    ];

    /**
     * @return array<string, string>
     */
    public static function fieldLabels(): array
    {
        return self::FIELD_LABELS;
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    public static function actionLabel(?string $action): string
    {
        return match ($action) {
            OutletChangeArchive::ACTION_RESET_DATA => 'Reset Data',
            OutletChangeArchive::ACTION_RESET_LOCATION => 'Reset Lokasi',
            OutletChangeArchive::ACTION_UPDATE => 'Edit Manual',
            OutletChangeArchive::ACTION_RESTORE => 'Restore',
            OutletChangeArchive::ACTION_APPROVAL_OVERRIDE => 'Override Approval',
            default => (string) $action,
        };
    }

    public static function actionColor(?string $action): string
    {
        return match ($action) {
            OutletChangeArchive::ACTION_UPDATE => 'warning',
            OutletChangeArchive::ACTION_RESET_DATA, OutletChangeArchive::ACTION_RESET_LOCATION => 'danger',
            OutletChangeArchive::ACTION_RESTORE => 'success',
            OutletChangeArchive::ACTION_APPROVAL_OVERRIDE => 'info',
            default => 'gray',
        };
    }

    public static function formatValue(string $field, mixed $value): string
    {
        if (self::isEmptyValue($value)) {
            return 'Kosong';
        }

        if (in_array($field, self::MEDIA_FIELDS, true)) {
            return 'Ada';
        }

        if (array_key_exists($field, self::ORG_FIELDS)) {
            $method = self::ORG_FIELDS[$field];
            $label = OrganizationalHierarchyOptions::{$method}($value, activeOnly: false);

            return filled($label) ? $label : (string) $value;
        }

        if ($field === 'register_id') {
            return 'Register #'.(string) $value;
        }

        if ($field === 'last_reset_at') {
            return Carbon::parse($value)->format('d M Y H:i');
        }

        if ($field === 'reset_count_yearly') {
            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * @param  array<int, string>|null  $changedFields
     */
    public static function summary(?array $changedFields): string
    {
        $fields = array_values(array_filter($changedFields ?? []));

        if ($fields === []) {
            return '-';
        }

        $labels = array_map(fn (string $field): string => self::fieldLabel($field), $fields);

        if (count($labels) <= 3) {
            return implode(', ', $labels);
        }

        $visible = array_slice($labels, 0, 2);
        $remaining = count($labels) - 2;

        return implode(', ', $visible).", +{$remaining} lainnya";
    }

    /**
     * @return array<int, array{label: string, old: string, new: string}>
     */
    public static function diffRows(OutletChangeArchive $archive): array
    {
        $oldValues = $archive->old_values ?? [];
        $newValues = $archive->new_values ?? [];
        $rows = [];

        foreach ($archive->changed_fields ?? [] as $field) {
            $rows[] = [
                'label' => self::fieldLabel($field),
                'old' => self::formatValue($field, $oldValues[$field] ?? null),
                'new' => self::formatValue($field, $newValues[$field] ?? null),
            ];
        }

        return $rows;
    }

    public static function statusLabel(OutletChangeArchive $archive): string
    {
        $archive->loadMissing('restoredBy');

        if (blank($archive->restored_at)) {
            return 'Aktif';
        }

        $status = 'Direstore · '.$archive->restored_at->format('d M Y H:i');

        if (filled($archive->restoredBy?->nama_lengkap)) {
            $status .= ' · '.$archive->restoredBy->nama_lengkap;
        }

        return $status;
    }

    private static function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === '-';
    }
}
