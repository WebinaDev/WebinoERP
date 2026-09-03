# نمای کلی معماری

وبینا ERM یک **مونولیت ماژولار کانتینری** است:

| کانتینر | نقش |
|---------|-----|
| **web** | پروکسی Caddy (`/`, `/api`, `/app`، `/docs`) |
| **frontend** | Next.js 15 SSR (`/admin`) |
| **backend** | API Laravel 13 + Octane/FrankenPHP |
| **docs** | مستندات Docusaurus 3 (FA/EN) |
| **db** | Postgres 15 |
| **redis** | کش، صف، نشست |
| **worker** | worker صف Laravel |
| **scheduler** | `schedule:work` |
| **ws** | Laravel Reverb (`:8081`، چت PM) |

## مرزهای ماژول

هر دامنه ERP یک ماژول Laravel با migration، entity، route و پوشه frontend مربوطه دارد.

## خط لوله مستندات API

1. مسیرهای Laravel در `Modules/*/Routes/api.php`
2. Scramble تولید OpenAPI 3.1 (`composer export-openapi`)
3. Docusaurus راهنما + [مرورگر Redoc](/api/explorer/)
4. CI صحت spec commit‌شده را بررسی می‌کند

## طرح URL

مسیرهای داشبورد ERP با redirectهای legacy. API تحت `/api/v1/{module}/…` نسخه‌بندی شده است.

مسیرهای legacy وردپرس (`/api/webinocrm/v1/*`) از spec اصلی OpenAPI مستثنی هستند.
