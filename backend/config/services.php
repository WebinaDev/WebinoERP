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

];
