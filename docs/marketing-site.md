# سایت عمومی WebinoERM

سایت شرکتی وبینا مستقیماً در WebinoERM میزبانی می‌شود (جایگزین WordPress در webina.dev).

## مسیرها (بدون پیشوند زبان در URL)

زبان از سوییچر بالای صفحه (کوکی `NEXT_LOCALE`) تنظیم می‌شود — نه در آدرس.

| لایه | مسیر | توضیح |
|------|------|-------|
| سایت عمومی SSR | `/`, `/blog`, `/services`, ... | بدون احراز هویت |
| داشبورد ERP | `/dashboard/*` | نیاز به login |
| لاگین | `/login` | عمومی |
| API عمومی | `/api/v1/public/*` | خواندن محتوا |
| API مدیریت | `/api/v1/marketing/*` | CRUD با `auth:sanctum` |

## نصب و پروویژن (از WebinoERP Platform)

پروویژن سایت از **Site Builder** در WebinoERP انجام می‌شود (`/admin/platform/sites`). ماژول **Platform** (SSH + Docker + Caddy) استک WebinoDashboard را deploy می‌کند. کنترل پنل هر سایت در `/admin/platform/sites/{id}` است.

```bash
# از UI: admin/platform/sites/new
# یا API: POST /api/v1/site-builder/provisions
```

Bootstrap خودکار شامل: `migrate`, `db:seed` (شامل `MarketingSiteSeeder`), `storage:link`.

ورود پیش‌فرض: `admin@webina.local` / `password`

### مهاجرت اختیاری WordPress هنگام پروویژن

از Site Builder یا env در پروویژن استفاده کنید:

```json
{"MARKETING_IMPORT_WORDPRESS_URL":"https://webina.dev"}
```

## ماژول Laravel

- مسیر: `backend/Modules/Marketing/`
- migration: `marketing_*` tables
- seeder: `php artisan db:seed --class=Modules\\Marketing\\Database\\Seeders\\MarketingSiteSeeder`

## مهاجرت WordPress (دستی)

```bash
php artisan marketing:import-wordpress --url=https://webina.dev
php artisan marketing:import-wordpress --dry-run
```

نگاشت idempotent با فیلد `wp_id` روی صفحات، پست‌ها و رسانه.

## مدیریت محتوا

از داشبورد ERM:

- `/dashboard/marketing/pages` — برگه‌های CMS
- `/dashboard/marketing/blog` — بلاگ
- `/dashboard/marketing/magazine` — مجله
- `/dashboard/marketing/media` — کتابخانه رسانه
- سایر بخش‌ها: academy, portfolio, faq, services, solutions, team, ...

فرم مشاوره عمومی (`/consultation`) مستقیماً `CrmConsultation` ایجاد می‌کند.

## تم

`frontend/src/themes/webina-corporate-v1/` — رنگ اصلی `#0066FF`، لوگو در `frontend/public/brand/`.
