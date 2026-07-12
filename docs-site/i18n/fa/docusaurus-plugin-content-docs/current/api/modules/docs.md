# API اسناد (Docs)

**پایه:** `/api/v1/docs`  
**احراز هویت:** `auth:sanctum` + `module:docs`  
**برچسب Explorer:** [DOCS](/api/explorer/#tag/DOCS)

## خلاصه

قراردادها و مدیریت فایل: آپلود، دانلود، پوشه، اشتراک، نسخه.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| GET/POST | `/contracts` | قراردادها |
| GET/POST | `/files` | فایل‌ها |
| GET | `/files/{file}/download` | دانلود |
| POST | `/files/folders` | پوشه جدید |
