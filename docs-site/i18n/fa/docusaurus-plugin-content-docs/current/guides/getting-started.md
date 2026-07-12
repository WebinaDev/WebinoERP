# شروع کار

به **وبینا ERM** خوش آمدید — پلتفرم ERP/CRM ماژولار با Laravel 12 و Next.js 14.

## پیش‌نیازها

- **PHP** 8.2+
- **Node.js** 18+
- **PostgreSQL** 14+ (یا MySQL 8.0+)
- **Redis** 7+ (کش، صف، نشست)
- **Docker** و **Docker Compose** (پیشنهادی)

## راه‌اندازی سریع

### ۱. کلون مخزن

```bash
git clone https://github.com/webina/webino-erm.git
cd webino-erm/WebinoERM
```

### ۲. Docker (پیشنهادی)

```bash
docker compose up -d
```

| سرویس | آدرس |
|-------|------|
| داشبورد (Next.js) | `http://localhost/` |
| API | `http://localhost/api/v1` |
| مستندات API | `http://localhost/docs` |
| مرورگر API | `http://localhost/docs/api/explorer/` |

### ۳. بک‌اند (محلی)

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

API در `http://localhost:8000/api/v1` در دسترس است.

### ۴. فرانت‌اند (محلی)

```bash
cd frontend
npm install
npm run dev
```

داشبورد در `http://localhost:3000` در دسترس است.

## ساختار پروژه

```
WebinoERM/
├── backend/          # Laravel 12 ماژولار
├── frontend/         # Next.js 14 داشبورد
├── docs-site/        # Docusaurus 3 مستندات FA/EN
└── docker/           # nginx, postgres, redis
```

## ماژول‌های بک‌اند

| ماژول | پیشوند API | توضیح |
|--------|------------|-------|
| Core | `core` | احراز هویت، کاربران، تنظیمات |
| CRM | `crm` | سرنخ، حساب، معاملات |
| HRM | `hrm` | پرسنل، حضور، حقوق |
| Finance | `accounting` | حسابداری، فاکتور |
| Projects | `projects` | پروژه، وظیفه، قرارداد |
| SCM | `scm` | انبار |
| Sales | `sales` | کاتالوگ، کمپین |
| Docs | `docs` | قرارداد، فایل |
| Marketplace | `marketplace` | محصول، ماژول |
| Integrations | `integrations` | SMS، مدیرپیامک |

## گام بعدی

- [احراز هویت](./authentication)
- [معماری](../architecture/overview)
- [مرورگر API](/api/explorer/)
