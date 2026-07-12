# CRM API

**Base:** `/api/v1/crm`  
**Auth:** `auth:sanctum` (module license: `crm`)  
**Explorer tag:** [CRM](/api/explorer/#tag/CRM)

## Overview

Customer relationship management: leads, accounts, deals, pipelines, contacts, sources, and activities.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET/POST | `/leads` | List / create leads |
| PATCH | `/leads/{id}/status` | Change lead status |
| POST | `/leads/{id}/convert` | Convert lead |
| GET/POST | `/accounts` | CRM accounts |
| GET | `/accounts/{id}/360` | Account 360 view |
| GET/POST/PATCH/DELETE | `/deals` | Deals CRUD |
| PATCH | `/deals/{deal}/move` | Move deal stage |
| GET/POST | `/pipelines` | Sales pipelines |
| GET | `/pipelines/{pipeline}/kanban` | Kanban board data |
| GET/POST | `/contacts` | Contacts |
| GET/POST | `/sources` | Lead sources |
| GET/POST | `/activities` | CRM activities |

## Middleware

- `fieldsec:lead`, `fieldsec:account` — field security on sensitive entities

## Related frontend

`/crm/leads`, `/crm/customers`, `/crm/deals`, `/crm/pipelines`
