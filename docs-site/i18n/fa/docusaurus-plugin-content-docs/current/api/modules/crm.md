# API مدیریت ارتباط با مشتری (CRM)

**پایه:** `/api/v1/crm`  
**احراز هویت:** `auth:sanctum` + `module:crm`  
**برچسب Explorer:** [CRM](/api/explorer/#tag/CRM)

## خلاصه

سرنخ، حساب، معامله، پایپ‌لاین، مخاطب، منبع و فعالیت.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| GET/POST | `/leads` | سرنخ‌ها |
| PATCH | `/deals/{deal}/move` | جابجایی مرحله معامله |
| GET | `/pipelines/{pipeline}/kanban` | داده کانبان |
| GET/POST | `/accounts` | حساب‌های CRM |

فرانت‌اند: `/crm/leads`, `/crm/deals`, `/crm/pipelines`
