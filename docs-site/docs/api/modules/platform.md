# Platform module API

**Base:** `/api/v1/platform`  
**Auth:** Sanctum + `module:platform` + `module.permission:platform`

Coolify-class control plane inside WebinoERP: SSH servers, Docker destinations, projects/environments/resources, domains, backups, Git sources, notifications, and first-class Webino Dashboard provisioning.

## Envelope

```json
{ "success": true, "data": {}, "message": null, "meta": null, "errors": null }
```

## Surfaces

| Area | Methods | Paths |
|------|---------|-------|
| Dashboard | GET | `/dashboard` |
| Servers | CRUD + actions | `/servers`, `/servers/{id}/validate`, `/bootstrap`, `/resources`, `/images`, `/networks`, `/metrics`, `/cleanup`, `/proxy`, `/proxy/reload`, `/destinations`, `/terminal` |
| SSH keys | index/store/destroy | `/ssh-keys` |
| API tokens | index/store/destroy | `/tokens` (abilities: `read`, `read:sensitive`, `write`, `deploy`) |
| Projects | CRUD + environments | `/projects`, `/projects/{id}/environments` |
| Resources | CRUD + deploy/ops | `/resources`, `/deploy`, `/deployments`, `/env`, `/volumes`, `/start`, `/stop`, `/domains` |
| Storages / backups | CRUD + run | `/storages`, `/backups`, `/backup-schedules` |
| Sources | index/store/destroy | `/sources` |
| Notifications | CRUD + test | `/notifications` |
| Service templates | index/show | `/services/templates`, `/services/templates/{slug}` |
| Settings | show/update | `/settings` |
| Variables / tags | CRUD | `/variables`, `/tags` |
| Webino launch | POST | `/webino/launch` |
| CRM 360 | GET | `/crm/{accountId}/sites` |

## Permissions

Spatie abilities under `platform.*` (see `RolesAndPermissionsSeeder` and `config/module_permissions.php`).

## Site admin announcements

Staff notices for tenant dashboards live under `/api/v1/site-builder/announcements` (not `/api/v1/platform`). Site targeting is all sites, selected sites, or a site category or type. Delivery is `POST /api/v1/integrations/erp/announcements` on each site with `Authorization: Bearer` `WEBINO_ERP_API_TOKEN`. Contract: `docs/site-admin-announcements.md`.

## Site types

When launching Webino Dashboard (Site Builder or `/webino/launch`), pass `site_type_slug` ∈ `ecommerce|magazine|cafe|resume|corporate`. License `meta.modules` / `meta.module_matrix` follow `Modules\Platform\Support\SiteTypeProfiles`.
