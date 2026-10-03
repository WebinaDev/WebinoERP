<?php

return [

    'sms' => [
        'default' => env('SMS_PROVIDER', 'stub'),
    ],

    'payment' => [
        'default' => env('PAYMENT_PROVIDER', 'stub'),
    ],

    'bale' => [
        'token' => env('BALE_BOT_TOKEN'),
        'webhook_secret' => env('BALE_WEBHOOK_SECRET'),
    ],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_CALENDAR_REDIRECT'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'tenant' => env('MICROSOFT_TENANT', 'common'),
        'redirect' => env('MICROSOFT_CALENDAR_REDIRECT'),
    ],

    'moadian' => [
        'hook_url' => env('MOADIAN_HOOK_URL'),
    ],

    'elementor' => [
        'secret' => env('WEBINOCRM_ELEMENTOR_SECRET'),
    ],

];
