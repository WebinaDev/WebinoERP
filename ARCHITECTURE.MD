# مستند معماری فنی سیستم جامع Webina Enterprise CRM
**نسخه:** 1.0.0
**توسعه‌دهنده:** شرکت توسعه کسب و کار وبینا
**تاریخ تدوین:** ژانویه 2026

---

## ۱. نمای کلی و اهداف (Overview)
هدف، طراحی و توسعه یک سیستم مدیریت منابع سازمانی (ERP/CRM) کاملاً **ماژولار**، **API-First**، **کانتینری** و **وایت‌لیبل (White-label)** است.
این سیستم بر پایه معماری **Modular Monolith** بنا شده است تا ضمن حفظ یکپارچگی، امکان فعال/غیرفعال‌سازی ماژول‌ها و مدیریت لایسنس برای هر بخش را فراهم کند.

---

## ۲. پشته تکنولوژی (Technology Stack)

### Backend (Server-side)
* **Framework:** Laravel 11.x
* **Architecture:** Modular Monolith (via `nwidart/laravel-modules`)
* **API Standard:** RESTful API (JSON)
* **Database:** PostgreSQL 15+
* **Cache & Queue:** Redis
* **Authentication:** Laravel Sanctum (Stateful for Web / Token for Mobile)
* **Role Management:** Spatie Laravel Permission
* **Documentation:** Scramble / Swagger

### Frontend (Client-side)
* **Framework:** Next.js 14+ (App Router)
* **Language:** TypeScript
* **UI Component Library:** shadcn/ui
* **Styling:** Tailwind CSS (با قابلیت تغییر متغیرها در Runtime)
* **State Management/Data Fetching:** TanStack Query (React Query)
* **Internationalization (i18n):** `next-intl` (پشتیبانی کامل RTL/LTR)
* **Icons:** Lucide React

### DevOps & Infrastructure
* **Containerization:** Docker & Docker Compose
* **Web Server:** Nginx (Reverse Proxy)

---

## ۳. معماری کلان (High-Level Architecture)

سیستم از دو بخش مجزا تشکیل شده است که تنها از طریق API با هم در ارتباط هستند:

1.  **هسته مرکزی (Core Kernel):** مدیریت کاربران، احراز هویت، تنظیمات سراسری، مدیریت ماژول‌ها و لایسنسینگ.
2.  **ماژول‌های تجاری (Business Modules):** CRM، انبار، حسابداری و... که به صورت پلاگین عمل می‌کنند.

### ساختار دایرکتوری Backend
```text
/root
├── app/                  # کدهای پایه و بوت‌استرپ لاراول
├── Modules/              # تمام ماژول‌های سیستم اینجاست
│   ├── Core/             # ماژول حیاتی (User, Auth, Settings, License)
│   ├── CRM/              # ماژول ارتباط با مشتری
│   ├── Accounting/       # ماژول حسابداری
│   ├── Warehouse/        # ماژول انبار
│   └── ...
├── docker/               # کانفیگ‌های داکر
└── composer.json

۴. طراحی دیتابیس و مدیریت ماژول‌ها (Core Module)
این بخش قلب سیستم است که تعیین می‌کند کدام مشتری به چه امکاناتی دسترسی دارد.

۴.۱ جدول ماژول‌ها (system_modules)
این جدول وضعیت لایسنس هر ماژول را نگهداری می‌کند.

SQL

CREATE TABLE system_modules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE, -- نام ماژول: 'CRM', 'Accounting'
    slug VARCHAR(50) NOT NULL UNIQUE, -- شناسه سیستمی
    is_active BOOLEAN DEFAULT FALSE,  -- وضعیت فعلی (روشن/خاموش)
    license_key VARCHAR(255) NULL,    -- کلید لایسنس خریداری شده
    started_at TIMESTAMP NULL,        -- تاریخ شروع لایسنس
    expires_at TIMESTAMP NULL,        -- تاریخ انقضا
    features JSONB NULL,              -- محدودیت‌های خاص لایسنس (مثلاً تعداد کاربر)
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
۴.۲ جدول تنظیمات سیستم (system_settings)
برای مدیریت وایت‌لیبل (White-labeling) و تنظیمات ظاهری.

SQL

CREATE TABLE system_settings (
    key VARCHAR(100) PRIMARY KEY, -- مثال: 'app_name', 'primary_color'
    value TEXT NULL,
    group VARCHAR(50) DEFAULT 'general' -- 'branding', 'email', 'system'
);
نمونه داده‌های جدول تنظیمات:

brand_name: "شرکت فولاد مبارکه"

brand_logo_url: "/storage/logos/client-logo.png"

primary_color: "#0f172a" (کد رنگ سازمانی مشتری)

setup_completed: "true"

۵. معماری فرانت‌اند (Next.js + Shadcn)
۵.۱ ساختار قالب و وایت‌لیبل (Theming)
رنگ‌ها در فایل globals.css هاردکد نمی‌شوند. بلکه از متغیرهای CSS استفاده می‌شود که در زمان اجرا (Runtime) توسط API تنظیم می‌گردند.

درخواست به /api/v1/core/config: فرانت‌اند تنظیمات رنگ و لوگو را دریافت می‌کند.

تزریق به DOM:

TypeScript

// Providers.tsx
useEffect(() => {
  if (config) {
    document.documentElement.style.setProperty('--primary', config.theme.primary);
    document.documentElement.style.setProperty('--radius', config.theme.radius);
  }
}, [config]);
۵.۲ کامپوننت فوتر اجباری (Mandatory Footer)
در فایل Layout اصلی (app/[locale]/layout.tsx)، فوتر به صورت ثابت قرار می‌گیرد و از دیتابیس خوانده نمی‌شود تا غیرقابل تغییر باشد.

TypeScript

// components/layout/Footer.tsx
export function Footer() {
  return (
    <footer className="w-full border-t bg-background py-4 text-center text-sm">
      <div className="container flex flex-col items-center justify-between gap-4 md:flex-row">
        <p className="text-muted-foreground">
            © {new Date().getFullYear()} {clientName}. All rights reserved.
        </p>
        <p className="font-medium text-primary">
            طراحی و توسعه توسط <span className="font-bold">شرکت توسعه کسب و کار وبینا</span>
        </p>
      </div>
    </footer>
  );
}
۵.۳ چندزبانه و راست‌چین (i18n & RTL)
استفاده از next-intl.

پوشه‌بندی: app/[locale]/page.tsx

تشخیص زبان: اگر locale === 'fa' باشد، تگ HTML به صورت <html dir="rtl" lang="fa"> رندر می‌شود.

Shadcn UI: تمامی کامپوننت‌های Shadcn (مانند Sidebar، Dropdown، Dialog) به صورت پیش‌فرض از Flexbox و Grid استفاده می‌کنند و با RTL سازگارند. تنها موارد margin و padding باید از کلاس‌های منطقی (ms-2, me-2, ps-4) استفاده کنند.

۶. فرآیند نصب اولیه (Setup Wizard)
وقتی سیستم برای اولین بار اجرا می‌شود (setup_completed: false)، تمام درخواست‌ها به صفحه /install ریدارکت می‌شوند.

مراحل ویزارد:

خوش‌آمدگویی: نمایش لوگوی وبینا و توضیحات سیستم.

بررسی سرور: چک کردن PHP Extensions، دسترسی نوشتن روی دیسک، اتصال اینترنت.

اتصال دیتابیس: دریافت Host, Port, DB Name, User, Password و تست اتصال. (در این مرحله فایل .env ساخته می‌شود).

شخصی‌سازی (Branding):

نام سازمان مشتری

آپلود لوگوی سازمان

انتخاب رنگ سازمانی (Color Picker)

ایجاد مدیر ارشد: تعریف نام کاربری و رمز عبور برای Super Admin.

پایان: اجرای php artisan migrate --seed، تغییر setup_completed به true و هدایت به صفحه لاگین.

۷. سیستم لایسنس و کنترل ماژول‌ها
۷.۱ Middleware (Backend)
یک Middleware سراسری (CheckModuleLicense) برای روت‌های هر ماژول قرار می‌گیرد.

PHP

// Modules/CRM/Routes/api.php
Route::middleware(['auth:sanctum', 'module:crm'])->group(function () {
    // CRM Routes
});
منطق Middleware:

نام ماژول (crm) را می‌گیرد.

در جدول system_modules چک می‌کند آیا is_active است؟

(اختیاری) چک می‌کند آیا expires_at نگذشته باشد؟

اگر غیرفعال بود، ارور 403 Feature Not Enabled برمی‌گرداند.

۷.۲ Dynamic Sidebar (Frontend)
منوی سمت راست (Sidebar) ثابت نیست.

فرانت‌اند لیست ماژول‌های فعال را از API می‌گیرد (user.active_modules).

اگر CRM فعال بود، آیتم‌های منوی "مشتریان"، "فرصت‌ها" را رندر می‌کند.

۸. زیرساخت داکر (Docker Infrastructure)
فایل docker-compose.yml نهایی برای محیط Production:

YAML

version: '3.8'

services:
  # وب‌سرور اصلی و پروکسی
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/conf.d:/etc/nginx/conf.d
      - ./backend:/var/www/html
    depends_on:
      - app
      - web

  # لاراول (Backend API)
  app:
    build:
      context: ./backend
      dockerfile: Dockerfile
    environment:
      DB_HOST: db
      REDIS_HOST: redis
    volumes:
      - ./backend:/var/www/html
    networks:
      - internal

  # نکست جی‌اس (Frontend Dashboard)
  web:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    environment:
      NEXT_PUBLIC_API_URL: http://nginx/api
    networks:
      - internal

  # دیتابیس
  db:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: webina_crm
      POSTGRES_USER: user
      POSTGRES_PASSWORD: password
    volumes:
      - pgdata:/var/lib/postgresql/data
    networks:
      - internal

  # ردیس (کش و صف)
  redis:
    image: redis:alpine
    networks:
      - internal

networks:
  internal:
    driver: bridge

volumes:
  pgdata:
۹. امنیت و سطوح دسترسی (RBAC)
از پکیج spatie/laravel-permission استفاده می‌شود.

نقش‌های پیش‌فرض (Default Roles)
Super Admin (مدیر سیستم):

دسترسی کامل به تمام ماژول‌ها.

دسترسی به بخش "مدیریت ماژول‌ها" (جهت وارد کردن لایسنس جدید).

دسترسی به تنظیمات برندینگ.

محدودیت: نمی‌تواند کپی‌رایت فوتر را تغییر دهد.

Manager (مدیر میانی):

دسترسی ویرایش و حذف در ماژول‌های فعال.

مشاهده گزارشات.

Staff (کارمند):

دسترسی محدود (معمولاً فقط مشاهده یا ایجاد رکورد).

محدود به رکوردهای خودش (Assigned To Me).

۱۰. استراتژی توسعه (Development Roadmap)
فاز صفر: راه‌اندازی پروژه لاراول + نکست، کانفیگ داکر، پیاده‌سازی ماژول Core و سیستم Authentication.

فاز یک: پیاده‌سازی Setup Wizard و سیستم وایت‌لیبل (Dynamic Theme).

فاز دو: توسعه ماژول CRM (مشتریان، لیدها) به عنوان اولین ماژول تجاری.

فاز سه: تست سیستم لایسنسینگ (خاموش/روشن کردن CRM).