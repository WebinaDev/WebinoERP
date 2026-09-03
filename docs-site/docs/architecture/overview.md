# Architecture overview

WebinoERM is a **containerized modular monolith**:

| Container | Role |
|-----------|------|
| **web** | Caddy reverse proxy (`/`, `/api`, `/app` WebSocket, `/docs`) |
| **frontend** | Next.js 15 SSR (`/admin`) |
| **backend** | Laravel 13 API + Octane/FrankenPHP (`nwidart/laravel-modules`) |
| **docs** | Docusaurus 3 static API docs (FA/EN) |
| **db** | Postgres 15 |
| **redis** | Cache, queue, sessions |
| **worker** | Laravel queue worker |
| **scheduler** | `schedule:work` |
| **ws** | Laravel Reverb WebSocket (`:8081`, PM chat) |

## Module boundaries

Each ERP domain is a Laravel module with its own migrations, entities, routes, and a matching frontend feature folder under `frontend/features/modules/`.

| Backend module | Frontend route prefix |
|----------------|----------------------|
| Core | `/admin`, shell, profile |
| HRM | `/hrm/*` |
| Finance (Accounting) | `/finance/*` |
| CRM | `/crm/*` |
| Projects (PM) | `/pm/*` |
| SCM | `/scm/*` |
| Sales | `/sales/*` |
| Docs | `/docs/*` |
| Marketplace | `/admin/marketplace/*` |
| Integrations | `/admin/integrations/*` |

## API documentation pipeline

1. Laravel routes in `Modules/*/Routes/api.php`
2. [Scramble](https://scramble.dedoc.co/) generates OpenAPI 3.1 (`composer export-openapi`)
3. Docusaurus serves guides + [Redoc explorer](/api/explorer/)
4. CI verifies the committed spec matches live routes

## URL scheme

Dashboard routes follow ERP paths with legacy redirects (e.g. `/staff` → `/hrm/staff`). API routes are versioned under `/api/v1/{module}/…`.

Legacy WordPress parity routes (`/api/webinocrm/v1/*`) exist for migration but are excluded from the primary OpenAPI spec.
