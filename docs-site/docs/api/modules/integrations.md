# Integrations API

**Base:** `/api/v1/integrations`  
**Auth:** Mixed — webhooks public; admin routes require `auth:sanctum` + `module:integrations`  
**Explorer tag:** [INTEGRATIONS](/api/explorer/#tag/INTEGRATIONS)

## Overview

Third-party integrations: SMS, payments, Bale messenger, Telegram, and ModirPayamak SMS platform.

## SMS & payments

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sms/settings` | SMS provider settings (IntegrationSetting) |
| POST | `/sms/send` | Send SMS |
| PUT | `/sms/settings` | SMS provider settings |
| POST | `/payments/initiate` | Start payment |
| POST | `/payments/verify` | Verify payment callback |

Production checklist: `php artisan webino:integrations:validate` — see [SMS production guide](../../guides/sms-production.md).

## ModirPayamak

Customer API under `/modirpayamak`:

| Method | Path | Description |
|--------|------|-------------|
| GET | `/modirpayamak/account` | Account info |
| GET | `/modirpayamak/packages` | SMS packages |
| POST | `/modirpayamak/topup/init` | Init wallet top-up |
| POST | `/modirpayamak/send` | Send SMS |
| GET | `/modirpayamak/reports/outbox` | Outbox reports |

Admin API under `/modirpayamak/admin` (dashboard, **proxy** for tickets/users/drafts, customers, packages, orders).

Proxy example:

```http
POST /api/v1/integrations/modirpayamak/admin/proxy
{ "method": "GET", "path": "api/tickets" }
```

## Bale business (`webinocrm/v1`)

Full business bot REST lives under **`/api/webinocrm/v1/bale/*`** (requires `auth:sanctum` + `role:system_manager`):

| Method | Path | Description |
|--------|------|-------------|
| GET/POST | `/settings` | Bot settings |
| GET | `/stats`, `/kpi` | Dashboard metrics |
| GET | `/logs`, `/user-logs` | System / per-chat logs |
| GET | `/webhook-url` | Public webhook URL |
| POST | `/set-webhook` | Register webhook with Bale |
| GET/POST | `/campaigns`, `/campaigns/{id}/run` | Campaigns |
| POST | `/message/bulk` | Bulk send |
| POST | `/diagnostics/*` | Webhook info, test log |

Public webhook (no auth): `POST /api/webinocrm/v1/bale/webhook`

Legacy integration routes under `/api/v1/integrations/bale/*` remain for simple send/webhook parity.

## Hosting (`webinocrm/v1`)

See [webinocrm/v1 Hosting (legacy)](../legacy/webinocrm-hosting.md) — Git sources, Portainer, audit logs under `/api/webinocrm/v1/hosting/*` (system manager only).

## Telegram

| Method | Path | Description |
|--------|------|-------------|
| POST | `/telegram/webhook` | Telegram webhook |
| POST | `/telegram/send` | Send Telegram message |

## Related frontend

- `/admin/integrations/modirpayamak/*` — send, reports, tickets, users, drafts
- `/admin/integrations/bale` — Bale business dashboard (settings, campaigns, logs)
- Admin **Settings → SMS** tab — unified with IntegrationSetting via `/sms/settings`
