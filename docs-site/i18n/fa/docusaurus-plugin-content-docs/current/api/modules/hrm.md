# API منابع انسانی (HRM)

**پایه:** `/api/v1/hrm`  
**احراز هویت:** `auth:sanctum` + `module:hrm`  
**برچسب Explorer:** [HRM](/api/explorer/#tag/HRM)

## خلاصه

پرسنل، حضور، مرخصی، حقوق، استخدام، عملکرد و آموزش.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| GET/POST | `/staff` | لیست / ایجاد پرسنل |
| POST | `/attendance/check-in` | ورود |
| GET/POST | `/leave/requests` | درخواست مرخصی |
| GET/POST | `/payroll/runs` | اجرای حقوق |
| POST | `/payroll/runs/{run}/calculate` | محاسبه حقوق |

فرانت‌اند: `/hrm/staff`, `/hrm/payroll`, `/hrm/attendance`
