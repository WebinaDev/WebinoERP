<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'recaptcha' => [
        'secret' => env('RECAPTCHA_SECRET_KEY'),
        'sitekey' => env('RECAPTCHA_SITE_KEY'),
    ],

    'webino' => [
        'provision_hmac_secret' => env('WEBINO_PROVISION_HMAC_SECRET', ''),
    ],

    'frontend_revalidate' => [
        'url' => env('FRONTEND_REVALIDATE_URL'),
        'secret' => env('REVALIDATE_SECRET'),
    ],

    'public_api_cache' => env('PUBLIC_API_CACHE', true),
    'sentry_dsn' => env('SENTRY_LARAVEL_DSN'),
    'api_rate_limit_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 120),
];
