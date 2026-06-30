<?php

namespace App\Support;

use App\Jobs\CleanupUploadedImportFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportJobDispatcher
{
    public static function queueSpreadsheetImport(object $import, string $relativePath, string $disk): ImportDispatchResult
    {
        if (self::shouldRunSynchronously()) {
            Excel::import($import, $relativePath, $disk);
            CleanupUploadedImportFile::dispatchSync($disk, $relativePath);

            Log::info('Spreadsheet import completed synchronously', [
                'disk' => $disk,
                'path' => $relativePath,
                'import' => $import::class,
            ]);

            return new ImportDispatchResult(mode: 'sync', queued: false);
        }

        $pendingDispatch = Excel::queueImport($import, $relativePath, $disk);
        $queue = (string) config('imports.queue', 'imports');

        if ($pendingDispatch) {
            $pendingDispatch->onQueue($queue);
            $pendingDispatch->allOnQueue($queue);
            $pendingDispatch->chain([
                new CleanupUploadedImportFile($disk, $relativePath),
            ]);
        }

        return new ImportDispatchResult(mode: 'queued', queued: true);
    }

    public static function shouldRunSynchronously(): bool
    {
        if ((bool) config('imports.force_sync', false)) {
            return true;
        }

        if (config('queue.default') === 'sync') {
            return true;
        }

        if (! (bool) config('imports.sync_fallback', true)) {
            return false;
        }

        return ! self::queueBackendAvailable();
    }

    public static function queueBackendAvailable(): bool
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            return true;
        }

        if ($connection === 'redis') {
            try {
                Redis::connection()->ping();

                return true;
            } catch (Throwable $exception) {
                Log::warning('Redis queue backend unavailable for imports', [
                    'message' => $exception->getMessage(),
                ]);

                return false;
            }
        }

        try {
            Queue::connection($connection);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Queue backend unavailable for imports', [
                'connection' => $connection,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public static function statusMessage(ImportDispatchResult $result): string
    {
        if ($result->isSync()) {
            if (! self::queueBackendAvailable() && config('queue.default') !== 'sync') {
                return 'Import selesai diproses langsung karena antrian worker tidak tersedia. Periksa notifikasi untuk hasil detail.';
            }

            return 'Import selesai diproses. Periksa notifikasi untuk hasil detail.';
        }

        return 'Import sedang diproses di antrian. Anda akan menerima notifikasi setelah selesai.';
    }

    public static function unavailableBlockingMessage(): string
    {
        return 'Antrian import tidak tersedia dan fallback sinkron dinonaktifkan. Pastikan Redis/worker Horizon aktif atau set IMPORT_SYNC_FALLBACK=true.';
    }
}
