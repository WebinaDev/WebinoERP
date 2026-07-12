# webinocrm/v1 Hosting API

**Base:** `/api/webinocrm/v1/hosting`  
**Auth:** `auth:sanctum` + `role:system_manager`

## Overview

Hosting and infrastructure settings for Git sources, Portainer stack control, and audit logging. Used by the ERP page `/admin/hosting-infra`.

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/settings` | Read hosting settings (secrets masked) |
| PUT | `/settings` | Update hosting settings |
| GET | `/module-git-sources` | List central module Git sources |
| POST | `/module-git-sources` | Create Git source |
| PATCH | `/module-git-sources/{id}` | Update clone URL / auth |
| DELETE | `/module-git-sources/{id}` | Delete Git source |
| GET | `/portainer/endpoints` | List Portainer endpoints (503 if unconfigured) |
| GET | `/portainer/stacks` | List stacks (optional `endpoint_id`) |
| POST | `/portainer/stacks/{stackId}/{action}` | Queue start/stop (`action`: start\|stop) |
| GET | `/audit-logs` | Recent infra audit events |

## Related frontend

- `/admin/hosting-infra` — dedicated hosting page (canonical)
- Settings → Accounting → Hosting tab links to the dedicated page

## Tests

`tests/Feature/HostingApiTest.php`

## See also

- [Integrations module](./integrations.md) — SMS, Bale, ModirPayamak under `/api/v1/integrations`
