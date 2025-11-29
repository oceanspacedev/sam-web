<?php

namespace App\Imports;

use App\Exports\PlanVisit\PlanVisitImportErrorsExport;
use App\Jobs\SendImportNotification;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use App\Support\StorageDisk;
use Carbon\Carbon;
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

class PlanVisitImport implements OnEachRow, ShouldQueue, WithChunkReading, WithEvents, WithHeadingRow
{
    private const SUMMARY_TTL_MINUTES = 120;

    private const ERROR_SAMPLE_LIMIT = 20;

    private const SUPPORTED_SCOPES = ['daily', 'weekly'];

    private ?int $userId;

    private int $processed = 0;

    private int $created = 0;

    private int $updated = 0;

    private string $scheduleScope;

    /**
     * @var array<int, array{row:int,message:string,kode_outlet:?string,columns:array<string,?string>}>
     */
    private array $errors = [];

    private string $summaryKey;

    public function __construct(?int $userId = null, string $scheduleScope = 'daily')
    {
        $this->userId = $userId;
        $this->scheduleScope = in_array($scheduleScope, self::SUPPORTED_SCOPES, true) ? $scheduleScope : 'daily';
        $this->summaryKey = 'plan-visit-import:'.Str::uuid()->toString();
        $this->ensureSummaryInitialized();
    }

    public function onRow(Row $row): void
    {
        $this->incrementProcessed();
        $rowIndex = $row->getIndex();
        $data = $row->toArray();

        try {
            $username = $this->requireValue($data, ['username'], 'username');
            $kodeOutlet = $this->requireValue($data, ['kode_outlet'], 'kode_outlet');
            $divisionName = $this->requireValue($data, ['divisi'], 'divisi');
            $tanggal = $this->resolveScheduleDate($data);

            $user = User::whereRaw('REPLACE(UPPER(username), " ", "") = ?', [$this->normalizeName($username)])->first();

            if (! $user) {
                throw new Exception('User dengan username '.$username.' tidak ditemukan.');
            }

            $division = Division::whereRaw('REPLACE(UPPER(name), " ", "") = ?', [$this->normalizeName($divisionName)])->first();

            if (! $division) {
                throw new Exception('Divisi '.$divisionName.' tidak ditemukan.');
            }

            $outlet = Outlet::query()
                ->where('divisi_id', $division->id)
                ->whereRaw('REPLACE(UPPER(kode_outlet), " ", "") = ?', [$this->normalizeOutletCode($kodeOutlet)])
                ->first();

            if (! $outlet) {
                throw new Exception('Outlet '.$kodeOutlet.' di divisi '.$divisionName.' tidak ditemukan.');
            }

            $userDivisionIds = $user->divisis()->pluck('divisions.id')->toArray();
            if (! in_array((int) $outlet->divisi_id, $userDivisionIds, true)) {
                throw new Exception('User dengan username '.$username.' tidak terdaftar pada divisi '.$divisionName.'.');
            }

            $schedulePayload = PlanVisit::schedulePayload($tanggal, $this->scheduleScope);

            $existing = PlanVisit::query()
                ->where('outlet_id', $outlet->id)
                ->where('user_id', $user->id)
                ->where('schedule_scope', $this->scheduleScope)
                ->whereDate('period_start', $schedulePayload['period_start'])
                ->first();

            if ($existing) {
                $existing->update(array_merge($schedulePayload, [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                ]));

                $this->incrementUpdated();

                return;
            }

            PlanVisit::create(array_merge($schedulePayload, [
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
            ]));

            $this->incrementCreated();
        } catch (Exception $exception) {
            $this->rememberError($rowIndex, $data, $exception->getMessage());

            Log::warning('Plan visit import error', [
                'row' => $rowIndex,
                'kode_outlet' => $data['kode_outlet'] ?? null,
                'schedule_scope' => $this->scheduleScope,
                'message' => $exception->getMessage(),
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
                $errorCount = $summary['error_total'] ?? count($this->errors);
                $errorsForExport = $summary['errors_export'] ?? $this->errors;

                $isEmpty = $processed === 0 && $errorCount === 0;

                $messageParts = [
                    'Import plan visit selesai.',
                    'Total '.number_format($processed).' baris diproses.',
                    'Mode '.strtoupper($this->scheduleScope).'.',
                ];

                if ($created > 0) {
                    $messageParts[] = number_format($created).' baris baru berhasil dibuat.';
                }

                if ($updated > 0) {
                    $messageParts[] = number_format($updated).' baris diperbarui.';
                }

                if ($isEmpty) {
                    $messageParts[] = 'Tidak ada baris yang berhasil diproses. Periksa kembali template sebelum mengunggah ulang.';
                }

                $downloadPath = null;

                if ($errorCount > 0) {
                    $messageParts[] = number_format($errorCount).' baris perlu diperbaiki.';
                    $downloadPath = $this->storeErrorReport($errorsForExport);

                    if ($downloadPath) {
                        $messageParts[] = 'Detail error tersedia pada file Excel terlampir.';
                    }
                }

                $body = implode(' ', $messageParts);

                $downloads = $downloadPath ? [[
                    'name' => 'download_xlsx',
                    'label' => 'Unduh .xlsx',
                    'path' => $downloadPath,
                ]] : null;

                SendImportNotification::dispatch(
                    $this->userId,
                    'Import Plan Visit Selesai',
                    $body,
                    $errorCount === 0 && ! $isEmpty,
                    $downloadPath,
                    $downloads
                );

                $this->flushSummary();
            },
        ];
    }

    private function resolveScheduleDate(array $row): Carbon
    {
        if ($this->scheduleScope === 'weekly' && $this->hasWeeklyColumns($row)) {
            $weekDate = $this->resolveWeeklyDate($row);
            $this->assertScheduleDateIsAllowed($weekDate);

            return $weekDate;
        }

        $tanggalVisitRaw = $this->requireValue($row, ['tanggal_visit'], 'tanggal_visit');

        if (strlen((string) preg_replace('/\s+/', '', $tanggalVisitRaw)) < 6) {
            throw new Exception('Tanggal tidak valid, pastikan kolom tanggal_visit bertipe text dengan format yyyy-mm-dd.');
        }

        $tanggal = Carbon::parse($tanggalVisitRaw);
        $this->assertScheduleDateIsAllowed($tanggal);

        return $tanggal;
    }

    private function hasWeeklyColumns(array $row): bool
    {
        if (array_key_exists('schedule_week', $row) || array_key_exists('schedule_year', $row)) {
            return true;
        }

        return $this->sanitizeString($row['schedule_week'] ?? null) !== null
            || $this->sanitizeString($row['schedule_year'] ?? null) !== null;
    }

    private function resolveWeeklyDate(array $row): Carbon
    {
        $weekValue = $this->sanitizeString($row['schedule_week'] ?? null);
        $yearValue = $this->sanitizeString($row['schedule_year'] ?? null);

        if ($weekValue === null || $yearValue === null) {
            throw new Exception('Kolom schedule_week dan schedule_year wajib diisi untuk import weekly.');
        }

        $week = $this->extractDigits($weekValue);
        $year = $this->extractDigits($yearValue);

        if ($week === null || $week < 1 || $week > 53) {
            throw new Exception('Nilai schedule_week harus antara 1 sampai 53.');
        }

        if ($year === null || $year < 2000 || $year > 2100) {
            throw new Exception('Nilai schedule_year tidak valid.');
        }

        try {
            $date = Carbon::now()->setISODate($year, $week, Carbon::MONDAY)->startOfDay();
        } catch (Exception $exception) {
            throw new Exception('Kombinasi schedule_week dan schedule_year tidak valid.');
        }

        return $date;
    }

    private function extractDigits(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits === '' ? null : (int) $digits;
    }

    private function assertScheduleDateIsAllowed(Carbon $tanggal): void
    {
        $minimumAllowedDate = now()->startOfDay();

        if ($this->scheduleScope === 'weekly') {
            $minimumAllowedDate = $minimumAllowedDate->startOfWeek(Carbon::MONDAY)->addWeek();
        } else {
            $minimumAllowedDate = $minimumAllowedDate->addWeek();
        }

        if ($tanggal->lt($minimumAllowedDate)) {
            throw new Exception('Tanggal '.$tanggal->format('Y-m-d').' tidak valid. Minimal satu minggu dari hari ini (>= '.$minimumAllowedDate->format('Y-m-d').').');
        }

        if ($tanggal->weekOfYear <= now()->weekOfYear && now() > now()->startOfDay()->startOfWeek()->addDay(1)->addHour(10)) {
            throw new Exception('Plan minggu '.$tanggal->weekOfYear.' sudah melewati batas cut-off Selasa 10.00.');
        }
    }

    private function requireValue(array $row, array $keys, string $label): string
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

        throw new Exception('Kolom '.$label.' wajib diisi.');
    }

    private function sanitizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeOutletCode(?string $value): ?string
    {
        $value = $this->sanitizeString($value);

        return $value === null ? null : Str::upper(str_replace(' ', '', $value));
    }

    private function normalizeName(?string $value): string
    {
        return Str::upper(str_replace(' ', '', (string) $value));
    }

    private function rememberError(int $rowIndex, array $row, string $message): void
    {
        $columns = [
            'username' => $this->sanitizeString($row['username'] ?? null),
            'kode_outlet' => $this->sanitizeString($row['kode_outlet'] ?? null),
            'divisi' => $this->sanitizeString($row['divisi'] ?? null),
            'nama_outlet' => $this->sanitizeString($row['nama_outlet'] ?? null),
            'tanggal_visit' => $this->sanitizeString($row['tanggal_visit'] ?? null),
            'schedule_week' => $this->sanitizeString($row['schedule_week'] ?? null),
            'schedule_year' => $this->sanitizeString($row['schedule_year'] ?? null),
        ];

        $error = [
            'row' => $rowIndex,
            'message' => $message,
            'kode_outlet' => $columns['kode_outlet'],
            'columns' => $columns,
        ];

        $this->errors[] = $error;

        $this->mutateSummary(function (array &$summary) use ($error): void {
            $summary['errors'] ??= [];
            $summary['errors_export'] ??= [];
            $summary['error_total']++;

            $summary['errors_export'][] = $error;

            if (count($summary['errors']) >= self::ERROR_SAMPLE_LIMIT) {
                return;
            }

            $summary['errors'][] = $error;
        });
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
            'error_total' => 0,
            'errors' => [],
            'errors_export' => [],
        ];
    }

    /**
     * @param  array<int, array{row:int,message:string,kode_outlet:?string,columns:array<string,?string>}>  $errors
     */
    private function storeErrorReport(array $errors): ?string
    {
        if ($this->userId === null || $errors === []) {
            return null;
        }

        $path = sprintf(
            'imports/plan-visit/errors/%s/plan-visit-import-errors-%s.xlsx',
            $this->userId,
            now()->format('Ymd_His')
        );

        try {
            Excel::store(new PlanVisitImportErrorsExport($errors, $this->scheduleScope), $path, StorageDisk::default());

            return $path;
        } catch (Throwable $exception) {
            Log::warning('Gagal menyimpan laporan error import plan visit', [
                'user_id' => $this->userId,
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function getSummaryKey(): string
    {
        return $this->summaryKey;
    }
}
