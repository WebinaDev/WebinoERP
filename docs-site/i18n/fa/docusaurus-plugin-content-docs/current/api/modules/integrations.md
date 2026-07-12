# API یکپارچه‌سازی‌ها (Integrations)

**پایه:** `/api/v1/integrations`  
**احراز هویت:** ترکیبی — webhook عمومی؛ admin با `auth:sanctum`  
**برچسب Explorer:** [INTEGRATIONS](/api/explorer/#tag/INTEGRATIONS)

## خلاصه

SMS، پرداخت، بله، تلگرام، مدیرپیامک.

## مدیرپیامک

| متد | مسیر | توضیح |
|-----|------|-------|
| GET | `/modirpayamak/account` | حساب |
| POST | `/modirpayamak/send` | ارسال SMS |
| POST | `/modirpayamak/topup/init` | شارژ کیف پول |
| GET | `/modirpayamak/reports/outbox` | گزارش outbox |

API ادمین تحت `/modirpayamak/admin`.

فرانت‌اند: `/admin/integrations/modirpayamak/*`
