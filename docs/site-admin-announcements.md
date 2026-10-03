# Site admin announcements

ERP staff send a notice to tenant sites. Each ready site receives it in the dashboard header bell and the inbox on `/dashboard`. Read and dismiss stay on the tenant, per signed-in user. Persian title and body are the text that is pushed. English title and body are optional fallbacks when the Persian field is empty.

The tenant receiver is already in WebinoDashboard. Its contract is `docs/erp-tenant-announcements.md` on branch `cursor/fa-locale-announcements-7e4d`. ERP does not ship a Dashboard patch.

## Two audiences

Site targeting decides which provisions receive a push. Inbox audience is the `audience` field on the tenant payload and decides who on that site sees the row.

Site targeting (`audience` JSON on the ERP row):

| Mode | Who receives the push |
| --- | --- |
| `all` | Every site that is not a draft and not cancelled |
| `sites` | The selected provision ids, including drafts. Drafts stay queued until the site is ready or SSL pending |
| `category` | `wizard_payload.business_category_id` or the package category |
| `type` | `wizard_payload.site_type_slug` / `business_type_id`, or the package type |
| `tag` | `wizard_payload.tags` (string or list) and platform tags on the provision or its platform resource |

Inbox audience (`inbox_audience`, default `admins`):

| Value | Who sees it on the tenant |
| --- | --- |
| `admins` | Role `admin` only |
| `staff` | Every role except `customer` |
| `all` | Every signed-in user |

Dashboard accepts only `all`, `staff`, and `admins`. Do not send the site-targeting mode as `audience`.

## Staff UI

`/dashboard/admin/platform/announcements`

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
| POST | `/api/v1/site-builder/announcements/{id}/archive` | Mark local deliveries revoked. Does not call the tenant |

Create body:

```json
{
  "title_fa": "قطع برق",
  "title_en": "Power cut",
  "body_fa": "امشب سرورها یک ساعت در دسترس نیستند.",
  "body_en": "Servers pause for one hour tonight.",
  "level": "warning",
  "inbox_audience": "admins",
  "expires_at": null,
  "publish": true,
  "audience_mode": "all",
  "site_ids": [],
  "category_ids": [],
  "type_ids": [],
  "tags": []
}
```

`level` is `info`, `warning`, or `critical`. `inbox_audience` is `admins`, `staff`, or `all`. `audience_mode` is `all`, `sites`, `category`, `type`, or `tag`.

Delivery counts on each row: `delivered`, `pending`, `failed`, `revoked`. `read` and `dismissed` stay on the row for the optional HMAC receipt below. The tenant bell does not report them back, so the staff screen shows delivered, queued, and failed.

Ops retry for every published notice:

```bash
php artisan site-builder:push-announcements
php artisan site-builder:push-announcements 12
```

A site is pushed only when it has a domain and status `ready` or `ssl_pending`. Anything else stays `pending` with the message `سایت هنوز آماده دریافت اطلاعیه نیست.` Publish waits on each tenant call, with a 20 second timeout, so the screen can show delivered or failed before it returns.

## Push payload (ERP to tenant)

`POST https://{site-domain}/api/v1/integrations/erp/announcements`

```
Authorization: Bearer <WEBINO_ERP_API_TOKEN>
Accept: application/json
Content-Type: application/json
```

Body, encoded with `JSON_UNESCAPED_UNICODE`:

```json
{
  "id": 12,
  "title": "قطع برق",
  "body": "امشب سرورها یک ساعت در دسترس نیستند.",
  "created_at": "2026-10-03T14:00:00+00:00",
  "audience": "admins",
  "tenant_domain": "cafe.example.test"
}
```

| Field | Source |
| --- | --- |
| `id` | ERP announcement id. Sending the same id updates the tenant row (`source_id`) |
| `title` | `title_fa`, or `title_en` when Persian is empty |
| `body` | `body_fa`, or `body_en` when Persian is empty |
| `created_at` | `published_at`, or `created_at`, ISO 8601 |
| `audience` | `inbox_audience`, default `admins` |
| `tenant_domain` | Site domain, lowercased. Required so the row is scoped to that tenant |

Do not send `read`. Read is stored per user on the tenant (`ErpAnnouncementRead`).

Dashboard has no revoke endpoint. Archive only changes the ERP delivery to `revoked`. A row already ingested on the tenant stays there. Repeating the same `id` updates title, body, and audience. An expired or archived notice is omitted from the optional pull below, and it is not pushed again.

A token call without `tenant_domain` would be visible to every tenant, so ERP always sends `tenant_domain`.

## Token

`WEBINO_ERP_API_TOKEN` must match on ERP and on the tenant.

1. If `WEBINO_ERP_API_TOKEN` is set in the ERP environment, that value is sent.
2. Otherwise ERP stores one secret on `core_hosting_settings.erp_api_token` (encrypted). The first use generates `bin2hex(random_bytes(32))` when the hosting row is empty.
3. `TenantEnvBuilder` writes `WEBINO_ERP_API_TOKEN` into the tenant `.env` for new provisions and stack resyncs.

Existing sites need a stack resync before ingest auth succeeds. `php artisan site-builder:ensure-hosting-defaults` generates the hosting token when both the row and the env var are empty.

## Optional pull and receipt

Dashboard does not call these. They remain for a site that can sign with the provision HMAC (`X-Provision-Token` plus `X-Provision-Signature` over the raw body, key `provision_webhook_secret`).

`POST /api/v1/site-builder/sync/announcements/pull` with body `{}` returns the same item shape as the push, one object per current published notice for that site. Revoked, archived, expired, and draft notices are skipped.

`POST /api/v1/site-builder/sync/announcements/receipt`:

```json
{
  "external_key": "erp-site-announcement:12",
  "event": "read"
}
```

`event` is `read`, `dismissed`, or `delivered`. Unknown keys return 404. A bad signature returns 403. The staff UI does not treat these counts as the tenant bell state.
