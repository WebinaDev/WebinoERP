# نمای کلی معماری

وبینا ERM یک **مونولیت ماژولار کانتینری** است:

| کانتینر | نقش |
|---------|-----|
| **nginx** | پروکسی معکوس (`/`, `/api`, `/docs`) |
| **frontend** | داشبورد Next.js 14 |
| **backend** | API Laravel 12 |
| **docs** | مستندات Docusaurus 3 (FA/EN) |
| **postgres** | پایگاه داده |
| **redis** | کش، صف، نشست |
| **queue** | worker صف Laravel |
| **reverb** | WebSocket (چت PM) |

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
