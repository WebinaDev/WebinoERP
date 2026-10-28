# Customer 360 — Phase 3 API contract (Orders / Accounting / SMS)

Base: `/api/v1/crm/customers/{id}/hub` (frontend client: `/v1/crm/customers/{id}/hub/...`). Auth: `auth:sanctum` + `module:crm`.
`{id}` is numeric. Merged customers → 404 like other hub routes.

## Common
- List envelope: `{ data: Row[], meta: { total, per_page, current_page, last_page, kpis?, can: {...} } }`.
- Query: `page` (default 1), `per_page` (default 20, max 100).
- Money: amounts are IRR (`currency: "IRR"`). **Money fields are `null`** when the viewer cannot see the
  `billing` section (no `sales.invoices.view` / `accounting.view` / `projects.invoices.view`). UI: `hasMoney(v)`.
- Dates are ISO‑8601 strings (server tz), `null` when unknown.
- 403 if the section is not viewable; 422 `{message, errors}` on validation.

## Section visibility (`CustomerHubAccess::VIEW`, appear in `GET /hub/summary` → `sections`)
| key | any of |
|---|---|
| `orders` | `crm.accounts.view`, `sales.invoices.view` |
| `accounting` | `sales.invoices.view`, `accounting.view` |
| `sms` | `crm.messages.view`, `crm.accounts.view` |

Manage actions (`CustomerHubAccess::MANAGE`): `sms` → `crm.hub.sms.send`; `basalam` (link stores) → `crm.accounts.manage`;
`payment` → `sales.invoices.manage|accounting.receipt.manage`; `invoice` → `sales.invoices.manage`.

## Nullable fields (summary — matches the actual row shapes below)
- **Money (null without `billing` view):** orders `total`, items `unit_price`/`total`, `meta.kpis.total_amount`; accounting summary
  `balance`, `outstanding`, `overdue`, `total_invoiced`, `total_paid`, `wallet_credit`, `credit_limit`, `aging` (whole object);
  ledger `debit`/`credit`/`balance`, `meta.opening_balance`/`closing_balance`; invoice `total`, `paid_amount`, `remaining`; payment `amount`.
- **Always nullable:** orders `paid_at`, `ordered_at`, `buyer_name`, `buyer_mobile`, `gateway`, `payment_ref`, `store`, items `sku`;
  stores `vendor_id`, `connected_at`, `last_seen_at`; accounting `last_payment`, `acc_person`; ledger `debit` or `credit` (one side is
  always null); invoices `issue_date`, `due_date`, `project_id`; payments `date`, `gateway`, `tracking_code`, `invoice_id`;
  SMS `provider`, `error`, `template_id`, `related_type`, `related_id`, `created_by`.
- **Never null:** `id`/`ref`, `source`, `status`, `currency`, `paid` (bool), invoice `overdue_days` (int, `0` when not overdue),
  `overdue_count`, `meta.total`, `meta.can.*` (bools), `meta.statuses`, `opt_out` (bool), list arrays (`[]` when empty).

## Write actions behind `meta.can` (existing hub action bar endpoints)
| `meta.can` key | endpoint | permission (any of) |
|---|---|---|
| `create_invoice` | `POST /hub/actions/invoice` `{ items: [{ title, price, discount?, desc? }] (≥1), issue_date?, project_id?, notes?, status?: draft|unpaid }` → 201 `{ data: SalesInvoice }` | `sales.invoices.manage` |
| `record_payment` | `POST /hub/actions/payment` `{ amount (≥1), method: cash|card_to_card|bank_transfer|cheque|pos|other, reference?, paid_at?, invoice_id?, note? }` → 201 `{ data: { id: "mp-<n>", invoice_status|null } }` | `sales.invoices.manage`, `accounting.receipt.manage` |
Both are audited. Timeline: a non-draft invoice fires `InvoiceIssued` (`invoice.issued`); a manual payment fires `PaymentRecorded`
(`payment.recorded`, `meta.subject_type = "manual_payment"`). When `invoice_id` is given and payments reach the invoice total, the
invoice becomes `paid`. After either call, refetch `/hub/accounting`, `/invoices`, `/payments`, `/ledger`.

---
## 1. Orders — `GET /hub/orders`
Query: `source=all|marketplace|basalam` (default all), `status`, `q` (order number / buyer), `paid=1|0`.
`status` is an exact match on the source's own status string (no fixed enum: Basalam statuses come from the site). Known values:
marketplace `pending|paid|processing|shipped|delivered|completed|cancelled|refunded`; Basalam whatever the store sends
(typically `pending|paid|preparing|shipped|delivered|completed|cancelled`). **Build the filter dropdown from `meta.statuses`**
(sorted distinct statuses among this customer's orders); unknown values just return an empty list (no 422).

Row:
```
{ ref: "mo-12" | "bs-5", id: 12, source: "marketplace"|"basalam", number: "MO-1001", status: "paid",
  total: 1500000|null, currency: "IRR", paid: true, paid_at, ordered_at, items_count: 3,
  buyer_name: "…"|null, buyer_mobile: "0912…"|null, gateway: "zarinpal"|null, payment_ref: "…"|null,
  store: { connection_id: 4, site_url: "https://shop.example.ir" } | null }
```
`meta.kpis = { count, paid_count, total_amount|null, basalam_count, marketplace_count }`
`meta.can = { link_basalam: bool }`, `meta.statuses: string[]`

### `GET /hub/orders/{ref}` (ref = `mo-12` | `bs-5`)
`{ data: Row + { items: [{ title, qty, unit_price|null, total|null, sku|null }] } }`

### Basalam stores
- `GET /hub/basalam/stores` → `{ data: [{ connection_id, site_url, vendor_id, status, connected_at, last_seen_at, linked: true }],
  meta: { candidates: [same shape, linked:false — unlinked stores whose host matches this customer's domains/sites], can: { link } } }`
- `vendor_id`: `integer | null` (Basalam vendor id, `unsignedBigInteger`; null until the store reports it). `connection_id`: integer.
- `POST /hub/basalam/stores` `{ connection_id: int, force?: bool }` → 200 `{ data: store }` (re-links orders of that store with no customer).
  If the store is already linked to **another** customer → 409 `{ message }`. To move it, show a confirm dialog and resend the same body
  with `force: true` → 200: the store is re-pointed to this customer (orders already linked to the old customer keep their link;
  only unlinked orders are attached). Linking a store already on this customer is idempotent (200).
- `DELETE /hub/basalam/stores/{connectionId}` → 204.
- Auto-link: a connection is linked automatically when its `site_url` host matches `crm_customer_domains` / `webino_site_provisions.domain` / account website.

### Basalam ingest (site → ERP, not for the dashboard)
`POST /api/webinocrm/v1/crm/basalam/orders` (middleware `webino.site`, HMAC; domain = host of `site_url`)
`{ site_url, orders: [{ id, status?, total?, buyer_name?, buyer_mobile?, items?: [], ordered_at?, paid_at? }] }` (≤200) → `{ success, data: { ok, saved, linked } }` (webinocrm envelope). Upsert by (domain, id).

---
## 2. Accounting
### `GET /hub/accounting` — summary
```
{ data: { currency: "IRR",
  balance, outstanding, overdue, overdue_count, total_invoiced, total_paid, wallet_credit, credit_limit,   // all number|null
  aging: { current, d1_30, d31_60, d61_90, d90_plus } | null,          // open invoice remaining by days past due
  last_payment: PaymentRow|null, acc_person: { id, name } | null },
  meta: { can: { record_payment, create_invoice } } }
```
Due date = `sales_invoices.due_date` when present, else `issue_date + 30 days`. `balance` = credits − debits (negative = customer owes).

### `GET /hub/accounting/ledger?from=YYYY-MM-DD&to=YYYY-MM-DD`
Chronological (oldest first, paged). Row:
`{ date, kind: "invoice"|"payment"|"receipt"|"refund", ref: "inv-3"|"pi-9"|"mo-2"|"mp-4"|"rv-7", description, debit|null, credit|null, balance|null }`
`meta += { opening_balance|null, closing_balance|null }`. Sources: sales invoices (debit), gateway/marketplace/manual payments (credit), accounting receipt vouchers of the linked `acc_persons` row (matched by national_id / economic code / mobile).

### `GET /hub/accounting/invoices?status=open|paid|overdue|all`
Row: `{ id, number, status, issue_date, due_date, total|null, paid_amount|null, remaining|null, overdue_days: int, source: "sales", project_id|null }`
`meta.kpis = { open, overdue, paid }` (counts)

### `GET /hub/accounting/payments`
Row: `{ id: "pi-9"|"mo-2"|"mp-4", source: "gateway"|"marketplace"|"manual", date, amount|null, gateway, tracking_code, status, paid: bool, invoice_id|null }`

---
## 3. SMS
### `GET /hub/sms?status=&q=`
Row: `{ id, to, body, status: "sent"|"queued"|"failed"|"logged", provider|null, error|null, template_id|null, related_type, related_id,
  created_at, created_by: { id, name } | null }`
`meta.can = { send: bool }`
`meta.recipients = [{ kind: "account"|"contact", id, label, mobile }]`
`meta.templates = [{ id, name, body }]` (active sms templates)
`meta.opt_out = bool` (marketing_sms_opt_out)

### `POST /hub/sms` — send (permission `crm.hub.sms.send`)
`{ to?: "09…" (defaults to customer mobile), contact_id?: int, body?: string (≤ 900), template_id?: int, purpose?: "transactional"|"marketing" }`
One of `body` / `template_id` required. `{{name}}` in body/template is replaced with the customer name.
→ 201 `{ data: Row }`. 422 if no mobile, or `purpose=marketing` and customer opted out.
Sending goes through the existing `CrmOutboundMessenger` (ModirPayamak edge → SendSmsJob → log). Every SMS log row tied
to a customer (via related account/contact or mobile match) appears here and fires `SmsSent` on the timeline.
