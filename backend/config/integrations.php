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

    'elementor' => [
        'secret' => env('WEBINOCRM_ELEMENTOR_SECRET'),
    ],

];
