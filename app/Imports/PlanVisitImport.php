<?php

namespace App\Imports;

use App\Exports\PlanVisit\PlanVisitImportErrorsExport;
use App\Jobs\SendImportNotification;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use App\Support\ImportOrganizationalResolver;
use App\Support\ImportSpreadsheetValidator;
use App\Support\ImportSummaryStore;
use App\Support\OrganizationalName;
use App\Support\StorageDisk;
use Carbon\Carbon;
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

class PlanVisitImport implements OnEachRow, ShouldQueue, WithChunkReading, WithEvents, WithHeadingRow, WithMultipleSheets
{
    use RegistersEventListeners;

    private const ERROR_SAMPLE_LIMIT = 20;

    private const SUPPORTED_SCOPES = ['daily', 'weekly'];

    private const CUTOFF_DAY_OFFSETS = [
        'monday' => 0,
        'senin' => 0,
        'tuesday' => 1,
        'selasa' => 1,
        'wednesday' => 2,
        'rabu' => 2,
        'thursday' => 3,
        'kamis' => 3,
        'friday' => 4,
        'jumat' => 4,
        'jum\'at' => 4,
        'saturday' => 5,
        'sabtu' => 5,
        'sunday' => 6,
        'minggu' => 6,
    ];

    private const CUTOFF_DAY_LABELS = [
        0 => 'Senin',
        1 => 'Selasa',
        2 => 'Rabu',
        3 => 'Kamis',
        4 => 'Jumat',
        5 => 'Sabtu',
        6 => 'Minggu',
    ];

    private ?int $userId;

    private int $processed = 0;

    private int $created = 0;

    private int $updated = 0;

    private string $scheduleScope;

    /**
     * @var array<int, array{row:int,message:string,kode_outlet:?string,columns:array<string,?string>}>
     */
    private array $errors = [];

    private ImportSummaryStore $summaryStore;

    private ?string $uploadedDisk;

    private ?string $uploadedPath;

    private Carbon $submittedAt;

    private bool $importerResolved = false;

    private ?User $importer = null;

    private ?ImportOrganizationalResolver $organizationalResolver = null;

    public function __construct(
        ?int $userId = null,
        string $scheduleScope = 'daily',
        ?string $uploadedDisk = null,
        ?string $uploadedPath = null,
        ?Carbon $submittedAt = null
    ) {
        $this->userId = $userId;
        $this->scheduleScope = in_array($scheduleScope, self::SUPPORTED_SCOPES, true) ? $scheduleScope : 'daily';
        $this->summaryStore = new ImportSummaryStore(
            'plan-visit-import:'.Str::uuid()->toString(),
            $this->freshSummary(),
        );
        $this->summaryStore->initialize();
        $this->uploadedDisk = $uploadedDisk;
        $this->uploadedPath = $uploadedPath ? ltrim($uploadedPath, '/') : null;
        $this->submittedAt = ($submittedAt ?? now())->copy();
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
            $username = $this->requireValue($data, ['username'], 'username');
            $kodeOutlet = $this->requireValue($data, ['kode_outlet'], 'kode_outlet');
            $divisionName = $this->requireValue($data, ['divisi'], 'divisi');
            $tanggal = $this->resolveScheduleDate($data);

            $namaOutlet = $this->sanitizeString($data['nama_outlet'] ?? null);
            if ($namaOutlet !== null) {
                $this->assertNotFormula($namaOutlet, 'nama_outlet');
            }

            $user = User::whereRaw('REPLACE(UPPER(username), " ", "") = ?', [$this->normalizeName($username)])->first();

            if (! $user) {
                throw new Exception('User dengan username '.$username.' tidak ditemukan.');
            }

            $division = $this->organizationalResolver()->resolveDivisionModelForImport(
                $divisionName,
                $this->sanitizeString($data['badan_usaha'] ?? null),
                $this->importer(),
            );

            $outlet = Outlet::query()
                ->where('divisi_id', $division->id)
                ->whereRaw('REPLACE(UPPER(kode_outlet), " ", "") = ?', [$this->normalizeOutletCode($kodeOutlet)])
                ->first();

            if (! $outlet) {
                throw new Exception('Outlet '.$kodeOutlet.' di divisi '.$divisionName.' tidak ditemukan.');
            }

            $this->assertTargetUserCanVisitOutlet($user, $outlet, $username, $kodeOutlet);
            $this->assertImporterCanAssignPlanVisit($user, $outlet, $username, $kodeOutlet);

            $schedulePayload = PlanVisit::schedulePayload($tanggal, $this->scheduleScope);

            $existing = PlanVisit::query()
                ->where('visitable_type', Outlet::class)
                ->where('visitable_id', $outlet->id)
                ->where('user_id', $user->id)
                ->where('schedule_scope', $this->scheduleScope)
                ->whereDate('period_start', $schedulePayload['period_start'])
                ->first();

            if ($existing) {
                $existing->update(array_merge($schedulePayload, [
                    'user_id' => $user->id,
                    'visitable_type' => Outlet::class,
                    'visitable_id' => $outlet->id,
                ]));

                $this->incrementUpdated();

                return;
            }

            PlanVisit::create(array_merge($schedulePayload, [
                'user_id' => $user->id,
                'visitable_type' => Outlet::class,
                'visitable_id' => $outlet->id,
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

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
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

        $currentErrors = $this->currentErrorRows($summary);
        $errorCount = count($currentErrors);

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
            $downloadPath = $this->storeErrorReport($currentErrors);

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
    }

    public function importFailed(ImportFailed $event): void
    {
        $this->handleImportFailed($this->exceptionFromImportFailed($event));
    }

    public static function uploadCutoffLabel(): string
    {
        [$hour, $minute] = self::uploadCutoffClock();

        return self::CUTOFF_DAY_LABELS[self::uploadCutoffDayOffset()].' '.sprintf('%02d.%02d', $hour, $minute);
    }

    private function handleImportFailed(?Throwable $exception = null): void
    {
        $summary = $this->getSummary();
        $currentErrors = $this->currentErrorRows($summary);
        $downloadPath = $this->storeErrorReport($currentErrors);

        $this->cleanupUploadedFile();
        $this->flushSummary();

        Log::error('Plan visit import failed in queue', [
            'user_id' => $this->userId,
            'schedule_scope' => $this->scheduleScope,
            'uploaded_disk' => $this->uploadedDisk,
            'uploaded_path' => $this->uploadedPath,
            'error' => $exception?->getMessage(),
        ]);

        if (! $this->userId) {
            return;
        }

        $body = 'Import plan visit (scope: '.strtoupper($this->scheduleScope).') gagal diproses di worker. '.$this->failureReason($exception);
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
            'Import Plan Visit Gagal',
            $body,
            false,
            $downloadPath,
            $downloads
        );
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
            Log::warning('Failed to cleanup uploaded plan visit import file', [
                'disk' => $this->uploadedDisk,
                'path' => $this->uploadedPath,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function assertTargetUserCanVisitOutlet(User $user, Outlet $outlet, string $username, string $kodeOutlet): void
    {
        if ($outlet->isVisibleTo($user)) {
            return;
        }

        throw new Exception('User dengan username '.$username.' tidak memiliki coverage untuk outlet '.$kodeOutlet.'.');
    }

    private function assertImporterCanAssignPlanVisit(User $targetUser, Outlet $outlet, string $username, string $kodeOutlet): void
    {
        $importer = $this->importer();

        if (! $importer) {
            return;
        }

        $canAccessUser = User::query()
            ->visibleTo($importer)
            ->whereKey($targetUser->id)
            ->exists();

        if (! $canAccessUser) {
            throw new Exception('User import tidak memiliki akses untuk membuat plan visit username '.$username.'.');
        }

        if (! $outlet->isVisibleTo($importer)) {
            throw new Exception('User import tidak memiliki akses ke outlet '.$kodeOutlet.'.');
        }
    }

    private function importer(): ?User
    {
        if (! $this->importerResolved) {
            $this->importer = $this->userId ? User::with('role')->find($this->userId) : null;
            $this->importerResolved = true;
        }

        return $this->importer;
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

        $this->assertNotFormula($weekValue, 'schedule_week');
        $this->assertNotFormula($yearValue, 'schedule_year');

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
        $now = $this->submittedAt->copy();
        $today = $now->copy()->startOfDay();

        $cutoffTime = self::uploadCutoffTimeFor($today);
        $cutoffLabel = self::uploadCutoffLabel();

        if ($this->scheduleScope === 'weekly') {
            if ($tanggal->isoWeekYear === $now->isoWeekYear && $tanggal->weekOfYear === $now->weekOfYear && $now->gt($cutoffTime)) {
                throw new Exception('Plan minggu '.$tanggal->weekOfYear.' sudah melewati batas cut-off '.$cutoffLabel.'.');
            }

            $minimumAllowedDate = $today->copy()->startOfWeek(Carbon::MONDAY);

            if ($now->gt($cutoffTime)) {
                $minimumAllowedDate = $minimumAllowedDate->addWeek();
            }

            if ($tanggal->lt($minimumAllowedDate)) {
                throw new Exception('Tanggal '.$tanggal->format('Y-m-d').' tidak valid. Minimal '.$minimumAllowedDate->format('Y-m-d').'.');
            }

            return;
        }

        $minimumAllowedDate = $today->copy()->addWeek();

        if ($tanggal->lt($minimumAllowedDate)) {
            throw new Exception('Tanggal '.$tanggal->format('Y-m-d').' tidak valid. Minimal satu minggu dari hari ini (>= '.$minimumAllowedDate->format('Y-m-d').').');
        }

        if ($tanggal->isoWeekYear === $now->isoWeekYear && $tanggal->weekOfYear <= $now->weekOfYear && $now->gt($cutoffTime)) {
            throw new Exception('Plan minggu '.$tanggal->weekOfYear.' sudah melewati batas cut-off '.$cutoffLabel.'.');
        }
    }

    private static function uploadCutoffTimeFor(Carbon $referenceDate): Carbon
    {
        [$hour, $minute] = self::uploadCutoffClock();

        return $referenceDate
            ->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->addDays(self::uploadCutoffDayOffset())
            ->setTime($hour, $minute);
    }

    private static function uploadCutoffDayOffset(): int
    {
        $day = strtolower(trim((string) config('plan_visit.upload_cutoff.day', 'wednesday')));

        if (array_key_exists($day, self::CUTOFF_DAY_OFFSETS)) {
            return self::CUTOFF_DAY_OFFSETS[$day];
        }

        if (is_numeric($day)) {
            $offset = (int) $day;

            if ($offset >= 0 && $offset <= 6) {
                return $offset;
            }
        }

        return self::CUTOFF_DAY_OFFSETS['wednesday'];
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function uploadCutoffClock(): array
    {
        $time = trim((string) config('plan_visit.upload_cutoff.time', '17:00'));

        if (! preg_match('/^(\d{1,2})(?::|\.)(\d{2})$/', $time, $matches)) {
            return [17, 0];
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return [17, 0];
        }

        return [$hour, $minute];
    }

    private function requireValue(array $row, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->sanitizeString($row[$key]);

            if ($value !== null && $value !== '') {
                $this->assertNotFormula($value, $label);

                return $value;
            }
        }

        throw new Exception('Kolom '.$label.' wajib diisi.');
    }

    private function assertNotFormula(?string $value, string $label): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (Str::startsWith(ltrim($value), '=')) {
            throw new Exception('Kolom '.$label.' tidak boleh berisi formula.');
        }
    }

    private function sanitizeString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
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

        return Str::upper($value);
    }

    private function organizationalResolver(): ImportOrganizationalResolver
    {
        return $this->organizationalResolver ??= new ImportOrganizationalResolver;
    }

    private function isBlankImportRow(array $data): bool
    {
        $keys = $this->scheduleScope === 'weekly'
            ? ['username', 'badan_usaha', 'kode_outlet', 'divisi', 'nama_outlet', 'schedule_week', 'schedule_year']
            : ['username', 'badan_usaha', 'kode_outlet', 'divisi', 'nama_outlet', 'tanggal_visit'];

        $values = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $values[] = $data[$key];
            }
        }

        return ! ImportSpreadsheetValidator::rowHasMeaningfulValue($values);
    }

    private function normalizeName(?string $value): string
    {
        return OrganizationalName::normalizeLookup($value);
    }

    private function rememberError(int $rowIndex, array $row, string $message): void
    {
        $columns = [
            'username' => $this->sanitizeString($row['username'] ?? null),
            'badan_usaha' => $this->sanitizeString($row['badan_usaha'] ?? null),
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
            'error_total' => 0,
            'errors' => [],
            'errors_export' => [],
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
        return $this->summaryStore->key();
    }
}
