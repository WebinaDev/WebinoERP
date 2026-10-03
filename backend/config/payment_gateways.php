<?php

/**
 * Iranian payment gateways. Secrets come from the environment or from
 * encrypted IntegrationSetting values. Empty strings stay empty.
 */
return [

    'callback_secret' => env('PAYMENT_CALLBACK_SECRET', ''),

    'callback_base_url' => env('PAYMENT_CALLBACK_BASE_URL', ''),

    'gateways' => [

        'zarinpal' => [
            'label_fa' => 'زرین‌پال',
            'label_en' => 'Zarinpal',
            'supports' => ['cash'],
            'required_live' => ['merchant_id'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => 'https://api.zarinpal.com/pg/v4/payment/',
                'sandbox' => 'https://sandbox.zarinpal.com/pg/v4/payment/',
                'start_production' => 'https://www.zarinpal.com/pg/StartPay/',
                'start_sandbox' => 'https://sandbox.zarinpal.com/pg/StartPay/',
            ],
            'env_fallback' => [
                'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
                'sandbox' => env('ZARINPAL_SANDBOX'),
                'enabled' => env('ZARINPAL_ENABLED'),
            ],
            'fields' => [
                ['key' => 'merchant_id', 'secret' => true, 'label_fa' => 'شناسه پذیرنده', 'label_en' => 'Merchant ID'],
            ],
            'notes_fa' => 'پرداخت نقدی با کارت. مبلغ به ریال (IRR) است. در حالت آزمایشی اگر شناسه پذیرنده ۳۶ کاراکتر نباشد، شبیه‌ساز داخلی بدون تماس با زرین‌پال استفاده می‌شود. برای سندباکس واقعی یک شناسه ۳۶ کاراکتری دلخواه بگذارید.',
        ],

        'snappay' => [
            'label_fa' => 'اسنپ‌پی',
            'label_en' => 'Snapp Pay',
            'supports' => ['installment'],
            'required_live' => ['client_id', 'client_secret', 'username', 'password'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => false,
                'installment_enabled' => true,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => 'https://api.snapppay.ir',
                'sandbox' => '',
            ],
            'env_fallback' => [
                'client_id' => env('SNAPPPAY_CLIENT_ID'),
                'client_secret' => env('SNAPPPAY_CLIENT_SECRET'),
                'username' => env('SNAPPPAY_USERNAME'),
                'password' => env('SNAPPPAY_PASSWORD'),
                'base_url' => env('SNAPPPAY_BASE_URL'),
                'sandbox' => env('SNAPPPAY_SANDBOX'),
                'enabled' => env('SNAPPPAY_ENABLED'),
            ],
            'fields' => [
                ['key' => 'client_id', 'secret' => true, 'label_fa' => 'شناسه مشتری (client_id)', 'label_en' => 'Client ID'],
                ['key' => 'client_secret', 'secret' => true, 'label_fa' => 'رمز مشتری (client_secret)', 'label_en' => 'Client secret'],
                ['key' => 'username', 'secret' => true, 'label_fa' => 'نام کاربری', 'label_en' => 'Username'],
                ['key' => 'password', 'secret' => true, 'label_fa' => 'رمز عبور', 'label_en' => 'Password'],
            ],
            'notes_fa' => 'پرداخت اقساطی. آی‌پی سرور ERP باید در اسنپ‌پی وایت‌لیست شود، وگرنه پاسخ Access Denied می‌آید. آدرس بازگشت باید با دامنه معرفی‌شده به اسنپ‌پی یکی باشد. اگر حالت آزمایشی روشن باشد و آدرس پایه خالی بماند، شبیه‌ساز داخلی استفاده می‌شود. برای استیج، آدرس پایه همان محیط را وارد کنید.',
        ],

        'digipay' => [
            'label_fa' => 'دیجی‌پی',
            'label_en' => 'Digipay',
            'supports' => ['cash', 'installment'],
            'required_live' => ['client_id', 'client_secret', 'username', 'password'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => true,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
                'ticket_type_cash' => 0,
                'ticket_type_installment' => 13,
            ],
            'urls' => [
                'production' => 'https://api.mydigipay.com/digipay/api',
                'sandbox' => 'https://uat.mydigipay.info/digipay/api',
            ],
            'env_fallback' => [
                'client_id' => env('DIGIPAY_CLIENT_ID'),
                'client_secret' => env('DIGIPAY_CLIENT_SECRET'),
                'username' => env('DIGIPAY_USERNAME'),
                'password' => env('DIGIPAY_PASSWORD'),
                'base_url' => env('DIGIPAY_BASE_URL'),
                'sandbox' => env('DIGIPAY_SANDBOX'),
                'enabled' => env('DIGIPAY_ENABLED'),
            ],
            'fields' => [
                ['key' => 'client_id', 'secret' => true, 'label_fa' => 'شناسه مشتری (client_id)', 'label_en' => 'Client ID'],
                ['key' => 'client_secret', 'secret' => true, 'label_fa' => 'رمز مشتری (client_secret)', 'label_en' => 'Client secret'],
                ['key' => 'username', 'secret' => true, 'label_fa' => 'نام کاربری', 'label_en' => 'Username'],
                ['key' => 'password', 'secret' => true, 'label_fa' => 'رمز عبور', 'label_en' => 'Password'],
            ],
            'notes_fa' => 'درگاه UPG دیجی‌کالا (mydigipay)، نه DigiPay کشور دیگر. نقدی با نوع بلیط ۰ (IPG) و اقساطی با نوع ۱۳ (BNPL). محیط آزمایش: uat.mydigipay.info و محیط عملیاتی: api.mydigipay.com. اگر شناسه‌ها خالی باشد شبیه‌ساز داخلی استفاده می‌شود.',
        ],

        'torobpay' => [
            'label_fa' => 'ترب‌پی',
            'label_en' => 'Torob Pay',
            'supports' => ['installment'],
            'required_live' => ['client_id', 'client_secret', 'username', 'password'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => false,
                'installment_enabled' => true,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => 'https://cpg.torobpay.com',
                'sandbox' => '',
            ],
            'env_fallback' => [
                'client_id' => env('TOROBPAY_CLIENT_ID'),
                'client_secret' => env('TOROBPAY_CLIENT_SECRET'),
                'username' => env('TOROBPAY_USERNAME'),
                'password' => env('TOROBPAY_PASSWORD'),
                'base_url' => env('TOROBPAY_BASE_URL'),
                'sandbox' => env('TOROBPAY_SANDBOX'),
                'enabled' => env('TOROBPAY_ENABLED'),
            ],
            'fields' => [
                ['key' => 'client_id', 'secret' => true, 'label_fa' => 'شناسه مشتری (client_id)', 'label_en' => 'Client ID'],
                ['key' => 'client_secret', 'secret' => true, 'label_fa' => 'رمز مشتری (client_secret)', 'label_en' => 'Client secret'],
                ['key' => 'username', 'secret' => true, 'label_fa' => 'نام کاربری', 'label_en' => 'Username'],
                ['key' => 'password', 'secret' => true, 'label_fa' => 'رمز عبور', 'label_en' => 'Password'],
            ],
            'notes_fa' => 'اعتبار و اقساط ترب‌پی. جریان: توکن OAuth، سپس توکن پرداخت، بازگشت، تأیید و تسویه. آدرس پایه پیش‌فرض https://cpg.torobpay.com است و از همین فرم قابل تغییر است. در حالت آزمایشی بدون آدرس پایه، شبیه‌ساز داخلی استفاده می‌شود.',
        ],
    ],
];
