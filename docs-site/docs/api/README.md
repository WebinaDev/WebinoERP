# API Reference

Interactive API documentation for **WebinoERM**.

## Base URL

```
/api/v1
```

## Explorer

Browse all endpoints in the [API Explorer](/api/explorer/) (Redoc UI).

Download the machine-readable spec: [`openapi/openapi.json`](/openapi/openapi.json).

## Authentication

Most endpoints require a **Laravel Sanctum** bearer token:

```http
Authorization: Bearer {token}
```

Obtain a token via `POST /api/v1/core/auth/login` or OTP flows — see [Authentication](../guides/authentication).

## Module prefixes

| Module | Prefix | Explorer tag |
|--------|--------|--------------|
| Core | `/api/v1/core` | CORE |
| CRM | `/api/v1/crm` | CRM |
| HRM | `/api/v1/hrm` | HRM |
| Finance | `/api/v1/accounting` | ACCOUNTING |
| Projects (PM) | `/api/v1/projects` | PROJECTS |
| SCM | `/api/v1/scm` | SCM |
| Sales | `/api/v1/sales` | SALES |
| Docs | `/api/v1/docs` | DOCS |
| Marketplace | `/api/v1/marketplace` | MARKETPLACE |
| Integrations | `/api/v1/integrations` | INTEGRATIONS |

Per-module guides are in the sidebar under **API → Modules**.

## Regenerating the OpenAPI spec

When [Scramble](https://scramble.dedoc.co/) is installed in the Laravel backend:

```bash
cd backend
composer install
composer export-openapi
```

This writes `docs-site/openapi/openapi.json`. CI fails if the committed spec is stale relative to route changes.

## Response format

All API responses use JSON with a consistent envelope (`data`, `meta`, `message`) via the `ApiResponseFormatter` middleware.
