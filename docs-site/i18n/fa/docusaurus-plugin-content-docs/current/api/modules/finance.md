# API مالی (Finance)

**پایه:** `/api/v1/accounting`  
**احراز هویت:** `auth:sanctum` + `module:accounting`  
**برچسب Explorer:** [ACCOUNTING](/api/explorer/#tag/ACCOUNTING)

## خلاصه

سال مالی، دفتر کل، فاکتور، رسید، چک، گزارش مالی.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| GET | `/summary` | خلاصه داشبورد |
| GET/POST | `/chart` | سرفصل حساب‌ها |
| GET | `/invoices` | فاکتورها |
| GET | `/ledger` | دفتر کل |
| GET | `/reports` | گزارش‌های مالی |

فرانت‌اند: `/finance/*`
