# SMS in production / پیامک در محیط تولید

---

## English

### Overview

WebinoERM sends OTP codes and transactional SMS through the **Integrations** module. Settings are stored in `integration_settings` (`sms` / `settings`) and mirrored from the admin **Settings → SMS** tab via `PUT /api/v1/integrations/sms/settings`.

OTP login uses the same provider configuration — see [Authentication](./authentication.md).

### Choose a provider

| Provider | `provider` value | Required credentials |
|----------|------------------|----------------------|
| Melipayamak | `melipayamak` | `username`, `password`, optional `sender` |
| ParsGreen | `parsgreen` | `api_key`, optional `sender` |
| Development | `log` or `stub` | None (messages logged only) |
| Disabled | `disabled` | OTP codes are not sent via SMS |

Set the default in `.env`:

```env
SMS_PROVIDER=melipayamak
MELIPAYAMAK_USERNAME=
MELIPAYAMAK_PASSWORD=
MELIPAYAMAK_SENDER=
```

Admin UI or API settings override env defaults when saved.

### Queue worker

Real providers dispatch `SendSmsJob` on the **`sms`** queue:

```bash
php artisan queue:work redis --queue=sms,default
```

Ensure Redis and `QUEUE_CONNECTION=redis` are configured in production.

### OTP flow

1. Client calls `POST /api/v1/core/auth/otp/send` with `{ "mobile": "09..." }`.
2. Backend generates a 6-digit code, caches it for 5 minutes, and queues SMS via `SmsIntegrationController`.
3. Client verifies with `POST /api/v1/core/auth/otp/verify`.

**Security:** `debug_code` is returned **only** when `APP_ENV=local` and `APP_DEBUG=true`. Never rely on it in production.

### Pre-deploy validation

Run before each production deploy:

```bash
php artisan webino:integrations:validate
```

In `APP_ENV=production`, the command exits with code `1` if SMS is still on `log`/`stub`/`disabled` or credentials are missing.

---

## فارسی

### خلاصه

ارسال OTP و پیامک تراکنشی از ماژول **Integrations** انجام می‌شود. تنظیمات در `integration_settings` (کلید `sms` / `settings`) ذخیره می‌شود و از تب **تنظیمات → SMS** یا API `PUT /api/v1/integrations/sms/settings` قابل ویرایش است.

ورود OTP همان تنظیمات را می‌خواند — [Authentication](./authentication.md).

### انتخاب سرویس‌دهنده

| سرویس | مقدار `provider` | فیلدهای لازم |
|--------|------------------|--------------|
| ملی‌پیامک | `melipayamak` | `username`, `password`, `sender` (اختیاری) |
| پارس‌گرین | `parsgreen` | `api_key` |
| توسعه | `log` / `stub` | فقط لاگ |
| غیرفعال | `disabled` | SMS ارسال نمی‌شود |

### صف worker

```bash
php artisan queue:work redis --queue=sms,default
```

### OTP

1. `POST /api/v1/core/auth/otp/send`
2. `POST /api/v1/core/auth/otp/verify`

در production فیلد `debug_code` **هرگز** برگردانده نمی‌شود.

### اعتبارسنجی قبل از deploy

```bash
php artisan webino:integrations:validate
```

در production اگر provider روی `log`/`stub`/`disabled` باشد یا credential ناقص باشد، exit code برابر `1` است.
