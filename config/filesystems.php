<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', env('FILESYSTEM_DRIVER', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Storage Archive
    |--------------------------------------------------------------------------
    |
    | Old local files can be moved to another disk, such as a NAS/SFTP storage
    | target, while keeping the database path unchanged.
    | URLs resolved through App\Support\StorageDisk and /storage/* requests will
    | fall back to enabled read fallback disks when the source file no longer
    | exists locally. Fallback order follows the array below.
    | The enabled flag controls the scheduled archive job, not read fallback.
    |
    */

    'archive' => [
        'enabled' => env('STORAGE_ARCHIVE_ENABLED', false),
        'source_disk' => env('STORAGE_ARCHIVE_SOURCE_DISK', 'public'),
        'target_disk' => env('STORAGE_ARCHIVE_TARGET_DISK', 'nas_sftp'),
        'read_fallback' => [
            'nas' => [
                'enabled' => env('STORAGE_ARCHIVE_READ_FALLBACK_NAS_ENABLED', false),
                'disk' => env('STORAGE_ARCHIVE_READ_FALLBACK_NAS_DISK', 'nas_sftp'),
            ],
            's3' => [
                'enabled' => env('STORAGE_ARCHIVE_READ_FALLBACK_S3_ENABLED', false),
                'disk' => env('STORAGE_ARCHIVE_READ_FALLBACK_S3_DISK', 's3'),
            ],
        ],
        'older_than_days' => (int) env('STORAGE_ARCHIVE_OLDER_THAN_DAYS', 90),
        'delete_source' => env('STORAGE_ARCHIVE_DELETE_SOURCE', true),
        'directories' => array_values(array_filter(array_map('trim', explode(',', env('STORAGE_ARCHIVE_DIRECTORIES', ''))))),
        'exclude' => array_values(array_filter(array_map('trim', explode(',', env('STORAGE_ARCHIVE_EXCLUDE', '.gitignore,apk/*,livewire-tmp/*'))))),
        'batch_size' => (int) env('STORAGE_ARCHIVE_BATCH_SIZE', 500),
        'schedule_time' => env('STORAGE_ARCHIVE_SCHEDULE_TIME', '02:30'),
        'visibility' => env('STORAGE_ARCHIVE_VISIBILITY', 'private'),
        'temporary_urls' => env('STORAGE_ARCHIVE_TEMPORARY_URLS', true),
        'verify_attempts' => (int) env('STORAGE_ARCHIVE_VERIFY_ATTEMPTS', 5),
        'verify_sleep_ms' => (int) env('STORAGE_ARCHIVE_VERIFY_SLEEP_MS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'visibility' => 'private',
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'nas_sftp' => [
            'driver' => 'sftp',
            'host' => env('NAS_SFTP_HOST'),
            'username' => env('NAS_SFTP_USERNAME'),
            'password' => env('NAS_SFTP_PASSWORD'),
            'privateKey' => env('NAS_SFTP_PRIVATE_KEY'),
            'passphrase' => env('NAS_SFTP_PASSPHRASE'),
            'port' => (int) env('NAS_SFTP_PORT', 22),
            'root' => env('NAS_SFTP_ROOT') ?: '/STORAGE-FORM',
            'timeout' => (int) env('NAS_SFTP_TIMEOUT', 30),
            'url' => env('NAS_SFTP_URL'),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
