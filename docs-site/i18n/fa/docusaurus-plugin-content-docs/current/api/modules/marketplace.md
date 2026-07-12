# API بازارگاه (Marketplace)

**پایه:** `/api/v1/marketplace`  
**احراز هویت:** `auth:sanctum` + `module:marketplace`  
**برچسب Explorer:** [MARKETPLACE](/api/explorer/#tag/MARKETPLACE)

## خلاصه

محصول، دسته، سفارش، Gitea، ماژول و انتشار release.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| GET/POST | `/products` | محصولات |
| GET/POST | `/modules` | ماژول‌های ERP |
| POST | `/modules/{module}/repo/sync` | همگام‌سازی repo |
| GET/POST | `/modules/{module}/releases` | انتشارها |
