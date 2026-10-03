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
- OIDC authorization-code login. SAML metadata plus ACS that checks a shared secret attribute (not a full XML signature validator). SCIM v2 user list, create, patch `active`, and delete.

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
GAPGPT_API_KEY=
OPENAI_API_KEY=
```

## Screens

Dashboard paths: `admin/connect`, `crm/companies`, `crm/price-book`, `crm/forms`, `crm/esign`, `crm/content`, `pm/planning`, `admin/studio`. The header bell reads the unified feed. A service worker caches the offline shell and the planning page can queue task creates until the network returns.
