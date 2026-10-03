# سایت عمومی وبینا

سایت شرکتی روی همین WebinoERP سرو می‌شود. زبان از سوییچر هدر (کوکی `NEXT_LOCALE`) می‌آید، نه از پیشوند آدرس.

## کدام کانتینر سایت عمومی را نشان می‌دهد

| Compose service | نقش |
| --- | --- |
| `frontend` | Next.js. آلیاس شبکه: `erp-frontend:3000`. **همین کانتینر صفحه اصلی، منو، فوتر و بقیه مسیرهای عمومی را می‌سازد.** |
| `web` | Caddy. تنها سرویسی که پورت میزبان را باز می‌کند. مسیرهای غیر از `/api` را به `erp-frontend` می‌فرستد. |
| `backend` | Laravel. API عمومی `/api/v1/public/*` و CRUD بازاریابی. |

دامنه پیش‌فرض در `docker/caddy/Caddyfile` برابر `webinaagency.ir` است. اگر بعد از آپدیت هنوز ظاهر قبلی را می‌بینید، ایمیج `frontend` بازسازی نشده است. HTML از Next می‌آید و با کش مرورگرِ فایل‌های قدیمی `_next/static` قاطی نمی‌شود، به شرطی که ایمیج جدید ساخته شده باشد.

## بعد از deploy این‌ها را بزنید

`update.sh` مهاجرت می‌زند، سیدر بازاریابی را اجرا می‌کند و کانتینرها را با `--build` بالا می‌آورد. سیدر فقط ردیف‌های تازه را می‌سازد و متنی که قبلاً در داشبورد عوض شده را بازنویسی نمی‌کند.

دستی، از پوشه ریپو:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build frontend web backend
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T backend php artisan db:seed --class='Modules\Marketing\Database\Seeders\MarketingSiteSeeder' --force
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T backend php artisan cache:clear
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T backend php artisan config:clear
```

سپس یک بار صفحه را با بارگذاری تازه باز کنید. داده عمومی Next حدود ۶۰ ثانیه `revalidate` دارد.

نصب اول (`install.sh` یا `php artisan db:seed`) همین `MarketingSiteSeeder` را از `DatabaseSeeder` صدا می‌زند.

## چه چیزی در سیدر خالی پر می‌شود

- پنج ستون خدمات و زیرخدمت‌ها، با همان اسلاگ منوی سایت (`/services/custom-web` و بقیه)
- پنج صنعت راهکار و صفحه‌های زیرمجموعه (`/solutions/retail/fashion` و بقیه)
- برگه درباره ما، محصول‌ها، و برگه‌های حقوقی (متن حقوقی پیش‌فرض، قابل ویرایش)
- چهار محصول خود شرکت در نمونه‌کار: Dashboard، ERP، مستندات، سکوی میزبانی
- پرسش‌های متداول، سه یادداشت بلاگ، یک دوره آکادمی، یک اطلاعیه
- نشان‌های اعتماد نمونه در `branding.trust_badges` (نماد اعتماد رسمی این‌جا جعل نشده)

آمار مشتری، درصد رضایت و نقل‌قول ساختگی در محتوای پیش‌فرض نیست.

## ویرایش از داشبورد

- `/dashboard/marketing/pages`
- `/dashboard/marketing/services` و دسته‌ها
- راهکارها، بلاگ، نمونه‌کار، سوالات، تیم، تنظیمات سایت

نشان اعتماد: در تنظیمات سایت، آرایه `trust_badges` با `label`، `hint`، `href` و `image`. اگر `image` خالی باشد، کاروسل فوتر نشان نمونه را می‌چرخاند.

لوگو: فایل `frontend/public/brand/logo.png` در هدر نشان داده می‌شود، کنار نام سایت. اگر آدرس لوگو خالی یا خراب باشد، نشان هندسی داخل خود هدر جای آن را می‌گیرد تا لوگو گم نشود.

## مسیرهای عمومی

`/`, `/services`, `/services/{slug}`, `/solutions`, `/solutions/{industry}`, `/solutions/{industry}/{slug}`, `/products`, `/portfolio`, `/blog`, `/academy`, `/magazine`, `/faq`, `/about`, `/contact`, `/consultation`, `/proposal`, `/pricing`

## صفحه‌ساز و پوسته‌ساز

همان سند و APIهای WebinoDashboard. جزئیات و فاصله‌های همگام‌سازی در [builder-sync.md](builder-sync.md).

- `/dashboard/builder` — برگه‌ها
- `/dashboard/builder/settings` — رنگ، فونت، تایپوگرافی، دکمه، تصویر، فرم
- `/dashboard/theme-builder` — هدر، فوتر، تکی، بایگانی، جستجو، ۴۰۴
- `/dashboard/marketing/portfolio` — نمونه‌کار، دسته، مطالعه موردی
- `/dashboard/marketing/menus` و `/dashboard/marketing/forms`

تم: `frontend/src/themes/webina-corporate-v1/`. پالت مرکب و زعفرانی (`#16130F`, `#F6F1E8`, `#E4C27A`)، جدا از آبی قبلی.

مخزن WebinoDocs هنگام این نسخه در دسترس نبود. توضیح محصول‌ها از همین ریپو است: Site Builder، ماژول Platform، و docs-site.
