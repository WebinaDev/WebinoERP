# Projects (PM) API

**Base:** `/api/v1/projects`  
**Auth:** `auth:sanctum` (module license: `projects`)  
**Explorer tag:** [PROJECTS](/api/explorer/#tag/PROJECTS)

## Overview

Project management: projects, tasks, contracts, tickets, sprints, kanban, time tracking, appointments, and invoices.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET/POST | `/projects` | Projects CRUD |
| GET/POST/PATCH/DELETE | `/tasks` | Tasks |
| GET | `/tasks/calendar` | Calendar view |
| GET | `/tasks/gantt` | Gantt data |
| GET/POST | `/contracts` | Contracts |
| POST | `/contracts/{id}/pdf` | Contract PDF |
| GET/POST | `/tickets` | Support tickets |
| GET/POST | `/time-entries` | Time tracking |
| POST | `/time-entries/start` | Start timer |
| GET/POST | `/sprints` | Agile sprints |
| GET | `/kanban/data` | Kanban board |
| GET/POST | `/appointments` | Appointments |
| GET/POST/PATCH/DELETE | `/forms` | Form builder (admin) |

## Public forms

`POST /api/v1/forms/{slug}/submit` is registered separately (throttled, no auth).

## Related frontend

`/pm/projects`, `/pm/tasks`, `/pm/contracts`, `/pm/tickets`, `/pm/time-tracking`
