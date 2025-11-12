<?php

namespace App\Imports;

use App\Jobs\SendImportNotification;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
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
use Maatwebsite\Excel\Row;

class OutletImport implements OnEachRow, ShouldQueue, WithChunkReading, WithEvents, WithHeadingRow
{
    private const SUMMARY_TTL_MINUTES = 120;

    private const ERROR_SAMPLE_LIMIT = 20;

    private string $mode;

    private ?int $userId;

    private int $processed = 0;

    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    /**
     * @var array<int, array{row:int,message:string,kode_outlet:?string}>
     */
    private array $errors = [];

    private string $summaryKey;

    public function __construct(string $mode = 'upsert', ?int $userId = null)
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
            $lookupCode = $this->normalizeOutletCode($this->requireValue($data, ['kode_outlet'], 'kode_outlet'));

            if ($lookupCode === null) {
                throw new Exception('Kolom kode_outlet wajib diisi.');
            }

            $existing = Outlet::where('kode_outlet', $lookupCode)->first();

            if ($existing && $this->mode === 'create') {
                $this->incrementSkipped();
                $this->rememberError($rowIndex, $data, 'Outlet sudah terdaftar, dilewati karena mode CREATE.');

                return;
            }

            if (! $existing && $this->mode === 'update') {
                $this->incrementSkipped();
                $this->rememberError($rowIndex, $data, 'Outlet tidak ditemukan, tidak dapat diproses pada mode UPDATE.');

                return;
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
                    $this->firstFilled($data, ['alamat_outlet_baru', 'alamat_outlet'], $existing?->alamat_outlet)
                ),
                'distric' => $this->uppercase(
                    $this->firstFilled($data, ['distric_baru', 'distric'], $existing?->distric)
                ),
                'limit' => $this->resolveInteger(
                    $this->firstFilled($data, ['limit_baru', 'limit']),
                    $existing?->limit ?? 0
                ),
                'status_outlet' => $this->uppercase(
                    $this->firstFilled($data, ['status_outlet_baru', 'status_outlet', 'status'], $existing?->status_outlet ?? 'MAINTAIN')
                ),
                'nama_pemilik_outlet' => $this->uppercase(
                    $this->firstFilled($data, ['nama_pemilik_outlet_baru', 'nama_pemilik_outlet'], $existing?->nama_pemilik_outlet)
                ),
                'nomer_tlp_outlet' => $this->sanitizeString(
                    $this->firstFilled($data, ['nomer_tlp_outlet_baru', 'nomer_tlp_outlet'], $existing?->nomer_tlp_outlet)
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
                $this->incrementUpdated();

                return;
            }

            $payload['radius'] = $this->resolveInteger($this->firstFilled($data, ['radius']), 100);
            $payload['latlong'] = $this->sanitizeString($this->firstFilled($data, ['latlong']));

            Outlet::create(
                array_filter(
                    $payload,
                    static fn ($value) => $value !== null,
                ),
            );

            $this->incrementCreated();
        } catch (Exception $e) {
            $this->rememberError($rowIndex, $data, $e->getMessage());
            Log::warning('Outlet import error', [
                'row' => $rowIndex,
                'kode_outlet' => $this->extractOutletCode($data),
                'message' => $e->getMessage(),
            ]);
        }
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

                $processed = max($summary['processed'], $this->processed);
                $created = max($summary['created'], $this->created);
                $updated = max($summary['updated'], $this->updated);
                $skipped = max($summary['skipped'], $this->skipped);
                $errorCount = $summary['error_total'] ?? count($this->errors);
                $errorDetails = $summary['errors'] ?? $this->errors;

                $isEmpty = $processed === 0 && $errorCount === 0;

                $lines = [
                    'Mode: '.strtoupper($this->mode),
                    'Diproses: '.$processed,
                    'Dibuat: '.$created,
                    'Diperbarui: '.$updated,
                    'Dilewati: '.$skipped,
                ];

                if ($isEmpty) {
                    $lines[] = 'Tidak ada baris data yang diproses. Pastikan data dimulai pada baris kedua dan sheet yang diunggah sudah terisi.';
                    $lines[] = 'Periksa kembali bahwa nama kolom mengikuti template (contoh: badan_usaha_baru, divisi_baru, region_baru, cluster_baru).';
                    $lines[] = 'Jika Anda menguji validasi hierarki, gunakan nilai yang benar-benar terdaftar untuk melihat pesan detail kesalahan.';
                }

                if ($errorCount > 0) {
                    $lines[] = 'Gagal: '.$errorCount;
                    $lines[] = 'Detail:';

                    foreach (array_slice($errorDetails, 0, 5) as $error) {
                        $code = $error['kode_outlet'] ? ' ['.$error['kode_outlet'].']' : '';
                        $lines[] = '- Baris '.$error['row'].$code.': '.$error['message'];
                    }

                    if ($errorCount > 5) {
                        $lines[] = '- dan lainnya...';
                    }
                }

                SendImportNotification::dispatch(
                    $this->userId,
                    'Import Data Outlet',
                    implode("\n", $lines),
                    $errorCount === 0 && ! $isEmpty
                );

                $this->flushSummary();
            },
        ];
    }

    private function normalizeMode(string $mode): string
    {
        return match (strtolower($mode)) {
            'create' => 'create',
            'update' => 'update',
            default => 'upsert',
        };
    }

    private function requireValue(array $row, array $keys, string $label, ?string $default = null): string
    {
        $value = $this->firstFilled($row, $keys, $default);

        if ($value === null) {
            throw new Exception("Kolom {$label} wajib diisi.");
        }

        return $value;
    }

    private function firstFilled(array $row, array $keys, ?string $default = null): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->sanitizeString($row[$key]);

            if ($value !== null && $value !== '') {
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

        return $value === '' ? null : $value;
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
            throw new Exception('Badan usaha '.$name.' tidak ditemukan.');
        }

        return $badanUsaha->id;
    }

    private function getDivisionId(string $name, int $badanusahaId): int
    {
        $normalized = $this->normalizeName($name);

        $division = Division::query()
            ->where('badanusaha_id', $badanusahaId)
            ->select(['id', 'name'])
            ->get()
            ->first(fn ($item) => $this->normalizeName((string) $item->name) === $normalized);

        if (! $division) {
            throw new Exception('Divisi '.$name.' tidak ditemukan untuk badan usaha terkait.');
        }

        return $division->id;
    }

    private function getRegionId(string $name, int $divisiId, int $badanusahaId): int
    {
        $normalized = $this->normalizeName($name);

        $region = Region::query()
            ->where('divisi_id', $divisiId)
            ->where('badanusaha_id', $badanusahaId)
            ->select(['id', 'name'])
            ->get()
            ->first(fn ($item) => $this->normalizeName((string) $item->name) === $normalized);

        if (! $region) {
            throw new Exception('Region '.$name.' tidak ditemukan untuk divisi terkait.');
        }

        return $region->id;
    }

    private function getClusterId(string $name, int $badanusahaId, int $divisiId, int $regionId): int
    {
        $normalized = $this->normalizeName($name);

        $cluster = Cluster::query()
            ->where('badanusaha_id', $badanusahaId)
            ->where('divisi_id', $divisiId)
            ->where('region_id', $regionId)
            ->select(['id', 'name'])
            ->get()
            ->first(fn ($item) => $this->normalizeName((string) $item->name) === $normalized);

        if (! $cluster) {
            throw new Exception('Cluster '.$name.' tidak ditemukan untuk region terkait.');
        }

        return $cluster->id;
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/\s+/', '', mb_strtoupper($value));
    }

    private function rememberError(int $rowIndex, array $row, string $message): void
    {
        $code = $this->extractOutletCode($row);

        $this->errors[] = [
            'row' => $rowIndex,
            'message' => $message,
            'kode_outlet' => $code,
        ];

        $this->mutateSummary(function (array &$summary) use ($rowIndex, $message, $code): void {
            $summary['error_total']++;

            if (count($summary['errors']) >= self::ERROR_SAMPLE_LIMIT) {
                return;
            }

            $summary['errors'][] = [
                'row' => $rowIndex,
                'message' => $message,
                'kode_outlet' => $code,
            ];
        });
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
        ];
    }
}
