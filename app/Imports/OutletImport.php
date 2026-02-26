<?php

namespace App\Imports;

use App\Exports\Outlet\OutletImportErrorsExport;
use App\Jobs\SendImportNotification;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Region;
use App\Support\StorageDisk;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Row;
use Throwable;

class OutletImport implements OnEachRow, ShouldQueue, WithChunkReading, WithEvents, WithHeadingRow
{
    private const SUMMARY_TTL_MINUTES = 120;

    private const ERROR_SAMPLE_LIMIT = 20;

    /**
     * Kolom untuk mode CREATE (sesuai template OutletCreatedSheet)
     */
    private const EXPORT_CREATE_FIELDS = [
        'badan_usaha',
        'divisi',
        'region',
        'cluster',
        'kode_outlet',
        'nama_outlet',
        'alamat_outlet',
        'distric',
        'limit',
    ];

    /**
     * Kolom untuk mode UPDATE (sesuai template OutletUpdatedSheet)
     */
    private const EXPORT_UPDATE_BASE_FIELDS = [
        'badan_usaha',
        'divisi',
        'region',
        'cluster',
        'kode_outlet',
        'nama_outlet',
        'nama_pemilik_outlet',
        'nomer_tlp_outlet',
        'distric',
        'limit',
        'status_outlet',
    ];

    private const EXPORT_UPDATE_NEW_FIELDS = [
        'badan_usaha_baru',
        'divisi_baru',
        'region_baru',
        'cluster_baru',
        'kode_outlet_baru',
        'nama_outlet_baru',
        'nama_pemilik_outlet_baru',
        'nomer_tlp_outlet_baru',
        'distric_baru',
        'limit_baru',
        'status_outlet_baru',
    ];

    private string $mode;

    private ?int $userId;

    private int $processed = 0;

    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    /**
     * @var array<int, array{row:int,message:string,kode_outlet:?string,columns:array<string,?string>}>
     */
    private array $errors = [];

    private string $summaryKey;

    public function __construct(string $mode = 'create', ?int $userId = null)
    {
        $this->mode = $this->normalizeMode($mode);
        $this->userId = $userId;
        $this->summaryKey = 'outlet-import:'.Str::uuid()->toString();
        $this->ensureSummaryInitialized();
    }

    public function onRow(Row $row): void
    {
        $this->incrementProcessed();
        $rowIndex = $row->getIndex();
        $data = $row->toArray();

        try {
            $this->processRowData($data, $rowIndex, persistNew: true, trackSummary: true);
        } catch (Exception $e) {
            $this->rememberError($rowIndex, $data, $e->getMessage());
            Log::warning('Outlet import error', [
                'row' => $rowIndex,
                'kode_outlet' => $this->extractOutletCode($data),
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function model(array $row, int $rowIndex = 1): ?Outlet
    {
        return $this->processRowData($row, $rowIndex, persistNew: false, trackSummary: false);
    }

    private function processRowData(array $data, int $rowIndex, bool $persistNew, bool $trackSummary): ?Outlet
    {
        $lookupCode = $this->normalizeOutletCode($this->requireValue($data, ['kode_outlet'], 'kode_outlet'));

        if ($lookupCode === null) {
            throw new Exception('Kolom kode_outlet wajib diisi.');
        }

        $targetBadanUsahaName = $this->requireValue($data, ['badan_usaha_baru', 'badan_usaha'], 'badan_usaha');
        $targetDivisiName = $this->requireValue($data, ['divisi_baru', 'divisi'], 'divisi');
        $targetRegionName = $this->requireValue($data, ['region_baru', 'region'], 'region');
        $targetClusterName = $this->requireValue($data, ['cluster_baru', 'cluster'], 'cluster');

        $badanusahaId = $this->getBadanUsahaId($targetBadanUsahaName);
        $divisiId = $this->getDivisionId($targetDivisiName, $badanusahaId);
        $regionId = $this->getRegionId($targetRegionName, $divisiId, $badanusahaId);
        $clusterId = $this->getClusterId($targetClusterName, $badanusahaId, $divisiId, $regionId);

        $targetKodeOutlet = $this->normalizeOutletCode(
            $this->requireValue($data, ['kode_outlet_baru', 'kode_outlet'], 'kode_outlet')
        );

        if ($targetKodeOutlet === null) {
            throw new Exception('Kolom kode_outlet wajib diisi.');
        }

        // Cari outlet existing berdasarkan kode_outlet (lookup) dan divisi sumber
        $sourceDivisiId = $this->resolveDivisionIdFromSource($data, $targetBadanUsahaName, $targetDivisiName, $divisiId);
        $existing = $this->findExistingOutlet($lookupCode, $targetKodeOutlet, $divisiId, $sourceDivisiId);

        // Validasi uniqueness kode_outlet dalam divisi
        if ($this->mode === 'update') {
            // MODE UPDATE: outlet harus ditemukan
            if (! $existing) {
                $message = "Outlet dengan kode {$lookupCode} tidak ditemukan.";

                if ($trackSummary) {
                    $this->incrementSkipped();
                    $this->rememberError($rowIndex, $data, $message);

                    return null;
                }

                throw new Exception($message);
            }

            if ($existing->cluster_id !== $clusterId) {
                $hasUnrealizedPlanVisits = PlanVisit::query()
                    ->unrealized()
                    ->where('visitable_type', Outlet::class)
                    ->where('visitable_id', $existing->id)
                    ->exists();

                if ($hasUnrealizedPlanVisits) {
                    $currentClusterName = $existing->cluster()->value('name');
                    $currentClusterName ??= (string) $existing->cluster_id;

                    $message = "Perubahan cluster ditolak karena outlet {$existing->kode_outlet} masih memiliki plan visit yang belum terealisasi. "
                        ."Cluster saat ini '{$currentClusterName}', target '{$targetClusterName}'.";

                    if ($trackSummary) {
                        $this->incrementSkipped();
                        $this->rememberError($rowIndex, $data, $message);

                        return null;
                    }

                    throw new Exception($message);
                }
            }

            // kode_outlet boleh sama dengan milik sendiri, tapi tidak boleh sama dengan outlet lain dalam divisi yang sama
            $duplicate = Outlet::where('kode_outlet', $targetKodeOutlet)
                ->where('divisi_id', $divisiId)
                ->where('id', '!=', $existing->id)
                ->first();

            if ($duplicate) {
                $message = "Kode outlet {$targetKodeOutlet} sudah dipakai oleh outlet lain di divisi ini.";

                if ($trackSummary) {
                    $this->incrementSkipped();
                    $this->rememberError($rowIndex, $data, $message);

                    return null;
                }

                throw new Exception($message);
            }
        } else {
            // MODE CREATE: kode_outlet harus unique dalam divisi target
            $duplicate = Outlet::where('kode_outlet', $targetKodeOutlet)
                ->where('divisi_id', $divisiId)
                ->first();

            if ($duplicate) {
                $message = "Kode outlet {$targetKodeOutlet} sudah dipakai di divisi ini.";

                if ($trackSummary) {
                    $this->incrementSkipped();
                    $this->rememberError($rowIndex, $data, $message);

                    return null;
                }

                throw new Exception($message);
            }
        }

        $payload = [
            'badanusaha_id' => $badanusahaId,
            'divisi_id' => $divisiId,
            'region_id' => $regionId,
            'cluster_id' => $clusterId,
            'kode_outlet' => $targetKodeOutlet,
            'nama_outlet' => $this->uppercase(
                $this->requireValue($data, ['nama_outlet_baru', 'nama_outlet'], 'nama_outlet', $existing?->nama_outlet)
            ),
            'alamat_outlet' => $this->uppercase(
                $this->firstFilled($data, ['alamat_outlet_baru', 'alamat_outlet'], $existing?->alamat_outlet, 'alamat_outlet')
            ),
            'distric' => $this->uppercase(
                $this->firstFilled($data, ['distric_baru', 'distric'], $existing?->distric, 'distric')
            ),
            'limit' => $this->resolveInteger(
                $this->firstFilled($data, ['limit_baru', 'limit'], label: 'limit'),
                $existing?->limit ?? 0
            ),
            'status_outlet' => $this->uppercase(
                $this->firstFilled(
                    $data,
                    ['status_outlet_baru', 'status_outlet', 'status'],
                    $existing?->status_outlet ?? 'MAINTAIN',
                    'status_outlet'
                )
            ),
            'nama_pemilik_outlet' => $this->uppercase(
                $this->firstFilled($data, ['nama_pemilik_outlet_baru', 'nama_pemilik_outlet'], $existing?->nama_pemilik_outlet, 'nama_pemilik_outlet')
            ),
            'nomer_tlp_outlet' => $this->sanitizeString(
                $this->firstFilled($data, ['nomer_tlp_outlet_baru', 'nomer_tlp_outlet'], $existing?->nomer_tlp_outlet, 'nomer_tlp_outlet')
            ),
        ];

        if ($existing) {
            $existing->fill(
                array_filter(
                    $payload,
                    static fn ($value) => $value !== null,
                ),
            );
            $existing->save();

            if ($trackSummary) {
                $this->incrementUpdated();
            }

            return null;
        }

        $payload['radius'] = $this->resolveInteger($this->firstFilled($data, ['radius'], label: 'radius'), 100);
        $payload['latlong'] = $this->sanitizeString($this->firstFilled($data, ['latlong'], label: 'latlong'));

        $attributes = array_filter(
            $payload,
            static fn ($value) => $value !== null,
        );

        if ($persistNew) {
            Outlet::create($attributes);

            if ($trackSummary) {
                $this->incrementCreated();
            }

            return null;
        }

        return Outlet::make($attributes);
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (): void {
                if (! $this->userId) {
                    $this->flushSummary();

                    return;
                }

                $summary = $this->getSummary();

                // Gunakan nilai terbesar antara summary (dari chunk processing) dan instance vars
                $processed = max($summary['processed'], $this->processed);
                $created = max($summary['created'], $this->created);
                $updated = max($summary['updated'], $this->updated);
                $skipped = max($summary['skipped'], $this->skipped);
                $mode = $summary['mode'] ?? $this->mode;

                // Untuk error, prioritaskan data dari instance saat ini jika ada
                // Ini mencegah data error lama dari cache tercampur
                $currentErrors = ! empty($this->errors) ? $this->errors : ($summary['errors_export'] ?? []);
                $errorCount = count($currentErrors);

                $isEmpty = $processed === 0 && $errorCount === 0;

                $modeLabel = match ($mode) {
                    'update' => 'Update',
                    default => 'Create',
                };

                $messageParts = [
                    "Import {$modeLabel} data outlet selesai.",
                    'Sebanyak '.number_format($processed).' '.str('baris')->plural($processed).' diproses.',
                ];

                if ($created > 0) {
                    $messageParts[] = number_format($created).' '.str('baris')->plural($created).' berhasil dibuat.';
                }

                if ($updated > 0) {
                    $messageParts[] = number_format($updated).' '.str('baris')->plural($updated).' berhasil diperbarui.';
                }

                if ($skipped > 0) {
                    $messageParts[] = number_format($skipped).' '.str('baris')->plural($skipped).' dilewati.';
                }

                if ($errorCount === 0 && $created === 0 && $updated === 0) {
                    $messageParts[] = 'Tidak ada baris data yang diproses. Periksa kembali template sebelum mengunggah ulang.';
                }

                $downloadPath = null;

                if ($errorCount > 0) {
                    $downloadPath = $this->storeErrorReport($currentErrors, $mode);

                    $messageParts[] = number_format($errorCount).' '.str('baris')->plural($errorCount).' gagal diproses dan perlu diperbaiki.';

                    if ($downloadPath) {
                        $messageParts[] = 'Detail kesalahan dapat dilihat pada file Excel terlampir.';
                    }
                }

                $body = implode(' ', $messageParts);

                $downloads = $downloadPath
                    ? [
                        [
                            'name' => 'download_xlsx',
                            'label' => 'Unduh .xlsx',
                            'path' => $downloadPath,
                        ],
                    ]
                    : null;

                SendImportNotification::dispatch(
                    $this->userId,
                    'Import Data Outlet Selesai',
                    $body,
                    $errorCount === 0 && ! $isEmpty,
                    $downloadPath,
                    $downloads
                );

                $this->flushSummary();
            },
        ];
    }

    private function normalizeMode(string $mode): string
    {
        return match (strtolower($mode)) {
            'update' => 'update',
            default => 'create',
        };
    }

    private function requireValue(array $row, array $keys, string $label, ?string $default = null): string
    {
        $value = $this->firstFilled($row, $keys, $default, $label);

        if ($value === null) {
            throw new Exception("Kolom {$label} wajib diisi.");
        }

        return $value;
    }

    private function firstFilled(array $row, array $keys, ?string $default = null, ?string $label = null): ?string
    {
        $label ??= $keys[0] ?? 'kolom';

        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->sanitizeString($row[$key]);

            if ($value !== null && $value !== '') {
                $this->rejectFormulaString($value, $label);

                return $value;
            }
        }

        $value = $default !== null ? $this->sanitizeString($default) : null;

        if ($value !== null && $value !== '') {
            $this->rejectFormulaString($value, $label);
        }

        return $value;
    }

    private function sanitizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function rejectFormulaString(string $value, string $label): void
    {
        if ($this->startsWithFormula($value)) {
            throw new Exception("Kolom {$label} tidak boleh menggunakan rumus Excel. Tempelkan sebagai nilai biasa (Paste Values).");
        }
    }

    private function startsWithFormula(string $value): bool
    {
        $trimmed = ltrim($value);

        if ($trimmed === '') {
            return false;
        }

        $firstChar = $trimmed[0];

        if ($firstChar === '=' || $firstChar === '@') {
            return true;
        }

        if (in_array($firstChar, ['+', '-'], true)) {
            $withoutSign = substr($trimmed, 1);

            // Allow signed numbers or phone-like strings that are commonly prefixed with + or -.
            if ($withoutSign !== '' && preg_match('/^[0-9 .()_-]+$/', $withoutSign)) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function uppercase(?string $value): ?string
    {
        $value = $this->sanitizeString($value);

        return $value === null ? null : mb_strtoupper($value);
    }

    private function normalizeOutletCode(?string $value): ?string
    {
        $value = $this->sanitizeString($value);

        return $value === null ? null : mb_strtoupper(str_replace(' ', '', $value));
    }

    private function normalizeName(string $value): string
    {
        return Str::upper(str_replace(' ', '', $value));
    }

    private function resolveInteger(?string $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    private function getBadanUsahaId(string $name): int
    {
        $normalized = $this->normalizeName($name);

        $badanUsaha = BadanUsaha::query()
            ->select(['id', 'name'])
            ->get()
            ->first(fn ($item) => $this->normalizeName((string) $item->name) === $normalized);

        if (! $badanUsaha) {
            throw new Exception("Badan usaha '{$name}' tidak ditemukan.");
        }

        return $badanUsaha->id;
    }

    private function getDivisionId(string $name, int $badanusahaId): int
    {
        $normalized = $this->normalizeName($name);

        $badanUsaha = BadanUsaha::find($badanusahaId);

        $division = Division::query()
            ->where('badanusaha_id', $badanusahaId)
            ->select(['id', 'name'])
            ->get()
            ->first(fn ($item) => $this->normalizeName((string) $item->name) === $normalized);

        if (! $division) {
            throw new Exception("Divisi '{$name}' tidak ditemukan di badan usaha '{$badanUsaha?->name}'.");
        }

        return $division->id;
    }

    private function getRegionId(string $name, int $divisiId, int $badanusahaId): int
    {
        $normalized = $this->normalizeName($name);

        $division = Division::find($divisiId);

        $region = Region::query()
            ->where('divisi_id', $divisiId)
            ->where('badanusaha_id', $badanusahaId)
            ->select(['id', 'name'])
            ->get()
            ->first(fn ($item) => $this->normalizeName((string) $item->name) === $normalized);

        if (! $region) {
            throw new Exception("Region '{$name}' tidak ditemukan di divisi '{$division?->name}'.");
        }

        return $region->id;
    }

    private function getClusterId(string $name, int $badanusahaId, int $divisiId, int $regionId): int
    {
        $normalized = $this->normalizeName($name);

        $region = Region::find($regionId);

        $cluster = Cluster::query()
            ->where('badanusaha_id', $badanusahaId)
            ->where('divisi_id', $divisiId)
            ->where('region_id', $regionId)
            ->select(['id', 'name'])
            ->get()
            ->first(fn ($item) => $this->normalizeName((string) $item->name) === $normalized);

        if (! $cluster) {
            throw new Exception("Cluster '{$name}' tidak ditemukan di region '{$region?->name}'.");
        }

        return $cluster->id;
    }

    private function rememberError(int $rowIndex, array $row, string $message): void
    {
        $code = $this->extractOutletCode($row);
        $columns = $this->buildExportColumns($row);

        $error = [
            'row' => $rowIndex,
            'message' => $message,
            'kode_outlet' => $code,
            'columns' => $columns,
        ];

        $this->errors[] = $error;

        $this->mutateSummary(function (array &$summary) use ($error): void {
            $summary['errors'] ??= [];
            $summary['errors_export'] ??= [];
            $summary['mode'] ??= $this->mode;

            $summary['error_total']++;

            $summary['errors_export'][] = $error;

            if (count($summary['errors']) >= self::ERROR_SAMPLE_LIMIT) {
                return;
            }

            $summary['errors'][] = $error;
        });
    }

    /**
     * @return array<string, ?string>
     */
    private function buildExportColumns(array $row): array
    {
        $columns = [];

        if ($this->mode === 'create') {
            // Mode CREATE: hanya kolom create
            foreach (self::EXPORT_CREATE_FIELDS as $field) {
                $columns[$field] = $this->sanitizeString($row[$field] ?? null);
            }
        } else {
            // Mode UPDATE: kolom base + kolom baru
            foreach (self::EXPORT_UPDATE_BASE_FIELDS as $field) {
                $columns[$field] = $this->sanitizeString($row[$field] ?? null);
            }

            foreach (self::EXPORT_UPDATE_NEW_FIELDS as $field) {
                $columns[$field] = $this->sanitizeString($row[$field] ?? null);
            }
        }

        return $columns;
    }

    private function extractOutletCode(array $row): ?string
    {
        return $this->normalizeOutletCode($row['kode_outlet'] ?? $row['kode_outlet_baru'] ?? null);
    }

    public function getSummaryKey(): string
    {
        return $this->summaryKey;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    private function incrementProcessed(): void
    {
        $this->processed++;
        $this->mutateSummary(function (array &$summary): void {
            $summary['processed']++;
        });
    }

    private function incrementCreated(): void
    {
        $this->created++;
        $this->mutateSummary(function (array &$summary): void {
            $summary['created']++;
        });
    }

    private function incrementUpdated(): void
    {
        $this->updated++;
        $this->mutateSummary(function (array &$summary): void {
            $summary['updated']++;
        });
    }

    private function incrementSkipped(): void
    {
        $this->skipped++;
        $this->mutateSummary(function (array &$summary): void {
            $summary['skipped']++;
        });
    }

    private function mutateSummary(callable $callback): void
    {
        $this->ensureSummaryInitialized();

        $summary = Cache::get($this->summaryKey, $this->freshSummary());
        $callback($summary);

        Cache::put($this->summaryKey, $summary, now()->addMinutes(self::SUMMARY_TTL_MINUTES));
    }

    private function ensureSummaryInitialized(): void
    {
        Cache::add($this->summaryKey, $this->freshSummary(), now()->addMinutes(self::SUMMARY_TTL_MINUTES));
    }

    private function getSummary(): array
    {
        $this->ensureSummaryInitialized();

        return Cache::get($this->summaryKey, $this->freshSummary());
    }

    private function flushSummary(): void
    {
        Cache::forget($this->summaryKey);
    }

    private function freshSummary(): array
    {
        return [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'error_total' => 0,
            'errors' => [],
            'errors_export' => [],
            'mode' => $this->mode,
        ];
    }

    /**
     * @param  array<int, array{row:int,message:string,kode_outlet:?string,columns:array<string,?string>}>  $errors
     */
    private function storeErrorReport(array $errors, string $mode): ?string
    {
        if ($this->userId === null || $errors === []) {
            return null;
        }

        $path = sprintf(
            'imports/outlets/errors/%s/outlet-import-errors-%s.xlsx',
            $this->userId,
            now()->format('Ymd_His')
        );

        try {
            Excel::store(new OutletImportErrorsExport($errors, $mode), $path, StorageDisk::default());

            return $path;
        } catch (Throwable $exception) {
            Log::warning('Gagal menyimpan laporan error import outlet', [
                'user_id' => $this->userId,
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Cari outlet existing dengan prioritas:
     * 1. Cari di divisi sumber (jika ada) - untuk kasus outlet pindah divisi
     * 2. Cari di divisi target - untuk kasus update biasa
     * 3. Fallback: Cari tanpa filter divisi (hanya mode update) - untuk backward compatibility
     */
    private function findExistingOutlet(string $lookupCode, string $targetKodeOutlet, int $targetDivisiId, ?int $sourceDivisiId): ?Outlet
    {
        $codeForMatch = $this->mode === 'create' ? $targetKodeOutlet : $lookupCode;

        // Prioritas 1: Cari di divisi sumber (jika berbeda dari target)
        if ($sourceDivisiId !== null && $sourceDivisiId !== $targetDivisiId) {
            $existing = Outlet::where('kode_outlet', $codeForMatch)
                ->where('divisi_id', $sourceDivisiId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Prioritas 2: Cari di divisi target
        $existing = Outlet::where('kode_outlet', $codeForMatch)
            ->where('divisi_id', $targetDivisiId)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Mode create tidak perlu fallback
        if ($this->mode === 'create') {
            return null;
        }

        // Fallback untuk mode update: cari tanpa filter divisi (backward compatibility)
        return Outlet::where('kode_outlet', $lookupCode)->first();
    }

    /**
     * Resolve divisi ID dari kolom sumber (badan_usaha, divisi) di Excel
     * untuk mendukung perpindahan outlet antar divisi
     */
    private function resolveDivisionIdFromSource(array $row, string $targetBadanUsahaName, string $targetDivisiName, int $targetDivisiId): ?int
    {
        $sourceBadanUsaha = $this->sanitizeString($row['badan_usaha'] ?? null);
        $sourceDivisi = $this->sanitizeString($row['divisi'] ?? null);

        // Jika tidak ada info sumber, return null
        if ($sourceBadanUsaha === null || $sourceDivisi === null) {
            return null;
        }

        // Jika sumber sama dengan target, return target ID
        if (
            $this->normalizeName($sourceBadanUsaha) === $this->normalizeName($targetBadanUsahaName)
            && $this->normalizeName($sourceDivisi) === $this->normalizeName($targetDivisiName)
        ) {
            return $targetDivisiId;
        }

        // Cari divisi sumber dari database
        try {
            $badanusahaId = $this->getBadanUsahaId($sourceBadanUsaha);

            return $this->getDivisionId($sourceDivisi, $badanusahaId);
        } catch (Exception) {
            return null;
        }
    }
}
