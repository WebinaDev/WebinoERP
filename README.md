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

### نصب روی سرور (یک دستور)

پیش‌نیاز: **Linux** با `sudo`. اسکریپت خودش نصب می‌کند: `git`, `curl`, **Docker Engine**, **Compose plugin** — سپس ERP را کلون و بالا می‌آورد.

```bash
curl -fsSL https://raw.githubusercontent.com/WebinaDev/WebinoERP/main/install.sh | bash
```

یا با پورت/مسیر سفارشی:

```bash
curl -fsSL https://raw.githubusercontent.com/WebinaDev/WebinoERP/main/install.sh \
  | INSTALL_DIR=/opt/webina WEB_HTTP_PORT=3080 APP_URL=http://YOUR_SERVER_IP:3080 bash
```

اگر `install.sh` هنوز روی `main` نیست:

```bash
sudo mkdir -p /opt/webina && sudo chown "$USER" /opt/webina
git clone --depth 1 https://github.com/WebinaDev/WebinoERP.git /tmp/WebinoERP-src
bash /tmp/WebinoERP-src/install.sh
```

بعد از نصب: **Admin** `http://SERVER:3080/admin` — ورود `admin@webina.local` / `password`

### با Docker (دستی)

```bash
git clone https://github.com/WebinaDev/WebinoERP.git
cd WebinoERP

cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env

docker compose run --rm --no-deps --entrypoint composer backend install --no-interaction
docker compose run --rm --no-deps --entrypoint php backend artisan key:generate --force
docker compose up -d --build
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed --force
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

