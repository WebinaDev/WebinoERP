# Live sync, CRM, planning, and studio

This pass extends the existing Integrations, CRM, Projects, Core, and AI modules. Routes stay under `/api/v1/{module}`. The JSON envelope is skipped for Slack/Outlook webhooks, SAML, and SCIM so those protocols keep their native bodies.

## Live sync

| Area | API | Notes |
| --- | --- | --- |
| Google and Outlook calendars | `/api/v1/integrations/calendars` | OAuth start, callback, webhook, and `webino:calendars:sync` every 15 minutes. Links map onto appointments and tasks. |
| Slack, Bale, Telegram | `/api/v1/integrations/bridges` | Outbound notifications plus inbound `/help`, `/task`, and `/lead`. |
| Mail | `/api/v1/integrations/mailbox` | Gmail, Microsoft Graph, or IMAP/SMTP. Threads, attachments metadata, send, and CRM activity link. |
| In-app feed | `/api/v1/integrations/notifications/feed` | Same fan-out as chat and SMS. |
| SMS | `PUT /api/v1/integrations/sms-rules` | Modes `off`, `highest_only`, `matched`. Default floor is `high`. Delivery uses ModirPayamak when enabled. |

## CRM

- Companies and company switch, currencies, FX convert. Base currency is IRR.
- Catalog products, price books with quantity breaks, and `POST /api/v1/crm/deals/{deal}/quote`.
- Public lead forms: `POST /api/v1/crm/public/forms/{slug}` stores the lead and landing attribution.
- E-sign envelopes with OTP. E-invoice documents follow an Iran-friendly seller/buyer/item shape and post to `MOADIAN_HOOK_URL` when set.
- Content calendars per account: blog or social (`instagram`, `telegram`, `linkedin`, `x`, `bale`, and others), status, publish window, reminders.
- AI: `POST /api/v1/crm/ai/assist` with `lead_suggest`, `summarize_note`, `email_draft`, `deal_risk`, `content_brief`. Uses GapGPT or OpenAI when a key exists, otherwise a deterministic heuristic.

## Projects

- Kanban WIP limits reject a move or create into a full column. Swimlanes use `swimlane_key` and the board `swimlane_field`.
- Critical path, baselines, resource capacity, and leave: `/api/v1/projects/planning/*`.
- Offline replay: `POST /api/v1/projects/offline/ops` (idempotent per user and `client_id`).
- AI task plans: `POST /api/v1/projects/ai/assist`.

## Studio

- Workflow graphs: trigger, condition, notify, create task, set flag.
- Report builder over deals, leads, tasks, and content, with CSV export.
- Feature flags with rollout percent and the `X-Webino-Sandbox: 1` header.
- OIDC authorization-code login. SAML metadata includes an identity provider descriptor, signed assertions, and ACS. Unsigned assertions still accept the shared secret attribute. SCIM v2 user list, create, patch `active`, and delete.

## Environment

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_CALENDAR_REDIRECT=
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_TENANT=common
MICROSOFT_CALENDAR_REDIRECT=
MOADIAN_HOOK_URL=
MOADIAN_BASE_URL=
MOADIAN_SANDBOX=false
MOADIAN_SANDBOX_BASE_URL=https://sandbox.moadian.ir
MOADIAN_CLIENT_ID=
MOADIAN_CLIENT_SECRET=
MOADIAN_FISCAL_ID=
MOADIAN_ECONOMIC_CODE=
MOADIAN_PRIVATE_KEY=
MOADIAN_PRIVATE_KEY_PATH=
MOADIAN_RETRY_TIMES=5
MAIL_SPAM_THRESHOLD=3
MAIL_SPAM_DOMAINS=
CPQ_APPROVAL_PERCENT=25
SAML_IDP_CERT=
SAML_IDP_KEY=
WEBINO_SANDBOX=false
INSTAGRAM_GRAPH_BASE=https://graph.facebook.com/v21.0
LINKEDIN_API_BASE=https://api.linkedin.com
GAPGPT_API_KEY=
OPENAI_API_KEY=
```

## Screens

Dashboard paths: `admin/connect`, `crm/companies`, `crm/price-book`, `crm/forms`, `crm/esign`, `crm/content`, `pm/planning`, `admin/studio`. The header bell reads the unified feed. A service worker caches the offline shell (`webino-shell-v3`) and the planning page can queue task creates and day shifts until the network returns.

## Production-grade pass

Existing expansion routes stay. This pass hardens them.

### Live sync

- Google and Outlook refresh tokens rotate in place. A cache lock avoids two refreshes at once. After three failures the account status is `needs_reauth` and sync returns an error. `webino:oauth:refresh` runs every five minutes.
- Slack, Bale, and Telegram public webhooks verify `X-Webhook-Secret`, the Telegram secret header, or the Slack `v0` signature. Slack `url_verification` still answers immediately. Other events land in `int_inbound_events` and `webino:webhooks:retry` replays failures.
- Mail sync scores spam (keywords, blocked domains, `X-Spam-Flag`). Spam is hidden from threads unless `include_spam` is set and is not linked to CRM. `GET /api/v1/integrations/mailbox/search` searches every inbox the user owns, and can pass a Gmail `q` or Graph `$search` when a token exists.

### CPQ, Moadian, attribution

- Product rules are `requires` or `excludes`. Hierarchical discounts stack from parent to child when `stackable` is set, otherwise the larger percent wins. A quote whose effective discount is above `CPQ_APPROVAL_PERCENT` (default 25) opens a pending approval.
- E-invoice documents still expose `taxid` and `seller.economic_code`. `MoadianClient` adds a structured Moadian packet, a client-credentials token, an optional SHA256 signature, and retry. With neither `MOADIAN_BASE_URL` nor `MOADIAN_HOOK_URL`, status stays `queued` / `queued_local`. HTTP 429, 5xx, and transport errors schedule `webino:moadian:retry`. `MOADIAN_SANDBOX`, `WEBINO_SANDBOX`, or `X-Webino-Sandbox: 1` selects the sandbox base URL.
- Touchpoints record multi-touch credit with models `linear`, `first`, `last`, and `position` (40 / 20 / 40). `GET /api/v1/crm/attribution/roi` returns revenue, spend, and ROI. Public lead forms still write `crm_attributions` and a touchpoint.

### Planning and publish

- `GET /api/v1/projects/planning/timeline` is a 14-day board. Drag calls `POST /api/v1/projects/planning/tasks/{id}/shift`. Baselines store `start_on` per task. Capacity still uses the same available-hours math and adds `conflicts` for leave and calendar overlap.
- The service worker cache is `webino-shell-v3`. Planning and health reads are network-then-cache. Offline actions include `shift_task`, mirrored in IndexedDB `webino-offline` and flushed by Background Sync tag `webino-offline`.
- Instagram Graph (`/{ig-user-id}/media` then `/media_publish`) and LinkedIn UGC posts publish from the content calendar. A missing connector or image (Instagram) sets `publish_status` to `failed`. `webino:content:publish` sends due items.

### Identity, reports, health

- The SAML identity provider signs assertions with a certificate in `storage/app/saml` or `SAML_IDP_CERT` / `SAML_IDP_KEY`. Metadata includes `IDPSSODescriptor`. `POST /api/v1/core/sso/saml/idp/sso` issues a response for a Sanctum user or email and password.
- Report joins are an allowlist: deals to companies, tasks to projects, content to calendars. Joined columns are aliased `{source}_{column}`. Daily or weekly email uses `webino:reports:send`.
- `GET /api/v1/core/compliance/audit` and `audit.csv` export `ops_audit_logs`, including the sandbox flag. `GET /api/v1/core/health/modules` is 200 when module counts are healthy and 503 when OAuth needs reauth, a webhook failed, a Moadian submit failed, or a social publish failed. Logs use the `webino.{module}.{event}` hook.

### Scheduler

| Command | Cadence |
| --- | --- |
| `webino:oauth:refresh` | every five minutes |
| `webino:webhooks:retry` | every minute |
| `webino:moadian:retry` | every five minutes |
| `webino:content:publish` | every five minutes |
| `webino:reports:send` | hourly |
