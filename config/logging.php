<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'custom' => [
            'driver' => 'single',
            'path' => storage_path('logs/custom.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        // Separate log channels for better observability
        'visit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/visit.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30, // Keep logs for 30 days
        ],

        'lead' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lead.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'noo' => [
            'driver' => 'daily',
            'path' => storage_path('logs/noo.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'api_errors' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api-errors.log'),
            'level' => 'error', // Only log errors and above
            'days' => 60, // Keep error logs longer
        ],

        'outlet' => [
            'driver' => 'daily',
            'path' => storage_path('logs/outlet.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'planvisit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/planvisit.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
    ],

];
