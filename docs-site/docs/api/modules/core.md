# Core API

**Base:** `/api/v1/core`  
**Auth:** Mixed — public auth endpoints; most routes require `auth:sanctum`  
**Explorer tag:** [CORE](/api/explorer/#tag/CORE)

## Overview

Platform foundation: authentication, users, settings, navigation, dashboard, chat, logs, licenses, analytics, and maintenance.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/login` | Password login |
| POST | `/auth/otp/send` | Send login OTP |
| POST | `/auth/otp/verify` | Verify OTP |
| GET | `/auth/user` | Current user (sanctum) |
| GET | `/navigation` | Sidebar menu |
| GET | `/dashboard/stats` | Dashboard KPIs |
| GET/PUT | `/settings` | System settings |
| GET/POST/PATCH/DELETE | `/users` | User CRUD |
| GET/POST | `/chat/channels` | Team chat |
| GET | `/search` | Global search |
| GET | `/analytics/kpi` | Analytics KPI |
| GET/POST | `/licenses` | Module licenses (system_manager) |

## Middleware

- `auth:sanctum` — bearer token required
- `fieldsec:{entity}` — field-level permissions (users, etc.)
- `role:system_manager` — license management

## Related frontend

Shell dashboard, admin settings, profile, notifications.
