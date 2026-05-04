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
    | Old local files can be moved to another disk, such as MinIO or any
    | S3-compatible object storage, while keeping the database path unchanged.
    | URLs resolved through App\Support\StorageDisk will fall back to this
    | target disk when the source file no longer exists locally.
    |
    */

    'archive' => [
        'enabled' => env('STORAGE_ARCHIVE_ENABLED', false),
        'source_disk' => env('STORAGE_ARCHIVE_SOURCE_DISK', 'public'),
        'target_disk' => env('STORAGE_ARCHIVE_TARGET_DISK', 's3'),
        'older_than_days' => (int) env('STORAGE_ARCHIVE_OLDER_THAN_DAYS', 90),
        'delete_source' => env('STORAGE_ARCHIVE_DELETE_SOURCE', true),
        'directories' => array_values(array_filter(array_map('trim', explode(',', env('STORAGE_ARCHIVE_DIRECTORIES', ''))))),
        'exclude' => array_values(array_filter(array_map('trim', explode(',', env('STORAGE_ARCHIVE_EXCLUDE', '.gitignore,apk/*,livewire-tmp/*'))))),
        'batch_size' => (int) env('STORAGE_ARCHIVE_BATCH_SIZE', 500),
        'schedule_time' => env('STORAGE_ARCHIVE_SCHEDULE_TIME', '02:30'),
        'visibility' => env('STORAGE_ARCHIVE_VISIBILITY', 'private'),
        'temporary_urls' => env('STORAGE_ARCHIVE_TEMPORARY_URLS', true),
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
    | Supported Drivers: "local", "ftp", "sftp", "s3"
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

        'nas_ftp' => [
            'driver' => 'ftp',
            'host' => env('NAS_FTP_HOST'),
            'username' => env('NAS_FTP_USERNAME'),
            'password' => env('NAS_FTP_PASSWORD'),
            'port' => (int) env('NAS_FTP_PORT', 21),
            'root' => env('NAS_FTP_ROOT', '/'),
            'passive' => env('NAS_FTP_PASSIVE', true),
            'ssl' => env('NAS_FTP_SSL', false),
            'timeout' => (int) env('NAS_FTP_TIMEOUT', 30),
            'url' => env('NAS_FTP_URL'),
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
