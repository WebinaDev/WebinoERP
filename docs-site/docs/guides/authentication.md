# Authentication

WebinoERM uses **Laravel Sanctum** bearer tokens for API access.

## Password login

```http
POST /api/v1/core/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "secret"
}
```

Response includes `data.token` — store it client-side and send as:

```http
Authorization: Bearer {token}
```

## OTP login

1. `POST /api/v1/core/auth/otp/send` with `{ "mobile": "09..." }`
2. `POST /api/v1/core/auth/otp/verify` with `{ "mobile": "09...", "code": "123456" }`

In local/dev environments, the OTP code may be returned in `debug_code` or logged server-side.

For production SMS setup (provider, queue worker, validation command), see [SMS in production](./sms-production.md).

## Module licensing

Routes under `/api/v1/{module}/` require the `module:{slug}` middleware. Active modules are resolved from `system_modules` and user licenses.
