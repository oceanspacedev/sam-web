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
        'url' => env('WAG_URL', 'https://waghub.mekayastudio.com'),
        'token' => env('WAG_TOKEN'),
        'connect_timeout' => (float) env('WA_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('WA_API_TIMEOUT', 15),
        'country_code' => '62',
        'min_digits' => 10,
        'max_digits' => 15,
    ],

    'whatsapp' => [
        'otp_expires_in' => env('WHATSAPP_OTP_EXPIRES_IN', 60),
        'queue_delay_seconds' => env('WHATSAPP_QUEUE_DELAY_SECONDS', 10),
        'otp_message' => env('WHATSAPP_OTP_MESSAGE', 'Kode OTP SAM Anda: {otp}. Berlaku 1 menit. Jangan bagikan kode ini kepada siapa pun.'),
        'account_registered_message' => env('WHATSAPP_ACCOUNT_REGISTERED_MESSAGE', "Halo {name}, nomor WhatsApp Anda sudah terdaftar di SAM dan bisa digunakan untuk login aplikasi.\n\n{download_links}"),
    ],

    'sam_app' => [
        'android_url' => env('SAM_ANDROID_DOWNLOAD_URL'),
        'ios_testflight_url' => env('SAM_IOS_TESTFLIGHT_URL'),
    ],

];
