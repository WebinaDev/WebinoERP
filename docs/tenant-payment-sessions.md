# Tenant payment sessions

The tenant dashboard starts a payment in ERP and sends the browser to the gateway. ERP verifies and settles on the callback. The dashboard must not mark a bill paid from the return query alone.

Amounts are integer Iranian rials (IRR). Installment totals always add `fee_percent`. Cash adds it only when that gateway has `apply_fee_on_cash`.

## Authentication

Tenant routes do not use Sanctum. They use the same optional HMAC as `POST /api/webinocrm/v1/license/check`.

When `WEBINOCRM_LICENSE_HMAC_SECRET` is empty, a body with `domain` is accepted. When the secret is set, every call must include:

| Field | Meaning |
| --- | --- |
| `domain` | Tenant site domain. ERP normalizes it (no scheme, no `www`, no path). |
| `product` | Product slug, for example `webinodashboard`. Optional when the secret is empty. |
| `ts` | Unix time. Must be within 600 seconds. |
| `signature` | `HMAC-SHA256` hex of `domain\|product\|ts` using the shared secret. |

A missing or stale signature returns `401`.

The session domain is taken from this signed `domain`, not from a separate payable domain. A bill whose domain does not match is `403`. Sales, accounting, and project invoices have no site domain, so the dashboard cannot pay them. ERP staff pay those through Sanctum.

## Tenant routes

All are `POST` and JSON.

| Path | Purpose |
| --- | --- |
| `/api/webinocrm/v1/payments/bills` | Outstanding bills and offers for the signed domain. |
| `/api/webinocrm/v1/payments/gateways` | Enabled gateways. Optional `mode` is `cash` or `installment`. Disabled gateways are omitted. |
| `/api/webinocrm/v1/payments/quote` | Fee preview. Body: `gateway`, `mode`, `base_amount`. |
| `/api/webinocrm/v1/payments/sessions` | Create a payment session and return `redirect_url`. |
| `/api/webinocrm/v1/payments/sessions/{publicId}` | Read status after return. Same HMAC body, plus the id in the path. |

### Create session

```json
{
  "domain": "client.example.com",
  "product": "webinodashboard",
  "ts": 1710000000,
  "signature": "<hex>",
  "payable_type": "wallet_topup",
  "payable_id": "client.example.com",
  "mode": "installment",
  "gateway": "snappay",
  "return_url": "https://dashboard.example.com/billing/return",
  "mobile": "09120000000",
  "amount": 150000,
  "idempotency_key": "dash-order-42"
}
```

`return_url` is required and must be an `http` or `https` URL on the dashboard. `amount` is required for `wallet_topup` and `generic`. `mobile` is required for live Snapp Pay, Digipay, and Torob Pay. Local simulation fills `09120000000` when it is omitted.

`payable_type` values:

| Type | `payable_id` | Notes |
| --- | --- | --- |
| `sms_credit` | SMS package id | ERP creates or reuses a pending SMS order. The response `payable_type` becomes `sms_order`. |
| `sms_order` | ModirPayamak order id | Domain must match. |
| `marketplace_order` | Marketplace order id | Domain comes from the site provision. |
| `license` | Core license id | Uses `meta.balance_due` or `meta.renewal_amount`. |
| `subscription` | Sales catalog item id | Item type `subscription`. Domain is the signed domain. |
| `wallet_topup` | Domain | Credits `base_amount` only. The fee is not added to the wallet. |
| `generic` | Caller id | Amount required. |
| `sales_invoice`, `accounting_invoice`, `project_invoice` | Invoice id | ERP staff only. |

Success is `201`:

```json
{
  "data": {
    "payment_id": "uuid",
    "redirect_url": "https://gateway.example/pay/...",
    "status": "redirected",
    "mode": "installment",
    "gateway": "snappay",
    "base_amount": 150000,
    "fee_percent": 10,
    "fee_amount": 15000,
    "total_amount": 165000,
    "currency": "IRR"
  }
}
```

Send the browser to `redirect_url`. Repeating the same `idempotency_key`, or the same open payable within two hours, returns the existing session.

## Callback and return

The gateway calls ERP at `GET` or `POST /api/v1/integrations/payments/callback/{gateway}` where `{gateway}` is `zarinpal`, `snappay`, `digipay`, or `torobpay`. ERP verifies with the provider (and settles when that provider requires it) before any side effect. A redirect or `Status=OK` alone does not mark the bill paid.

After settle, a browser callback is redirected to `return_url` with:

`payment_id`, `status`, `gateway`, `mode`, `amount`, `base_amount`, `fee_amount`, `fee_percent`, `ref`, `sig`

`sig` is `HMAC-SHA256` of `payment_id|status|amount` using `PAYMENT_CALLBACK_SECRET`, or `APP_KEY` when that secret is empty. Clients that send `Accept: application/json` receive JSON instead of a redirect.

The dashboard should `POST /api/webinocrm/v1/payments/sessions/{payment_id}` and treat the bill as paid only when `status` is `settled`.

## ERP staff routes

Sanctum, module `integrations`, and permission `integrations`:

| Method | Path |
| --- | --- |
| `GET` / `PUT` | `/api/v1/integrations/payments/gateways` |
| `POST` | `/api/v1/integrations/payments/gateways/{code}/test` |
| `GET` | `/api/v1/integrations/payments/options` |
| `POST` | `/api/v1/integrations/payments/quote` |
| `GET` | `/api/v1/integrations/payments/bills` |
| `GET` / `POST` | `/api/v1/integrations/payments/intents` |
| `GET` | `/api/v1/integrations/payments/intents/{publicId}` |
| `POST` | `/api/v1/integrations/payments/intents/{publicId}/cancel` |

`PUT /payments/gateways` body is `{ "gateways": { "zarinpal": { ... }, "snappay": { ... } } }`. Blank secret fields keep the saved value. Responses mask secrets. A disabled gateway is absent from `options` and from the payment picker.

Legacy `POST /api/v1/integrations/payments/initiate` and `/verify` stay. They use the new flow when a cash gateway is enabled, and the previous cache sandbox when it is not.

## Gateways

| Code | Modes | Live credentials | Sandbox without credentials |
| --- | --- | --- | --- |
| `zarinpal` | cash | `merchant_id` | Local simulator, unless the merchant id is 36 characters (then HTTP sandbox). |
| `snappay` | installment | `client_id`, `client_secret`, `username`, `password`, and a base URL | Local simulator. Production base `https://api.snapppay.ir`. The ERP server IP must be whitelisted. |
| `digipay` | cash and installment | same OAuth fields | Local simulator. UAT `https://uat.mydigipay.info/digipay/api`, production `https://api.mydigipay.com/digipay/api`. This is Digikala UPG, not another DigiPay product. Cash ticket type `0`, installment type `13`. |
| `torobpay` | installment | same OAuth fields plus base URL | Local simulator. Default production base `https://cpg.torobpay.com`. |

Snapp Pay and Torob Pay verify then settle. Digipay verifies then delivers. Zarinpal verify codes `100` and `101` are success and do not need a separate settle call.
