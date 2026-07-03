<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'onesignal' => [
        'app_id' => env('ONESIGNAL_APP_ID', '787d6428-2b70-463d-a858-eec955e1a922'),
    ],

    'whatsapp_gateway' => [
        'provider' => env('WHATSAPP_GATEWAY_PROVIDER', 'waha'),
        'fallback_provider' => env('WHATSAPP_GATEWAY_FALLBACK_PROVIDER', 'fonnte'),
        'fallback_enabled' => env('WHATSAPP_GATEWAY_FALLBACK_ENABLED', true),
        'waha' => [
            'base_url' => env('WHATSAPP_GATEWAY_WAHA_BASE_URL', env('WAHA_API_ENDPOINT', env('WAHA_BASE_URL', 'http://localhost:3000'))),
            'api_key' => env('WHATSAPP_GATEWAY_WAHA_API_KEY', env('WAHA_API_KEY')),
            'session' => env('WHATSAPP_GATEWAY_WAHA_SESSION', env('WAHA_SESSION', 'default')),
        ],
        'fonnte' => [
            'endpoint' => env('WHATSAPP_GATEWAY_FONNTE_ENDPOINT', env('FONNTE_API_ENDPOINT', env('FONNTE_ENDPOINT', 'https://api.fonnte.com/send'))),
            'token' => env('WHATSAPP_GATEWAY_FONNTE_TOKEN', env('FONNTE_TOKEN')),
        ],
        'country_code' => env('WHATSAPP_GATEWAY_COUNTRY_CODE', env('WHATSAPP_COUNTRY_CODE', env('FONNTE_COUNTRY_CODE', '62'))),
        'timeout' => env('WHATSAPP_GATEWAY_TIMEOUT', 15),
        'min_seconds_between_sends' => env('WHATSAPP_GATEWAY_MIN_SECONDS_BETWEEN_SENDS', 3),
        'min_digits' => env('WHATSAPP_GATEWAY_MIN_DIGITS', 10),
        'max_digits' => env('WHATSAPP_GATEWAY_MAX_DIGITS', 15),
    ],

    'whatsapp' => [
        'otp_expires_in' => env('WHATSAPP_OTP_EXPIRES_IN', env('FONNTE_OTP_EXPIRES_IN', 60)),
        'queue_delay_seconds' => env('WHATSAPP_QUEUE_DELAY_SECONDS', env('FONNTE_QUEUE_DELAY_SECONDS', 10)),
        'otp_message' => env('WHATSAPP_OTP_MESSAGE', env('FONNTE_OTP_MESSAGE', 'Kode OTP SAM Anda: {otp}. Berlaku 1 menit. Jangan bagikan kode ini kepada siapa pun.')),
        'account_registered_message' => env('WHATSAPP_ACCOUNT_REGISTERED_MESSAGE', env('FONNTE_ACCOUNT_REGISTERED_MESSAGE', "Halo {name}, nomor WhatsApp Anda sudah terdaftar di SAM dan bisa digunakan untuk login aplikasi.\n\n{download_links}")),
    ],

    'sam_app' => [
        'android_url' => env('SAM_ANDROID_DOWNLOAD_URL'),
        'ios_testflight_url' => env('SAM_IOS_TESTFLIGHT_URL'),
    ],

];
