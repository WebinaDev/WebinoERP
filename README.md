# Webina Enterprise CRM

سیستم مدیریت منابع سازمانی (ERP/CRM) کاملاً **ماژولار**، **API-First**، **کانتینری** و **وایت‌لیبل (White-label)**.

## ساختار پروژه

```
webina-enterprise/
├── backend/          # Laravel 13 + Octane/FrankenPHP (nwidart Modules/)
├── frontend/         # Next.js 15 App Router (`src/app`, `/admin`)
├── docker/           # Caddy, PHP, Next Dockerfiles
└── docker-compose.yml
```

## پیش‌نیازها

- Docker & Docker Compose
- Node.js 18+ (برای local development)
- PHP 8.2+ & Composer (برای local development)

## راه‌اندازی سریع

### با Docker (توصیه می‌شود)

```bash
# Clone repository
git clone <repository-url>
cd Webino

# Copy environment files
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env

# Start services
docker compose up -d

# Run migrations
docker compose exec backend php artisan migrate

# Seed database
docker compose exec backend php artisan db:seed
```

### بدون Docker (Development)

#### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

#### Frontend

```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

## دسترسی

- **Frontend:** http://localhost:3080 (یا `WEB_HTTP_PORT`)
- **Admin:** http://localhost:3080/admin
- **Backend API:** http://localhost:3080/api (پیشوند ماژول‌ها: `/api/v1/core`, `/api/v1/crm`, `/api/v1/projects`, …)

## مستندات

- [معماری سیستم](ARCHITECTURE.MD)
- [مستندات ماژول CRM](CRM.MD)
- [مستندات API (مرجع)](REVIEW_API_SPECIFICATION.md)
- [نگاشت AJAX وردپرس به REST](backend/docs/AJAX_TO_API_INVENTORY.md)
- [وضعیت مسیرهای API در برابر inventory](backend/docs/API_ROUTE_VERIFICATION.md)

برای فهرست عملی endpointها، فایل‌های `Routes/api.php` در `backend/Modules/*/Routes/` و `backend/routes/api.php` را ببینید. مستندات Swagger/OpenAPI به‌صورت پیش‌فرض در این repo نصب نشده است.

## توسعه

برای اطلاعات بیشتر در مورد توسعه، به مستندات معماری مراجعه کنید.

## لایسنس

© 2026 شرکت توسعه کسب و کار وبینا. تمامی حقوق محفوظ است.

