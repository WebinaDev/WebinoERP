# API هسته (Core)

**پایه:** `/api/v1/core`  
**احراز هویت:** ترکیبی — endpointهای auth عمومی؛ بقیه `auth:sanctum`  
**برچسب Explorer:** [CORE](/api/explorer/#tag/CORE)

## خلاصه

پایه پلتفرم: احراز هویت، کاربران، تنظیمات، ناوبری، داشبورد، چت، لاگ، لایسنس و نگهداری.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| POST | `/auth/login` | ورود با رمز |
| POST | `/auth/otp/send` | ارسال OTP |
| GET | `/auth/user` | کاربر جاری |
| GET | `/navigation` | منوی سایدبار |
| GET/PUT | `/settings` | تنظیمات سیستم |
| GET/POST | `/chat/channels` | چت تیمی |
| GET | `/search` | جستجوی سراسری |
