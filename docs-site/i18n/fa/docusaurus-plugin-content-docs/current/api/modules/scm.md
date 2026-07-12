# API زنجیره تأمین (SCM)

**پایه:** `/api/v1/scm`  
**احراز هویت:** `auth:sanctum` + `module:scm`  
**برچسب Explorer:** [SCM](/api/explorer/#tag/SCM)

## خلاصه

انبار، موجودی، رسید ورود/خروج، انبارگردانی. از `WarehouseService` به Accounting متصل است.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| GET/POST | `/warehouses` | انبارها |
| GET | `/stock` | موجودی |
| GET/POST | `/inbound` | رسید ورود |
| POST | `/inbound/post` | ثبت نهایی ورود |
| GET/POST | `/outbound` | حواله خروج |
| GET/POST | `/audit` | انبارگردانی |

فرانت‌اند: `/scm/*`
