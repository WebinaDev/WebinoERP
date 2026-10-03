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
        'base_url' => env('MOADIAN_BASE_URL'),
        'sandbox' => (bool) env('MOADIAN_SANDBOX', false),
        'sandbox_base_url' => env('MOADIAN_SANDBOX_BASE_URL', 'https://sandbox.moadian.ir'),
        'client_id' => env('MOADIAN_CLIENT_ID'),
        'client_secret' => env('MOADIAN_CLIENT_SECRET'),
        'fiscal_id' => env('MOADIAN_FISCAL_ID'),
        'economic_code' => env('MOADIAN_ECONOMIC_CODE'),
        'private_key' => env('MOADIAN_PRIVATE_KEY'),
        'private_key_path' => env('MOADIAN_PRIVATE_KEY_PATH'),
        'retry_times' => (int) env('MOADIAN_RETRY_TIMES', 5),
    ],

    'spam' => [
        'threshold' => (int) env('MAIL_SPAM_THRESHOLD', 3),
        'keywords' => [
            'viagra', 'crypto airdrop', 'click here now', 'winner selected',
            'قرعه کشی', 'برنده شدید', 'هدیه رایگان',
        ],
        'domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('MAIL_SPAM_DOMAINS', ''))))),
    ],

    'cpq' => [
        'approval_percent' => (float) env('CPQ_APPROVAL_PERCENT', 25),
    ],

    'saml' => [
        'certificate' => env('SAML_IDP_CERT'),
        'private_key' => env('SAML_IDP_KEY'),
    ],

    'sandbox' => (bool) env('WEBINO_SANDBOX', false),

    'social' => [
        'instagram_graph' => env('INSTAGRAM_GRAPH_BASE', 'https://graph.facebook.com/v21.0'),
        'linkedin_api' => env('LINKEDIN_API_BASE', 'https://api.linkedin.com'),
    ],

    'elementor' => [
        'secret' => env('WEBINOCRM_ELEMENTOR_SECRET'),
    ],

];
