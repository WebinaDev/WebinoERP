# Site admin announcements

ERP staff send a notice to tenant site admins. Each ready site receives it in the dashboard header bell and the inbox at `/dashboard/notifications`. Admins can mark it read or dismiss it. Persian copy is the primary text. English title and body are optional.

This repo is WebinoERP. WebinoDashboard is a separate checkout. The consumer patch for that repo is [patches/webino-dashboard-site-admin-announcements.patch](patches/webino-dashboard-site-admin-announcements.patch) (based on `WebinaDev/WebinoDashboard` commit `581f21e`).

## Staff UI

`/dashboard/admin/platform/announcements`

Audience:

| Mode | Who receives it |
| --- | --- |
| `all` | Every site that is not a draft and not cancelled |
| `sites` | The selected provision ids, including drafts. Drafts stay queued until the site is ready |
| `category` | `wizard_payload.business_category_id` or the package category |
| `type` | `wizard_payload.site_type_slug` / `business_type_id`, or the package type |
| `tag` | `wizard_payload.tags` (string or list) and platform tags attached to the provision or its platform resource |

Permission: `site_builder.provision.view` to list, `site_builder.provision.manage` to create, publish, retry, and archive. The screen is under Platform in the sidebar.

## Staff API

Auth: Sanctum, module `platform`, same permission map as the other `/api/v1/site-builder` routes.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/v1/site-builder/announcements/options` | Sites, categories, types, tags for the form |
| POST | `/api/v1/site-builder/announcements/preview` | Count of matching sites. Body is the audience fields only |
| GET | `/api/v1/site-builder/announcements` | List, with delivery counts |
| POST | `/api/v1/site-builder/announcements` | Create. `"publish": true` sends immediately |
| GET | `/api/v1/site-builder/announcements/{id}` | One notice plus per-site delivery rows |
| PATCH | `/api/v1/site-builder/announcements/{id}` | Edit. A published notice is pushed again |
| DELETE | `/api/v1/site-builder/announcements/{id}` | Drafts only |
| POST | `/api/v1/site-builder/announcements/{id}/publish` | Publish or publish again after archive |
| POST | `/api/v1/site-builder/announcements/{id}/retry` | Re-resolve audience and push pending, failed, and new sites |
| POST | `/api/v1/site-builder/announcements/{id}/archive` | Revoke the notice on sites that already received it |

Create body:

```json
{
  "title_fa": "قطع برق",
  "title_en": "Power cut",
  "body_fa": "امشب سرورها یک ساعت در دسترس نیستند.",
  "body_en": "Servers pause for one hour tonight.",
  "level": "warning",
  "expires_at": null,
  "publish": true,
  "audience_mode": "all",
  "site_ids": [],
  "category_ids": [],
  "type_ids": [],
  "tags": []
}
```

`level` is `info`, `warning`, or `critical`. `audience_mode` is `all`, `sites`, `category`, `type`, or `tag`.

Delivery counts on each row: `delivered`, `pending`, `failed`, `revoked`, `read`, `dismissed`. Read and dismiss are reported by the tenant. They are per site (the first admin action), not per admin user. Per-user state stays in the tenant inbox.

Ops retry for every published notice:

```bash
php artisan site-builder:push-announcements
php artisan site-builder:push-announcements 12
```

A site is pushed only when it has a domain, a provision token, and status `ready` or `ssl_pending`. Anything else stays `pending` with the message `سایت هنوز آماده دریافت اطلاعیه نیست.` Publish waits on each tenant call, with a 20 second timeout, so the screen can show delivered or failed before it returns.

## Push payload (ERP to tenant)

Site Control already calls the tenant with `X-Provision-Token` and `X-Provision-Signature` (HMAC-SHA256 of the raw JSON body, key `provision_webhook_secret`). Announcements use that same call.

`POST https://{site-domain}/api/v1/provision/announcements`

```json
{
  "version": 1,
  "announcement": {
    "external_key": "erp-site-announcement:12",
    "action": "upsert",
    "level": "info",
    "title_fa": "قطع برق",
    "title_en": "Power cut",
    "body_fa": "امشب سرورها یک ساعت در دسترس نیستند.",
    "body_en": "Servers pause for one hour tonight.",
    "published_at": "2026-10-03T14:00:00+00:00",
    "expires_at": null
  }
}
```

`action` is `upsert` or `revoke`. Encode with `JSON_UNESCAPED_UNICODE` and sign those exact bytes. An empty object is `{}`, not `[]`.

The tenant stores one inbox row per admin (`role` `admin` or `staff`; if none, `shop_manager`). The bell title is Persian when `title_fa` is set. The same `external_key` updates the row. A text change becomes unread again. A retry with the same text keeps read and dismiss. `revoke`, archive, or a past `expires_at` dismisses it.

## Pull payload (tenant to ERP)

Tenants that missed the push call ERP. Auth is the same HMAC. The token selects the site. Secret is the hosting `provision_webhook_secret`.

`POST /api/v1/site-builder/sync/announcements/pull`

Body: `{}`

```json
{
  "data": {
    "site_id": 4,
    "slug": "cafe",
    "announcements": [
      {
        "version": 1,
        "announcement": {
          "external_key": "erp-site-announcement:12",
          "action": "upsert"
        }
      }
    ]
  }
}
```

Each item is the push object above (`upsert` or `revoke`). A site created after publish still receives current published notices on pull.

`WebinoDashboard` command, also scheduled hourly once the patch is applied:

```bash
php artisan erp:pull-announcements
```

It reads `WEBINO_BASE_URL`, `TENANT_PROVISION_TOKEN`, and `WEBINO_PROVISION_HMAC_SECRET` (already written into the tenant `.env` by provisioning).

## Receipt (tenant to ERP)

`POST /api/v1/site-builder/sync/announcements/receipt`

```json
{
  "external_key": "erp-site-announcement:12",
  "event": "read",
  "occurred_at": "2026-10-03T15:00:00+00:00"
}
```

`event` is `read`, `dismissed`, or `delivered`. Unknown keys return 404. A bad signature returns 403.

## Apply the Dashboard patch

From a WebinoDashboard checkout at or near `581f21e`:

```bash
git apply /path/to/WebinoERP/docs/patches/webino-dashboard-site-admin-announcements.patch
php artisan migrate
```

The patch adds:

- `POST /api/v1/provision/announcements` (provision HMAC)
- `dismissed_at` and `external_key` on `user_notifications`
- Dismiss on the header bell and `/dashboard/notifications`
- `erp:pull-announcements`

Until that patch is deployed, ERP still stores the notice and records push failures. Retry after the tenant is updated.
