# WebinoERP ⇄ WebinaDashboard (WP) — Basalam contract

Source: WebinoERP `main` (backend/Modules/Marketplace, Modules/Crm/.../BasalamOrderIngestController.php). ERP base URL = `https://webina.dev` (webinaagency.ir 301 → webina.dev; plugin constant `WEBINO_DASHBOARD_VENDOR_URL`).

## 0. Auth for every site→ERP call (Site-Auth HMAC)
Headers (already added by `Webino_Dashboard_Site_Auth::sign_request` for ERP hosts):
- `X-Webino-Site`: normalized domain (lowercase, no `www.`)
- `X-Webino-Ts`: unix seconds (±300 s window)
- `X-Webino-Nonce`: `[A-Za-z0-9_-]{16,128}`, single use (replay = 401)
- `X-Webino-Signature`: `hex(HMAC-SHA256(site_secret, "v1\nMETHOD\n/path\ndomain\nts\nnonce\nsha256(raw_body)"))`

Mode on prod: `grace` → enrolled domains MUST sign, unenrolled pass. Failure: `401 {"ok":false,"unavailable":true,"code":"invalid_signature|domain_mismatch|signature_required","message":"..."}`.
The claimed domain is the `domain` body field, else host of `site_url`; it must equal `X-Webino-Site`.

## 1. OAuth routes (final — no move; paths stay)
| Method | Path | Auth | Envelope |
|---|---|---|---|
| POST | `/api/webinocrm/v1/basalam/oauth/start` | none (throttle 60/min) | raw JSON |
| POST | `/api/webinocrm/v1/basalam/oauth/refresh` | Site-Auth optional; **required to update the ERP connection record** | raw JSON |
| POST | `/api/webinocrm/v1/basalam/oauth/disconnect` | Site-Auth (`webino.site`) | raw JSON |
| GET | `/api/basalam/oauth/callback` (alias `/api/v1/marketplace/basalam/oauth/callback`) | Basalam redirect | 302 to site |

Registered Basalam redirect_uri: `https://webinaagency.ir/api/basalam/oauth/callback` (ERP default now equals this; fixed — was webina.dev, which would mismatch at token exchange).

### start
Request: `{"site_url":"https://shop.ir","return_url":"https://shop.ir/...(same host or dropped)","handoff_path":"(optional)"}`
`handoff_path` is optional; omitted/invalid → `/dashboard/settings/shop/basalam/` (the plugin dashboard SPA route that verifies the handoff). The plugin (PR #6) does not send it — that is fine.
200: `{"url":"https://basalam.com/accounts/sso?...","state":"48ch","redirect_uri":"...","client_id":"2357","expires_in":600,"handoff_key":"32ch"}`
503: `{"message":"..."}` when client_secret not configured on ERP. **Prod currently returns 503 — secret not set (see report).** 422: Laravel validation `{"message","errors":{...}}`.

### Handoff (ERP → site, browser redirect)
`{site}{handoff_path}?access_token&refresh_token&expires_in&expires_at&vendor_id&is_vendor=true&webino_sig&webino_ts&webino_hk&oauth=handoff[&return_url]`
- v1 (always): `webino_sig = hex(HMAC-SHA256(webino_hk, access|refresh|ts|vendor_id|site_url))` — key travels in the URL, so this is integrity-only, not authentication.
- **v2 (new, when the site is Site-Auth enrolled):** extra params `webino_v=2&webino_nonce&webino_sig2`
  `webino_sig2 = hex(HMAC-SHA256(site_secret, "v2\nbasalam-handoff\n{domain}\n{vendor_id}\n{webino_ts}\n{webino_nonce}\n{expires_in}\n" . hex(sha256(access_token . "|" . refresh_token))))`
  `site_secret` = the Site-Auth secret (option `webino_dashboard_site_auth_secret`); domain = normalized host. Plugin must: reject if |now−ts|>300, store nonce for 600 s and reject reuse, `hash_equals`. If enrolled and `webino_sig2` missing → reject.
- Errors: `?oauth=error&reason=vendor` (account is not a vendor).

### refresh
Request: `{"refresh_token":"...","site_url":"https://shop.ir","vendor_id":123}` (site_url/vendor_id optional).
200 (raw, not `{success,data}`): `{"access_token","refresh_token","expires_in":int,"expires_at":unix|null,"token_type":"Bearer"}`
- Refresh token: ERP returns whatever Basalam returns; if Basalam rotates, the new one is in `refresh_token`, otherwise the old one is echoed. **Always store the returned value.**
- Invalid/expired refresh token or Basalam error: `400 {"message":"<Basalam error_description or 'Token exchange failed.'>"}`; Basalam unreachable (after 2 retries): `400 {"message":"Basalam token endpoint unreachable."}`. 503 if ERP not configured. On 400 the plugin should mark disconnected and ask the merchant to reconnect.
- Proactive refresh: the plugin owns the refresh token, so the **plugin** refreshes when `now >= expires_at − 300` (and on any Basalam 401). ERP does not refresh on its own (doing so would rotate the token out from under the plugin). Store `expires_at` as absolute unix time (ERP now stores it too in `marketplace_basalam_connections.tokens`, encrypted).

### disconnect
`{"site_url":"https://shop.ir"}` → `200 {"ok":true}`. Marks the connection disconnected (keeps vendor_id/history).

## 2. Orders mirror (site → ERP) — the only order path into ERP
POST `/api/webinocrm/v1/crm/basalam/orders`, Site-Auth (`webino.site`), throttle 60/min. **Enveloped**: `{"success":true,"data":{...}}`.
```
{"site_url":"https://shop.ir","domain":"shop.ir (optional, must equal site_url host)",
 "orders":[{"invoice_id":"<Basalam invoice id>","id":"(legacy alias of invoice_id)","status_id":3237,"status":"(alt: id or text)","total":1250000,"fee":null,"currency":"IRR|IRT (default IRR)",
            "buyer_name":"","buyer_mobile":"","items":[{"title":"","qty":1,"price":0,"product_id":0,"woo_product_id":0}],
            "ordered_at":"ISO8601","paid_at":"ISO8601"}]}   // 1..200 orders
```
200: `{"success":true,"data":{"ok":true,"saved":<rows changed>,"linked":<bool customer found>}}`. 422 validation / `{"ok":false,"code":"domain_mismatch"}` if site_url host ≠ authenticated domain. 401 site-auth.
- ERP receives **no Basalam webhooks and calls no Basalam order API**. Basalam webhooks go to the site: `{site}/wp-json/webino-basalam/v1/order-manager` (legacy route still registered by the plugin: `{site}/wp-json/sync-basalam/v1/order-manager`); ERP does not proxy them. ERP config: `integrations.basalam.webhook_path` / `legacy_webhook_path`; the admin connections registry returns `webhook_url`, `legacy_webhook_url`, `site_auth_enrolled` per store. Source of truth for status = Basalam (via the plugin); ERP is a read-only mirror.
- Dedup/idempotency: unique `(site_domain, external_id)`; `external_id` = `invoice_id` ?? `id` = the **Basalam invoice id** (plugin key: order meta `_basalam_invoice_id`, fallback `webino_basalam_payments.invoice_id`; orders without one are never sent). `invoice_id` and `id` are the same field — send either, never two different ids for one order. Resending is safe; only changed rows count in `saved`. Omitted fields keep previous values.
- Push on: webhook received / status change / initial backfill (batches ≤200).
- Status mapping (`status_id` wins over `status`): 3739→`wait_vendor`, 3237→`preparation`, 3238→`shipping`, 3195→`completed`, 3067→`rejected`, 3233→`canceled`, other→`basalam_<id>`.
- Text status (`woocommerce_statuses` mode), `wc-`/`bslm-` prefix stripped: `wait-vendor|pending|on-hold`→`wait_vendor`, `preparation|processing`→`preparation`, `shipping|shipped`→`shipping`, `completed|delivered`→`completed`, `rejected|failed`→`rejected`, `canceled|cancelled|refunded`→`canceled`; anything else stored as-is (≤40 chars).
- Amounts: ERP stores **IRR**. Send Basalam amounts as-is (Basalam API is rial) or set `currency:"IRT"` and ERP ×10. `fee` (Basalam commission) is optional/nullable, same currency rule; ERP never computes it. Omit it if the plugin doesn't know it.

## 3. Product mapping (WooCommerce ⇄ Basalam)
ERP does not sync products/stock/price with Basalam; that is entirely plugin ↔ Basalam openapi. ERP has no product mapping endpoint and does not need one now; optionally include `woo_product_id`/`product_id` in order `items`.

## 4. Admin (ERP UI, Sanctum) — not for the plugin
`GET/POST /api/v1/marketplace/basalam/oauth/{status,config}`, `GET /api/v1/marketplace/basalam/connections`, `POST .../connections/disconnect`, Customer 360 `GET/POST/DELETE /api/v1/crm/customers/{id}/hub/basalam/stores[/{connectionId}]` (409 if linked elsewhere; `force:true` relinks and moves that store's mirrored orders).

## 5. Site-Auth secret rollout (every site must have its own secret)
- Secrets are created **only** by `POST /api/webinocrm/v1/site-auth/enroll` (site sends a one-time `pending` + `receive_url`; ERP POSTs the new secret to that URL on the claimed domain). Re-enroll of an enrolled domain needs a valid signature or `php artisan site-auth reset <domain>`. ERP never pushes a secret on its own.
- `php artisan site-auth:provision [--nudge] [--domain=x.ir]` — idempotent: lists every known site (Basalam connections + active licenses) as `enrolled|missing`; `--nudge` POSTs `{"domain"}` to `https://{domain}/wp-json/webino-dashboard/v1/site-auth/enroll-now` for missing ones. Prints no secrets.
- **Plugin must add** `POST webino-dashboard/v1/site-auth/enroll-now` (public, rate-limited, no body trust): if no secret, schedule/run `maybe_enroll()`; return `{"ok":true,"enrolled":bool}` (implemented — see §5.1). It cannot leak anything: the secret still only arrives via `/site-auth/receive` with the site's own pending token.
- Enforcement plan: (1) deploy plugin with enroll-now; (2) run `site-auth:provision --nudge` until `missing: 0`; (3) set `WEBINO_BASALAM_REQUIRE_SIGNED=true` (order mirror rejects unsigned, 401 `signature_required`) — default **off**; (4) later `WEBINO_SITE_AUTH_MODE=enforce` for all Edge routes. Plugin then treats a missing `webino_sig2` as fatal for all sites.

### 5.1 Plugin `POST /wp-json/webino-dashboard/v1/site-auth/enroll-now` (WebinaDashboard PR #6, `670d20d`)
- Public; the request body is **ignored** (ERP's `{"domain"}` is informational only). Never returns or accepts a secret.
- `200 {"ok":true,"enrolled":true}` — the site already has a secret.
- `200 {"ok":true,"enrolled":false,"scheduled":true}` — no secret yet; `maybe_enroll()` was scheduled via wp-cron (enroll waits up to 30s for ERP's callback to `/site-auth/receive`, so it is not run inline). Treat as **success**; re-run `provision` later to confirm `enrolled`.
- `429 {"ok":false,"enrolled":false,"code":"rate_limited"}` — at most 1 request / 60s per site **and** per IP. Treat as **retry later**, not failure.
- `site-auth:provision --nudge` prints per site `nudge=enrolled|scheduled|ok|retry_later|failed(<status>)|error` and always exits 0.
