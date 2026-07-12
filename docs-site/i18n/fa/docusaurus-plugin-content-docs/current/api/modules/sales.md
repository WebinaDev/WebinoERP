# API فروش (Sales)

**پایه:** `/api/v1/sales`  
**احراز هویت:** `auth:sanctum` + `module:sales`  
**برچسب Explorer:** [SALES](/api/explorer/#tag/SALES)

## خلاصه

کاتالوگ، کمپین، فاکتور با PDF/ایمیل، خدمات اشتراک.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| GET/POST | `/catalog` | کاتالوگ |
| GET/POST | `/campaigns` | کمپین |
| GET/POST | `/invoices` | فاکتور |
| POST | `/invoices/{invoice}/pdf` | PDF |
| POST | `/invoices/{invoice}/email` | ایمیل |
