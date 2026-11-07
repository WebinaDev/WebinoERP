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

        'tara' => [
            'label_fa' => 'تارا',
            'label_en' => 'Tara',
            'supports' => ['installment'],
            'required_live' => ['username', 'password'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => false,
                'installment_enabled' => true,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'base_url',
                    'secret' => false,
                    'label_fa' => 'base_url',
                    'label_en' => 'base_url',
                ],
                [
                    'key' => 'username',
                    'secret' => false,
                    'label_fa' => 'username',
                    'label_en' => 'username',
                ],
                [
                    'key' => 'password',
                    'secret' => true,
                    'label_fa' => 'password',
                    'label_en' => 'password',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'zarinplus' => [
            'label_fa' => 'زرین‌پلاس',
            'label_en' => 'Zarin Plus',
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
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_id',
                    'secret' => true,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
                [
                    'key' => 'access_token',
                    'secret' => true,
                    'label_fa' => 'access_token',
                    'label_en' => 'access_token',
                ],
                [
                    'key' => 'gateway_slug',
                    'secret' => false,
                    'label_fa' => 'gateway_slug',
                    'label_en' => 'gateway_slug',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'parspal' => [
            'label_fa' => 'پارس‌پال',
            'label_en' => 'ParsPal',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'nextpay' => [
            'label_fa' => 'نکست‌پی',
            'label_en' => 'NextPay',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'payping' => [
            'label_fa' => 'پی‌پینگ',
            'label_en' => 'PayPing',
            'supports' => ['cash'],
            'required_live' => ['api_token'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_token',
                    'secret' => true,
                    'label_fa' => 'api_token',
                    'label_en' => 'api_token',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'payir' => [
            'label_fa' => 'پی',
            'label_en' => 'Pay.ir',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'payfa' => [
            'label_fa' => 'پی‌فا',
            'label_en' => 'PayFa',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'vandar' => [
            'label_fa' => 'وندار',
            'label_en' => 'Vandar',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
                [
                    'key' => 'business_name',
                    'secret' => false,
                    'label_fa' => 'business_name',
                    'label_en' => 'business_name',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'bitpay' => [
            'label_fa' => 'بیت‌پی',
            'label_en' => 'BitPay.ir',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'idpay' => [
            'label_fa' => 'آیدی‌پی',
            'label_en' => 'IDPay',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'jibit' => [
            'label_fa' => 'جیبیت',
            'label_en' => 'Jibit',
            'supports' => ['cash'],
            'required_live' => ['api_key', 'secret_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'base_url',
                    'secret' => false,
                    'label_fa' => 'base_url',
                    'label_en' => 'base_url',
                ],
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
                [
                    'key' => 'secret_key',
                    'secret' => true,
                    'label_fa' => 'secret_key',
                    'label_en' => 'secret_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'toman' => [
            'label_fa' => 'تومان',
            'label_en' => 'Toman',
            'supports' => ['cash'],
            'required_live' => [],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
                [
                    'key' => 'client_id',
                    'secret' => false,
                    'label_fa' => 'client_id',
                    'label_en' => 'client_id',
                ],
                [
                    'key' => 'client_secret',
                    'secret' => true,
                    'label_fa' => 'client_secret',
                    'label_en' => 'client_secret',
                ],
                [
                    'key' => 'username',
                    'secret' => false,
                    'label_fa' => 'username',
                    'label_en' => 'username',
                ],
                [
                    'key' => 'password',
                    'secret' => true,
                    'label_fa' => 'password',
                    'label_en' => 'password',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'zibal' => [
            'label_fa' => 'زیبال',
            'label_en' => 'Zibal',
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
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_id',
                    'secret' => true,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'paystar' => [
            'label_fa' => 'پی‌استار',
            'label_en' => 'PayStar',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
                [
                    'key' => 'sign_key',
                    'secret' => true,
                    'label_fa' => 'sign_key',
                    'label_en' => 'sign_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'aghayepardakht' => [
            'label_fa' => 'آقای پرداخت',
            'label_en' => 'Aghaye Pardakht',
            'supports' => ['cash'],
            'required_live' => ['pin'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'pin',
                    'secret' => true,
                    'label_fa' => 'pin',
                    'label_en' => 'pin',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'shepa' => [
            'label_fa' => 'شپا',
            'label_en' => 'Shepa',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'easypay' => [
            'label_fa' => 'ایزی‌پی',
            'label_en' => 'EasyPay',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'parsian' => [
            'label_fa' => 'پارسیان',
            'label_en' => 'Parsian (PEC)',
            'supports' => ['cash'],
            'required_live' => ['login_account'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'login_account',
                    'secret' => true,
                    'label_fa' => 'login_account',
                    'label_en' => 'login_account',
                ],
                [
                    'key' => 'settlement_iban',
                    'secret' => false,
                    'label_fa' => 'settlement_iban',
                    'label_en' => 'settlement_iban',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'saman' => [
            'label_fa' => 'سامان',
            'label_en' => 'Saman (SEP)',
            'supports' => ['cash'],
            'required_live' => ['terminal_id'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'terminal_id',
                    'secret' => false,
                    'label_fa' => 'terminal_id',
                    'label_en' => 'terminal_id',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'mellat' => [
            'label_fa' => 'ملت',
            'label_en' => 'Mellat (Behpardakht)',
            'supports' => ['cash'],
            'required_live' => ['terminal_id', 'username', 'password'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'terminal_id',
                    'secret' => false,
                    'label_fa' => 'terminal_id',
                    'label_en' => 'terminal_id',
                ],
                [
                    'key' => 'username',
                    'secret' => false,
                    'label_fa' => 'username',
                    'label_en' => 'username',
                ],
                [
                    'key' => 'password',
                    'secret' => true,
                    'label_fa' => 'password',
                    'label_en' => 'password',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'sadad' => [
            'label_fa' => 'سداد',
            'label_en' => 'Sadad (Melli)',
            'supports' => ['cash'],
            'required_live' => ['terminal_id', 'merchant_id', 'terminal_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'terminal_id',
                    'secret' => false,
                    'label_fa' => 'terminal_id',
                    'label_en' => 'terminal_id',
                ],
                [
                    'key' => 'merchant_id',
                    'secret' => false,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
                [
                    'key' => 'terminal_key',
                    'secret' => true,
                    'label_fa' => 'terminal_key',
                    'label_en' => 'terminal_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'asanpardakht' => [
            'label_fa' => 'آسان‌پرداخت',
            'label_en' => 'Asan Pardakht',
            'supports' => ['cash'],
            'required_live' => ['merchant_config_id', 'username', 'password'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_config_id',
                    'secret' => false,
                    'label_fa' => 'merchant_config_id',
                    'label_en' => 'merchant_config_id',
                ],
                [
                    'key' => 'username',
                    'secret' => false,
                    'label_fa' => 'username',
                    'label_en' => 'username',
                ],
                [
                    'key' => 'password',
                    'secret' => true,
                    'label_fa' => 'password',
                    'label_en' => 'password',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'pasargad' => [
            'label_fa' => 'پاسارگاد',
            'label_en' => 'Pasargad (PEP)',
            'supports' => ['cash'],
            'required_live' => ['merchant_code', 'terminal_code'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_code',
                    'secret' => false,
                    'label_fa' => 'merchant_code',
                    'label_en' => 'merchant_code',
                ],
                [
                    'key' => 'terminal_code',
                    'secret' => false,
                    'label_fa' => 'terminal_code',
                    'label_en' => 'terminal_code',
                ],
                [
                    'key' => 'username',
                    'secret' => false,
                    'label_fa' => 'username',
                    'label_en' => 'username',
                ],
                [
                    'key' => 'password',
                    'secret' => true,
                    'label_fa' => 'password',
                    'label_en' => 'password',
                ],
                [
                    'key' => 'private_key',
                    'secret' => true,
                    'label_fa' => 'private_key',
                    'label_en' => 'private_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'sepehr' => [
            'label_fa' => 'سپهر',
            'label_en' => 'Sepehr',
            'supports' => ['cash'],
            'required_live' => ['terminal_id'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'terminal_id',
                    'secret' => false,
                    'label_fa' => 'terminal_id',
                    'label_en' => 'terminal_id',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'samankish' => [
            'label_fa' => 'سامان کیش',
            'label_en' => 'Saman Kish',
            'supports' => ['cash'],
            'required_live' => ['terminal_id', 'merchant_id'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'terminal_id',
                    'secret' => false,
                    'label_fa' => 'terminal_id',
                    'label_en' => 'terminal_id',
                ],
                [
                    'key' => 'merchant_id',
                    'secret' => false,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'irankish' => [
            'label_fa' => 'ایران کیش',
            'label_en' => 'Iran Kish',
            'supports' => ['cash'],
            'required_live' => ['terminal_id', 'acceptor_id', 'password', 'public_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'terminal_id',
                    'secret' => false,
                    'label_fa' => 'terminal_id',
                    'label_en' => 'terminal_id',
                ],
                [
                    'key' => 'acceptor_id',
                    'secret' => false,
                    'label_fa' => 'acceptor_id',
                    'label_en' => 'acceptor_id',
                ],
                [
                    'key' => 'password',
                    'secret' => true,
                    'label_fa' => 'password',
                    'label_en' => 'password',
                ],
                [
                    'key' => 'public_key',
                    'secret' => true,
                    'label_fa' => 'public_key',
                    'label_en' => 'public_key',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'saderat' => [
            'label_fa' => 'صادرات',
            'label_en' => 'Saderat (Sepehr)',
            'supports' => ['cash'],
            'required_live' => ['terminal_id'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'terminal_id',
                    'secret' => false,
                    'label_fa' => 'terminal_id',
                    'label_en' => 'terminal_id',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'omidpay' => [
            'label_fa' => 'امیدپی',
            'label_en' => 'OmidPay',
            'supports' => ['cash'],
            'required_live' => ['username', 'password'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'username',
                    'secret' => false,
                    'label_fa' => 'username',
                    'label_en' => 'username',
                ],
                [
                    'key' => 'password',
                    'secret' => true,
                    'label_fa' => 'password',
                    'label_en' => 'password',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'iran_wallet' => [
            'label_fa' => 'کیف پول ایران',
            'label_en' => 'Iran Wallet',
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
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_id',
                    'secret' => false,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
                [
                    'key' => 'national_code',
                    'secret' => false,
                    'label_fa' => 'national_code',
                    'label_en' => 'national_code',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'stripe' => [
            'label_fa' => 'استرایپ',
            'label_en' => 'Stripe',
            'supports' => ['cash'],
            'required_live' => ['publishable_key', 'secret_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'publishable_key',
                    'secret' => false,
                    'label_fa' => 'publishable_key',
                    'label_en' => 'publishable_key',
                ],
                [
                    'key' => 'secret_key',
                    'secret' => true,
                    'label_fa' => 'secret_key',
                    'label_en' => 'secret_key',
                ],
                [
                    'key' => 'webhook_secret',
                    'secret' => true,
                    'label_fa' => 'webhook_secret',
                    'label_en' => 'webhook_secret',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'paypal' => [
            'label_fa' => 'پی‌پال',
            'label_en' => 'PayPal',
            'supports' => ['cash'],
            'required_live' => ['client_id', 'client_secret'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'client_id',
                    'secret' => false,
                    'label_fa' => 'client_id',
                    'label_en' => 'client_id',
                ],
                [
                    'key' => 'client_secret',
                    'secret' => true,
                    'label_fa' => 'client_secret',
                    'label_en' => 'client_secret',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'nowpayments' => [
            'label_fa' => 'NOWPayments',
            'label_en' => 'NOWPayments',
            'supports' => ['cash'],
            'required_live' => ['api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
                [
                    'key' => 'ipn_secret',
                    'secret' => true,
                    'label_fa' => 'ipn_secret',
                    'label_en' => 'ipn_secret',
                ],
                [
                    'key' => 'pay_currency',
                    'secret' => false,
                    'label_fa' => 'pay_currency',
                    'label_en' => 'pay_currency',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'cryptomus' => [
            'label_fa' => 'Cryptomus',
            'label_en' => 'Cryptomus',
            'supports' => ['cash'],
            'required_live' => ['merchant_id', 'api_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_id',
                    'secret' => false,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'coinpayments' => [
            'label_fa' => 'CoinPayments',
            'label_en' => 'CoinPayments',
            'supports' => ['cash'],
            'required_live' => ['public_key', 'private_key', 'merchant_id'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_id',
                    'secret' => false,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
                [
                    'key' => 'public_key',
                    'secret' => false,
                    'label_fa' => 'public_key',
                    'label_en' => 'public_key',
                ],
                [
                    'key' => 'private_key',
                    'secret' => true,
                    'label_fa' => 'private_key',
                    'label_en' => 'private_key',
                ],
                [
                    'key' => 'ipn_secret',
                    'secret' => true,
                    'label_fa' => 'ipn_secret',
                    'label_en' => 'ipn_secret',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'binance_pay' => [
            'label_fa' => 'Binance Pay',
            'label_en' => 'Binance Pay',
            'supports' => ['cash'],
            'required_live' => ['api_key', 'secret_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_id',
                    'secret' => false,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
                [
                    'key' => 'secret_key',
                    'secret' => true,
                    'label_fa' => 'secret_key',
                    'label_en' => 'secret_key',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'perfect_money' => [
            'label_fa' => 'پرفکت‌مانی',
            'label_en' => 'Perfect Money',
            'supports' => ['cash'],
            'required_live' => ['payee_account', 'passphrase'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'payee_account',
                    'secret' => false,
                    'label_fa' => 'payee_account',
                    'label_en' => 'payee_account',
                ],
                [
                    'key' => 'passphrase',
                    'secret' => true,
                    'label_fa' => 'passphrase',
                    'label_en' => 'passphrase',
                ],
                [
                    'key' => 'alternate_passphrase',
                    'secret' => true,
                    'label_fa' => 'alternate_passphrase',
                    'label_en' => 'alternate_passphrase',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'payeer' => [
            'label_fa' => 'پی‌یر',
            'label_en' => 'Payeer',
            'supports' => ['cash'],
            'required_live' => ['merchant_id', 'secret_key'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'merchant_id',
                    'secret' => false,
                    'label_fa' => 'merchant_id',
                    'label_en' => 'merchant_id',
                ],
                [
                    'key' => 'secret_key',
                    'secret' => true,
                    'label_fa' => 'secret_key',
                    'label_en' => 'secret_key',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'coingate' => [
            'label_fa' => 'CoinGate',
            'label_en' => 'CoinGate',
            'supports' => ['cash'],
            'required_live' => ['api_token'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_token',
                    'secret' => true,
                    'label_fa' => 'api_token',
                    'label_en' => 'api_token',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
                [
                    'key' => 'rial_per_unit',
                    'secret' => false,
                    'label_fa' => 'rial_per_unit',
                    'label_en' => 'rial_per_unit',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],

        'ircryptopay' => [
            'label_fa' => 'آی‌آر کریپتو پی',
            'label_en' => 'IRCrypto Pay',
            'supports' => ['cash'],
            'required_live' => ['api_key', 'webhook_secret'],
            'defaults' => [
                'sandbox' => true,
                'cash_enabled' => true,
                'installment_enabled' => false,
                'fee_percent' => 0,
                'apply_fee_on_cash' => false,
            ],
            'urls' => [
                'production' => '',
                'sandbox' => '',
            ],
            'env_fallback' => [],
            'fields' => [
                [
                    'key' => 'api_key',
                    'secret' => true,
                    'label_fa' => 'api_key',
                    'label_en' => 'api_key',
                ],
                [
                    'key' => 'webhook_secret',
                    'secret' => true,
                    'label_fa' => 'webhook_secret',
                    'label_en' => 'webhook_secret',
                ],
                [
                    'key' => 'settle_address',
                    'secret' => false,
                    'label_fa' => 'settle_address',
                    'label_en' => 'settle_address',
                ],
                [
                    'key' => 'currency',
                    'secret' => false,
                    'label_fa' => 'currency',
                    'label_en' => 'currency',
                ],
            ],
            'notes_fa' => 'درگاه هم‌تراز با داشبورد فروشگاهی. در حالت آزمایشی بدون اعتبارنامهٔ کامل از شبیه‌ساز محلی استفاده می‌شود.',
        ],
    ],
];
