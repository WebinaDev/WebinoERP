# احراز هویت

وبینا ERM از توکن **Laravel Sanctum** برای دسترسی API استفاده می‌کند.

## ورود با رمز عبور

```http
POST /api/v1/core/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "secret"
}
```

پاسخ شامل `data.token` است — آن را ذخیره کنید و در هدر ارسال کنید:

```http
Authorization: Bearer {token}
```

## ورود با OTP

1. `POST /api/v1/core/auth/otp/send` با `{ "mobile": "09..." }`
2. `POST /api/v1/core/auth/otp/verify` با `{ "mobile": "09...", "code": "123456" }`

در محیط توسعه، کد OTP ممکن است در `debug_code` برگردانده شود.

## مجوز ماژول

مسیرهای `/api/v1/{module}/` به میان‌افزار `module:{slug}` نیاز دارند. ماژول‌های فعال از `system_modules` و لایسنس کاربر resolve می‌شوند.
