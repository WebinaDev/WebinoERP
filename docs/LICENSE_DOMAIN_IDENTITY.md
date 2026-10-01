# License identity = domain (+ product)

## Product rule

- **License identity:** tenant `domain` + optional `product` (e.g. `bluecafe.webinaagency.ir` + `webinodashboard`).
- **No license code.** Operators never paste a key into Dashboard.
- **HMAC is optional service auth** between Dashboard and ERP — **not** license identity.
  - Do **not** require `WEBINOCRM_LICENSE_HMAC_SECRET` on each tenant for entitlement.
  - UI must never block with «License HMAC secret is not configured» when domain is set.

## Dashboard → ERP

1. Set `WEBINO_BASE_URL` (public ERP URL) and tenant `domain` (usually already set).
2. Call `POST /api/webinocrm/v1/license/check` with `{ domain, product, ts }` — signature only if a shared deploy secret is present.
3. ERP returns `active` / `demo` / `expired` / `invalid` for that domain (+ product).

## Deploy / bluecafe

After this change, a tenant typically needs **nothing new** except:

- `WEBINO_BASE_URL` pointing at ERP
- Domain already configured on the tenant

Optional: shared `WEBINOCRM_LICENSE_HMAC_SECRET` on ERP + tenants (injected by Platform provisioner) for signed service calls. Entitlement still works without it.

## Contract (ERP)

- **Entitlement lookup:** `domain` + optional `product` (slug). No license code.
- **HMAC (service auth only):** canonical payload `domain|{product}|ts`.
  - Legacy WP / old Dashboard signatures `domain|{license_key}|ts` still verify; `license_key` is **ignored** for entitlement.
  - If WP posts `license_key` that looks like a hostname and `domain` is empty, it is treated as domain.
- **Endpoints:** `POST /api/webinocrm/v1/license/check|activate|module-clone-url` (and marketplace / ledger / ModirPayamak compat).
  - `check` allows **unsigned** domain-status (rate-limited). Bad signatures still rejected when a secret is configured.
- **Activate body:** `domain` required; `product` optional (default `webino` / `webinodashboard`); `license_key` optional deprecated.

## DB

- Column `core_licenses.product` (default `webino`); unique `(domain, product)`.
- Column `license_key` **deprecated** — filled with internal placeholder `dom:{domain}[:product]` for unique/compat only. Not shown in UI; not required from clients.

## WP license-management

Old WordPress WebinaDashboard / license-management clients may still POST `license_key`. ERP accepts the field for HMAC/compat, maps domain from `domain` (or Host / domain-like key), and checks status by domain + product.

## Domain family (webina.dev ↔ webinaagency.ir)

`CoreLicenseResolver` treats these apexes as one license host family:

- `webina.dev`
- `webinaagency.ir`

A license registered as `bluecafe.webinaagency.ir` also matches checks for `bluecafe.webina.dev` (and the reverse). Apex↔apex is included (`webinaagency.ir` ↔ `webina.dev`). Other domains are exact-match only.

## WEBINO_BASE_URL (Dashboard → ERP)

| Deploy | Expected value | License path |
|--------|----------------|--------------|
| Same-VPS tenant (Platform local) | `http://erp-backend:8080` | `POST /api/webinocrm/v1/license/check` |
| Public / remote tenant | `https://webinaagency.ir` (or configured `public_crm_url`) | same path |

Full URL example: `POST https://webinaagency.ir/api/webinocrm/v1/license/check` with JSON `{ "domain", "product", "ts" }` (signature optional).

Same-VPS uses Docker DNS so license checks do **not** hairpin through the public CDN. ERP `backend` must join `webino_sites` with alias `erp-backend` (see `docker-compose.yml`).
