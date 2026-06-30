<?php

namespace App\Imports;

use App\Exports\Outlet\OutletImportErrorsExport;
use App\Jobs\SendImportNotification;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use App\Support\ImportOrganizationalResolver;
use App\Support\ImportSpreadsheetValidator;
use App\Support\ImportSummaryStore;
use App\Support\OrganizationalName;
use App\Support\StorageDisk;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Row;
use Throwable;

class OutletImport implements OnEachRow, ShouldQueue, WithChunkReading, WithEvents, WithHeadingRow, WithMultipleSheets
{
    use RegistersEventListeners;

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

    private ImportSummaryStore $summaryStore;

    private ?string $uploadedDisk;

    private ?string $uploadedPath;

    private bool $importerResolved = false;

    private ?User $importer = null;

    private ?ImportOrganizationalResolver $organizationalResolver = null;

    public function __construct(
        string $mode = 'create',
        ?int $userId = null,
        ?string $uploadedDisk = null,
        ?string $uploadedPath = null
    ) {
        $this->mode = $this->normalizeMode($mode);
        $this->userId = $userId;
        $this->summaryStore = new ImportSummaryStore(
            'outlet-import:'.Str::uuid()->toString(),
            $this->freshSummary(),
        );
        $this->summaryStore->initialize();
        $this->uploadedDisk = $uploadedDisk;
        $this->uploadedPath = $uploadedPath ? ltrim($uploadedPath, '/') : null;
    }

    public function onRow(Row $row): void
    {
        $rowIndex = $row->getIndex();
        $data = $row->toArray();

        if ($this->isBlankImportRow($data)) {
            return;
        }

        $this->incrementProcessed();

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

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
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

        $badanusahaId = $this->organizationalResolver()->resolveBadanUsahaId($targetBadanUsahaName);
        $divisiId = $this->organizationalResolver()->resolveDivisionId($targetDivisiName, $badanusahaId);
        $regionId = $this->organizationalResolver()->resolveRegionId($targetRegionName, $divisiId, $badanusahaId);
        $clusterId = $this->organizationalResolver()->resolveClusterId($targetClusterName, $badanusahaId, $divisiId, $regionId);

        $targetKodeOutlet = $this->normalizeOutletCode(
            $this->requireValue($data, ['kode_outlet_baru', 'kode_outlet'], 'kode_outlet')
        );

        if ($targetKodeOutlet === null) {
            throw new Exception('Kolom kode_outlet wajib diisi.');
        }

        // Cari outlet existing berdasarkan kode_outlet (lookup) dan divisi sumber
        $sourceDivisiId = $this->resolveDivisionIdFromSource($data, $targetBadanUsahaName, $targetDivisiName, $divisiId);
        $existing = $this->findExistingOutlet($lookupCode, $targetKodeOutlet, $divisiId, $sourceDivisiId, $badanusahaId);
        $targetScope = Outlet::make([
            'badanusaha_id' => $badanusahaId,
            'divisi_id' => $divisiId,
            'region_id' => $regionId,
            'cluster_id' => $clusterId,
        ]);

        if ($existing) {
            $this->assertImporterCanWriteOutlet($existing, $lookupCode, 'sumber');
        }

        $this->assertImporterCanWriteOutlet($targetScope, $targetKodeOutlet, 'target');

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
                $existing?->limit ?? 0,
                'limit'
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

        $payload['radius'] = $this->resolveInteger($this->firstFilled($data, ['radius'], label: 'radius'), 100, 'radius');
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

    public function afterImport(AfterImport $event): void
    {
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

        $currentErrors = $this->currentErrorRows($summary);
        $errorCount = count($currentErrors);

        $isEmpty = $processed === 0 && $errorCount === 0;

        $modeLabel = match ($mode) {
            'update' => 'Update',
            default => 'Create',
        };

        $downloadPath = null;

        if ($errorCount > 0) {
            $downloadPath = $this->storeErrorReport($currentErrors, $mode);
        }

        $body = $this->buildResultBody(
            $modeLabel,
            $processed,
            $created,
            $updated,
            $skipped,
            $errorCount,
            $downloadPath !== null,
        );

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
    }

    public function importFailed(ImportFailed $event): void
    {
        $this->handleImportFailed($this->exceptionFromImportFailed($event));
    }

    private function handleImportFailed(?Throwable $exception = null): void
    {
        $summary = $this->getSummary();
        $mode = $summary['mode'] ?? $this->mode;
        $currentErrors = $this->currentErrorRows($summary);
        $downloadPath = $this->storeErrorReport($currentErrors, $mode);

        $this->cleanupUploadedFile();
        $this->flushSummary();

        Log::error('Outlet import failed in queue', [
            'user_id' => $this->userId,
            'mode' => $this->mode,
            'uploaded_disk' => $this->uploadedDisk,
            'uploaded_path' => $this->uploadedPath,
            'error' => $exception?->getMessage(),
        ]);

        if (! $this->userId) {
            return;
        }

        $body = 'Import data outlet (mode: '.strtoupper($this->mode).') gagal diproses di worker. '.$this->failureReason($exception);
        $downloads = $downloadPath ? [[
            'name' => 'download_xlsx',
            'label' => 'Unduh laporan error',
            'path' => $downloadPath,
        ]] : null;

        if ($downloadPath) {
            $body .= ' Error yang sempat terbaca tersedia pada file Excel terlampir.';
        }

        SendImportNotification::dispatch(
            $this->userId,
            'Import Data Outlet Gagal',
            $body,
            false,
            $downloadPath,
            $downloads
        );
    }

    private function buildResultBody(
        string $modeLabel,
        int $processed,
        int $created,
        int $updated,
        int $skipped,
        int $errorCount,
        bool $hasDownloadPath,
    ): string {
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

        if ($errorCount === 0 && $created === 0 && $updated === 0) {
            $messageParts[] = 'Tidak ada baris data yang diproses. Periksa kembali template sebelum mengunggah ulang.';
        }

        if ($errorCount > 0) {
            $messageParts[] = number_format($errorCount).' '.str('baris')->plural($errorCount).' gagal atau dilewati dan perlu diperbaiki.';

            if ($skipped > 0) {
                $messageParts[] = number_format($skipped).' '.str('baris')->plural($skipped).' di antaranya dilewati oleh aturan validasi.';
            }

            if ($hasDownloadPath) {
                $messageParts[] = 'Detail kesalahan dapat dilihat pada file Excel terlampir.';
            }
        }

        return implode(' ', $messageParts);
    }

    private function failureReason(?Throwable $exception): string
    {
        if (! $exception) {
            return 'Silakan periksa file dan coba unggah ulang.';
        }

        $message = trim(preg_replace('/\s+/', ' ', $exception->getMessage()) ?: '');

        if ($message === '') {
            return 'Silakan periksa file dan coba unggah ulang.';
        }

        return 'Penyebab: '.Str::limit($message, 300);
    }

    private function exceptionFromImportFailed(ImportFailed $event): ?Throwable
    {
        if (method_exists($event, 'getException')) {
            $exception = $event->getException();

            return $exception instanceof Throwable ? $exception : null;
        }

        $exception = $event->exception ?? null;

        return $exception instanceof Throwable ? $exception : null;
    }

    private function cleanupUploadedFile(): void
    {
        if (! $this->uploadedDisk || ! $this->uploadedPath) {
            return;
        }

        try {
            $storage = Storage::disk($this->uploadedDisk);

            if ($storage->exists($this->uploadedPath)) {
                $storage->delete($this->uploadedPath);
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to cleanup uploaded outlet import file', [
                'disk' => $this->uploadedDisk,
                'path' => $this->uploadedPath,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function assertImporterCanWriteOutlet(Outlet $outlet, string $kodeOutlet, string $scopeLabel): void
    {
        $importer = $this->importer();

        if (! $importer || $outlet->isVisibleTo($importer)) {
            return;
        }

        throw new Exception("User import tidak memiliki akses ke outlet {$scopeLabel} {$kodeOutlet}.");
    }

    private function importer(): ?User
    {
        if (! $this->importerResolved) {
            $this->importer = $this->userId ? User::with('role')->find($this->userId) : null;
            $this->importerResolved = true;
        }

        return $this->importer;
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

        return $default !== null ? $this->sanitizeString($default) : null;
    }

    private function sanitizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
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

        if ($value === null) {
            return null;
        }

        $value = str_replace(' ', '', $value);

        if (preg_match('/^\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        return mb_strtoupper($value);
    }

    private function organizationalResolver(): ImportOrganizationalResolver
    {
        return $this->organizationalResolver ??= new ImportOrganizationalResolver;
    }

    private function isBlankImportRow(array $data): bool
    {
        $keys = [
            'kode_outlet',
            'kode_outlet_baru',
            'badan_usaha',
            'badan_usaha_baru',
            'divisi',
            'divisi_baru',
            'region',
            'region_baru',
            'cluster',
            'cluster_baru',
            'nama_outlet',
            'nama_outlet_baru',
        ];

        $values = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $values[] = $data[$key];
            }
        }

        return ! ImportSpreadsheetValidator::rowHasMeaningfulValue($values);
    }

    private function normalizeName(string $value): string
    {
        return OrganizationalName::normalizeLookup($value);
    }

    private function resolveInteger(?string $value, int $default, string $label = 'nilai'): int
    {
        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value)) {
            throw new Exception("Kolom {$label} harus berupa angka.");
        }

        return (int) $value;
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
        return $this->summaryStore->key();
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
        $this->summaryStore->mutate($callback);
    }

    private function getSummary(): array
    {
        return $this->summaryStore->get();
    }

    private function flushSummary(): void
    {
        $this->summaryStore->forget();
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
     * @return array<int, array{row:int,message:string,kode_outlet:?string,columns:array<string,?string>}>
     */
    private function currentErrorRows(array $summary): array
    {
        $merged = [];

        foreach ([($summary['errors_export'] ?? []), $this->errors] as $errorRows) {
            foreach ($errorRows as $error) {
                if (! is_array($error)) {
                    continue;
                }

                $key = implode('|', [
                    (string) ($error['row'] ?? ''),
                    (string) ($error['message'] ?? ''),
                    (string) ($error['kode_outlet'] ?? ''),
                    md5(json_encode($error['columns'] ?? []) ?: ''),
                ]);

                $merged[$key] = $error;
            }
        }

        return array_values($merged);
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
            Excel::store(new OutletImportErrorsExport($errors, $mode, $this->userId), $path, StorageDisk::default());

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
    private function findExistingOutlet(
        string $lookupCode,
        string $targetKodeOutlet,
        int $targetDivisiId,
        ?int $sourceDivisiId,
        int $badanusahaId,
    ): ?Outlet {
        $codeForMatch = $this->mode === 'create' ? $targetKodeOutlet : $lookupCode;

        if ($sourceDivisiId !== null && $sourceDivisiId !== $targetDivisiId) {
            $existing = Outlet::where('kode_outlet', $codeForMatch)
                ->where('divisi_id', $sourceDivisiId)
                ->where('badanusaha_id', $badanusahaId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $existing = Outlet::where('kode_outlet', $codeForMatch)
            ->where('divisi_id', $targetDivisiId)
            ->where('badanusaha_id', $badanusahaId)
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($this->mode === 'create') {
            return null;
        }

        $matches = Outlet::query()
            ->where('kode_outlet', $lookupCode)
            ->where('badanusaha_id', $badanusahaId)
            ->get();

        if ($matches->count() > 1) {
            throw new Exception(
                "Kode outlet {$lookupCode} ditemukan di beberapa divisi dalam badan usaha yang sama. Pastikan kolom divisi diisi dengan benar."
            );
        }

        return $matches->first();
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
            $badanusahaId = $this->organizationalResolver()->resolveBadanUsahaId($sourceBadanUsaha);

            return $this->organizationalResolver()->resolveDivisionId($sourceDivisi, $badanusahaId);
        } catch (Exception) {
            return null;
        }
    }
}
