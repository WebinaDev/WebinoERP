# مرجع API

مستندات تعاملی API برای **وبینا ERM**.

## آدرس پایه

```
/api/v1
```

## مرورگر API

همه endpointها در [مرورگر API](/api/explorer/) (Redoc).

فایل spec: [`openapi/openapi.json`](/openapi/openapi.json).

## احراز هویت

اکثر endpointها به توکن **Sanctum** نیاز دارند:

```http
Authorization: Bearer {token}
```

توکن از `POST /api/v1/core/auth/login` — [احراز هویت](../guides/authentication).

## پیشوندهای ماژول

| ماژول | پیشوند | برچسب Explorer |
|--------|--------|----------------|
| Core | `/api/v1/core` | CORE |
| CRM | `/api/v1/crm` | CRM |
| HRM | `/api/v1/hrm` | HRM |
| Finance | `/api/v1/accounting` | ACCOUNTING |
| Projects | `/api/v1/projects` | PROJECTS |
| SCM | `/api/v1/scm` | SCM |
| Sales | `/api/v1/sales` | SALES |
| Docs | `/api/v1/docs` | DOCS |
| Marketplace | `/api/v1/marketplace` | MARKETPLACE |
| Integrations | `/api/v1/integrations` | INTEGRATIONS |

راهنمای هر ماژول در **API → Modules**.

## بازتولید spec

```bash
cd backend
composer export-openapi
```

خروجی: `docs-site/openapi/openapi.json`. CI در صورت stale بودن spec شکست می‌خورد.
