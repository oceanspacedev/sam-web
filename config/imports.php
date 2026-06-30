<?php

return [

    'queue' => env('IMPORT_QUEUE', 'imports'),

    /*
    |--------------------------------------------------------------------------
    | Synchronous import fallback
    |--------------------------------------------------------------------------
    |
    | When queue workers / Redis are unavailable, imports can run inline so
    | operators are not stuck with a false "sedang diproses" message.
    |
    */
    'force_sync' => (bool) env('IMPORT_FORCE_SYNC', false),

    'sync_fallback' => (bool) env('IMPORT_SYNC_FALLBACK', true),

    'summary_ttl_minutes' => (int) env('IMPORT_SUMMARY_TTL_MINUTES', 120),

    'summary_error_export_limit' => (int) env('IMPORT_SUMMARY_ERROR_EXPORT_LIMIT', 2000),

];
