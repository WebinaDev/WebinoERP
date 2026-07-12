# Getting Started

Welcome to **Webina ERM** — a modular ERP/CRM platform built with Laravel 12 and Next.js 14.

## Prerequisites

- **PHP** 8.2+
- **Node.js** 18+
- **PostgreSQL** 14+ (or MySQL 8.0+)
- **Redis** 7+ (cache, queues, sessions)
- **Docker** & **Docker Compose** (recommended)

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/webina/webino-erm.git
cd webino-erm/WebinoERM
```

### 2. Docker (recommended)

```bash
docker compose up -d
```

| Service | URL |
|---------|-----|
| Dashboard (Next.js) | `http://localhost/` |
| API | `http://localhost/api/v1` |
| API docs (Docusaurus) | `http://localhost/docs` |
| API Explorer | `http://localhost/docs/api/explorer/` |

### 3. Backend setup (local)

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1`.

### 4. Frontend setup (local)

```bash
cd frontend
npm install
npm run dev
```

The dashboard will be available at `http://localhost:3000`.

## Project Structure

```
WebinoERM/
├── backend/          # Laravel 12 modular monolith
│   ├── Modules/      # Core, CRM, HRM, Accounting, Projects, …
│   └── config/       # scramble.php, integrations.php
├── frontend/         # Next.js 14.2 dashboard (shadcn/ui)
├── docs-site/        # Docusaurus 3 API docs (FA/EN)
└── docker/           # nginx, postgres, redis configs
```

## Backend modules

| Module | API prefix | Description |
|--------|------------|-------------|
| Core | `core` | Auth, users, settings, chat, logs |
| CRM | `crm` | Leads, accounts, deals, pipelines |
| HRM | `hrm` | Staff, attendance, leave, payroll |
| Finance | `accounting` | Invoices, journal, chart of accounts |
| Projects | `projects` | Projects, tasks, contracts, tickets |
| SCM | `scm` | Warehouse inbound/outbound/audit |
| Sales | `sales` | Catalog, campaigns, invoices |
| Docs | `docs` | Contracts, file manager |
| Marketplace | `marketplace` | Products, modules, releases |
| Integrations | `integrations` | SMS, ModirPayamak, Bale |

## Next Steps

- [Authentication](./authentication) — authenticate with the API
- [Architecture Overview](../architecture/overview) — system design
- [API Explorer](/api/explorer/) — browse REST endpoints
