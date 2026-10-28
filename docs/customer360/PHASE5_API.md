# Customer 360 — Phase 5 API contract (Duplicates / Import‑Export / Portal / BI)

Envelope and money rules as in PHASE3_API.md (`{data, meta}`; money `null` without billing visibility).

## 1. Duplicates (staff; `auth:sanctum`, `module:crm`, needs `crm.accounts.view`)
### `GET /api/v1/crm/customers/duplicates?min_score=50&page=&per_page=`
Row: `{ key: "12-40", score: 0..100, reasons: ["national_id","mobile","email","domain","name"],
  a: Brief, b: Brief, suggested_target_id }` — Brief = `{ id, name, mobile, email, national_id, website, created_at, invoices_count }`.
Sorted by score desc. Dismissed pairs excluded. `meta.can = { merge: bool }` (`crm.hub.merge`).
Scores: national_id 60, mobile 40, email 35, domain 35, exact normalized name 25, similar name 15 (capped at 100).
Suggested target = older account, or the one with more invoices.
### `GET /api/v1/crm/customers/{id}/hub/duplicates` → same rows for one customer.
### `POST /api/v1/crm/customers/duplicates/dismiss` `{ a_id, b_id }` → 204 (needs `crm.hub.merge`).
Merge itself: existing `POST /api/v1/crm/customers/{target}/hub/merge {source_id}`.

## 2. Import / Export (permission `crm.hub.import_export`)
### Export — `GET /api/v1/crm/customers/export?entity=customers|addresses|contacts&format=csv|xlsx&ids[]=&q=`
File download (`Content-Disposition: attachment`). No `ids` = all customers (merged ones excluded). CSV is UTF‑8 with BOM.
Columns:
- customers: `id,name,customer_kind,type,hub_status,national_id,economic_code,registration_no,mobile,phone,email,website,city,province,postal_code,address_line,segment,source,tags,account_manager_id,created_at`
- addresses: `id,account_id,account_name,label,province,city,postal_code,address_line,is_default`
- contacts: `id,account_id,account_name,first_name,last_name,mobile,phone,email,job_title,is_primary`
### Template — `GET /api/v1/crm/customers/import/template?entity=&format=csv|xlsx` (header row only).
### Import — `POST /api/v1/crm/customers/import` multipart `{ file (csv|xlsx ≤5MB), entity, mode: create|upsert (default upsert), dry_run: 0|1 }`
Upsert key: customers `id` → `national_id` → `mobile`; addresses/contacts `id`, else created under `account_id`.
→ `{ data: { total, created, updated, skipped, errors: [{ row, field, message }] } }`. Dry run writes nothing.

## 3. Customer portal
**Auth decision:** reuses the existing portal prefix **`/api/v1/projects/portal/*`** and its auth:
`auth:sanctum` + `module:projects` + `module.permission:projects`, and the user must be a portal customer
(`CustomerAccess::isPortalCustomer`, i.e. role `client` linked through `crm_account_users`). Account = the user's primary
linked account; pass `?account_id=` to pick another of *their* accounts (others → 403).
Existing endpoints stay: `GET /portal/summary`, tickets (`POST /portal/tickets`, replies), appointments, files.

| section | endpoint |
|---|---|
| profile | `GET /portal/profile` → `{ data: { account: {id,name,brand_name,national_id,economic_code,mobile,email,website,address_line,city,province,postal_code,hub_status}, accounts: [{id,name,is_primary}] } }` |
| contracts | `GET /portal/contracts` → rows `{ id, title, status, amount, starts_at, ends_at, signed_at, can_sign, document_id }` ; `GET /portal/contracts/{id}` → `+ { body, installments: [{ id, amount, due_date, paid_at }] }` |
| invoices | `GET /portal/invoices?status=open|paid|all` → rows `{ id, number, status, issue_date, due_date, total, remaining, payable: bool }`; `meta.kpis = { outstanding, overdue }` |
| services | `GET /portal/services` → rows `{ id, kind, name, plan, status, site_domain, renews_at }` + `meta.domains: [{ domain, expires_at, auto_renew }]` |
| documents | `GET /portal/documents` → rows `{ id, title, category, expires_at, created_at, download_url (signed, 10 min) }` — only documents whose `visible_to_roles` contains `client`, plus signed contract files |
| tickets | existing `GET /portal/summary` (`open_tickets`) + ticket endpoints |

### Contract signing with SMS OTP
1. `POST /portal/contracts/{id}/sign/otp` → 200 `{ data: { sent_to: "0912***4567", expires_in: 300, resend_in: 60 } }`.
   Sends a 6‑digit code to the customer mobile (user mobile → account mobile) via `CrmOutboundMessenger`.
   Code stored hashed in cache 5 min; 60 s resend throttle (429 `{message}` + `Retry-After` header in seconds); 422 if contract not `draft|active` or no mobile.
2. `POST /portal/contracts/{id}/sign` `{ code, accept: true }` → 200 `{ data: Contract }` with `status:"signed"`, `signed_at`.
   Wrong code → 422 `errors.code`; 5 wrong attempts invalidate the code. Stores `signature_meta` (ip, user agent, mobile, user_id, at)
   in hub audit, fires `ContractSigned` (timeline + onboarding automation).

### Pay an invoice through the existing gateway
1. `POST /portal/invoices/{id}/pay` `{ gateway?: string, return_url?: url }` → 200
   `{ data: { payment_id: "<uuid>", redirect_url: "https://gateway/…", amount, gateway } }`. Front: `window.location = redirect_url`.
   `return_url` must be on the app host (`config('app.url')` / `FRONTEND_URL`) else 422; default `{FRONTEND_URL}/portal/invoices?invoice={id}`.
   422 if already paid / gateway not configured (`{message, code}` from PaymentException).
2. Gateway → `/api/v1/integrations/payments/callback/{gateway}` (existing) verifies + settles, then redirects to `return_url` with
   `?payment_id=&status=settled|failed|…&amount=&gateway=&ref=&sig=`.
3. Front then calls `GET /portal/payments/{payment_id}` → `{ data: { payment_id, status, paid: bool, amount, invoice_id, settled_at } }` (only own payments). Do not trust the query string alone.

## 4. BI (permission `crm.hub.bi.view`; money null without billing)
Base `/api/v1/crm/customers/reports/{section}`; query `from`, `to`, `segment`, `limit` (top, default 20).
- `ltv` → `{ data: { avg_ltv, median_ltv, total_revenue, customers, buckets: [{ label, from, to, count }] } }`
- `debt` → aging `{ data: { total, buckets: { current, d1_30, d31_60, d61_90, d90_plus }, top_debtors: [{ id, name, outstanding, overdue, oldest_days }] } }`
- `churn` → `{ data: { active, churned, at_risk, churn_rate, by_month: [{ month: "2026-09", churned, new }], at_risk_list: [{ id, name, health }] } }` (churned = hub_status churned/suspended/inactive or no paid activity 180 d; at risk = health < 40)
- `cohorts` → `{ data: [{ cohort: "2026-05", size, months: [retention % m0..m11] }] }` (by first paid invoice month)
- `top` → `{ data: [{ id, name, revenue, invoices, last_paid_at }] }`
- `segments` → `{ data: [{ segment, customers, revenue, avg_ltv, outstanding }] }`

## 5. Automations (phase 4, staff; `crm.hub.automations.manage`)
- `GET /api/v1/crm/customers/automations` → `{ data: [{ id, key, name, trigger_key, enabled, sends_sms, suspends, config, last_run_at }] }`
- `PATCH /api/v1/crm/customers/automations/{key}` `{ enabled?, config? }` → row. Stored in `core_workflows` (`trigger_key = "customer360.{key}"`).
Keys: `renewal_reminder`, `installment_reminder`, `debtor_status`, `auto_suspend`, `onboarding`, `churn_risk`, `document_expiry`, `welcome_sms`.
Defaults: SMS‑sending and suspending rules are OFF.

## Implementation notes (backend as shipped)
- Signable contract statuses: `draft | active | sent | pending`; anything else → 422.
- Portal documents reuse the hub signed download route; only docs whose `visible_to_roles` includes `client` (and approved) are listed.
- `sales_invoices` has no `due_date` column: `due_date` = `issue_date + payment_terms_days` (default 30).
- Return URL after payment: `config('app.frontend_url')`, falling back to `config('app.url')`; a custom `return_url` must be on that host (else 422).
- OTP resend throttle returns 429 with a `Retry-After` header (the API envelope strips extra body keys).
- No new migrations: duplicate dismissals and OTP state live in existing tables / cache.
