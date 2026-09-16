<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class EnvExampleLaravel12Test extends TestCase
{
    public function test_env_example_only_contains_env_keys_this_codebase_reads(): void
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env.example';

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertNotSame('', trim($contents));

        $liveKeys = $this->liveKeys($contents);
        $liveAssignments = $this->liveAssignments($contents);
        $usedKeys = $this->envKeysReferencedInCode();

        foreach ($liveKeys as $key) {
            if (str_starts_with($key, 'VITE_') || in_array($key, ['BROADCAST_CONNECTION'], true)) {
                continue;
            }

            $this->assertContains(
                $key,
                $usedKeys,
                ".env.example live key [{$key}] is not read via env() in app/ or config/",
            );
        }

        $legacyKeys = [
            'CACHE_DRIVER',
            'BROADCAST_DRIVER',
            'FILESYSTEM_DRIVER',
            'MAIL_ENCRYPTION',
            'WAHA_API_ENDPOINT',
            'WAHA_BASE_URL',
            'WAHA_API_KEY',
            'WAHA_SESSION',
            'FONNTE_TOKEN',
            'FONNTE_ENDPOINT',
            'FONNTE_API_ENDPOINT',
            'WHATSAPP_GATEWAY_PROVIDER',
            'WHATSAPP_GATEWAY_WAHA_BASE_URL',
            'WHATSAPP_GATEWAY_FONNTE_TOKEN',
            'NAS_SFTP_HOST',
            'STORAGE_ARCHIVE_ENABLED',
        ];

        foreach ($legacyKeys as $key) {
            $this->assertNotContains(
                $key,
                $liveKeys,
                ".env.example must not keep unused/legacy live key [{$key}]",
            );
        }

        $requiredKeys = [
            'APP_NAME',
            'APP_ENV',
            'APP_KEY',
            'APP_DEBUG',
            'APP_URL',
            'APP_LOCALE',
            'APP_FALLBACK_LOCALE',
            'APP_FAKER_LOCALE',
            'APP_MAINTENANCE_DRIVER',
            'BCRYPT_ROUNDS',
            'LOG_CHANNEL',
            'LOG_STACK',
            'LOG_DEPRECATIONS_CHANNEL',
            'LOG_LEVEL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'SESSION_DRIVER',
            'SESSION_LIFETIME',
            'SESSION_ENCRYPT',
            'SESSION_PATH',
            'BROADCAST_CONNECTION',
            'FILESYSTEM_DISK',
            'QUEUE_CONNECTION',
            'QUEUE_RETRY_AFTER',
            'CACHE_STORE',
            'MEMCACHED_HOST',
            'HORIZON_PATH',
            'LOG_VIEWER_ENABLED',
            'LOG_VIEWER_PATH',
            'REDIS_CLIENT',
            'REDIS_HOST',
            'REDIS_PASSWORD',
            'REDIS_PORT',
            'MAIL_MAILER',
            'MAIL_SCHEME',
            'MAIL_HOST',
            'MAIL_PORT',
            'MAIL_USERNAME',
            'MAIL_PASSWORD',
            'MAIL_FROM_ADDRESS',
            'MAIL_FROM_NAME',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_DEFAULT_REGION',
            'AWS_BUCKET',
            'AWS_URL',
            'AWS_ENDPOINT',
            'AWS_USE_PATH_STYLE_ENDPOINT',
            'PLAN_VISIT_UPLOAD_CUTOFF_DAY',
            'PLAN_VISIT_UPLOAD_CUTOFF_TIME',
            'IMPORT_SYNC_FALLBACK',
            'IMPORT_FORCE_SYNC',
            'IMPORT_SUMMARY_TTL_MINUTES',
            'ONESIGNAL_APP_ID',
            'WAG_URL',
            'WAG_TOKEN',
            'WA_CONNECT_TIMEOUT',
            'WA_API_TIMEOUT',
            'WHATSAPP_OTP_EXPIRES_IN',
            'WHATSAPP_QUEUE_DELAY_SECONDS',
            'SAM_ANDROID_DOWNLOAD_URL',
            'SAM_IOS_TESTFLIGHT_URL',
            'OCTANE_SERVER',
            'OCTANE_HTTPS',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertContains(
                $key,
                $liveKeys,
                ".env.example must include used key [{$key}]",
            );
        }

        $this->assertSame(
            'redis',
            $liveAssignments['QUEUE_CONNECTION'] ?? null,
            '.env.example must keep QUEUE_CONNECTION=redis for Horizon',
        );
    }

    /**
     * @return list<string>
     */
    private function liveKeys(string $contents): array
    {
        return array_keys($this->liveAssignments($contents));
    }

    /**
     * @return array<string, string>
     */
    private function liveAssignments(string $contents): array
    {
        $assignments = [];

        foreach (preg_split('/\R/', $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
                continue;
            }

            $assignments[$matches[1]] = $matches[2];
        }

        return $assignments;
    }

    /**
     * @return list<string>
     */
    private function envKeysReferencedInCode(): array
    {
        $root = dirname(__DIR__, 2);
        $keys = [];

        foreach (['app', 'config', 'bootstrap'] as $directory) {
            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($root.DIRECTORY_SEPARATOR.$directory)
                ),
                '/\.php$/',
            );

            foreach ($iterator as $file) {
                $contents = file_get_contents($file->getPathname());

                if ($contents === false) {
                    continue;
                }

                if (preg_match_all("/env\\(\\s*['\"]([A-Z][A-Z0-9_]+)['\"]/", $contents, $matches) !== false) {
                    foreach ($matches[1] as $key) {
                        $keys[$key] = true;
                    }
                }
            }
        }

        return array_keys($keys);
    }
}
