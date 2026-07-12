# webinocrm/v1 Warehouse API (legacy parity)

**Base:** `/api/webinocrm/v1`  
**Auth:** `auth:sanctum` (warehouse routes); license routes are public with HMAC signature

## Overview

Selective parity for external WordPress plugins and legacy consumers. **Not** a full migration of 200+ legacy routes — CRM/HRM/marketplace remain under `/api/v1/*`.

Warehouse data stays on Accounting tables (`acc_warehouses`, `acc_warehouse_documents`, `acc_warehouse_stock`). The ERP dashboard uses `/api/v1/scm/*` for warehouse UX.

## Response envelope

| Mode | Trigger | List shape |
|------|---------|------------|
| ERP default | (none) | `{ "data": [...], "total": N }` |
| Legacy | `?legacy=1` or header `X-Webinocrm-Legacy: 1` | `{ "success": true, "data": { "items": [...], "total": N } }` |

## Covered warehouse routes (21)

| Method | Path |
|--------|------|
| GET | `/warehouses` — pagination (`page`, `per_page`) + `search` |
| POST | `/warehouses/create` |
| POST | `/warehouses/update` |
| POST | `/warehouses/delete` |
| GET | `/products` |
| GET | `/warehouse/stock` |
| GET | `/warehouse/stock/{warehouseId}/{productId}` |
| GET/POST | `/warehouse/outbound`, `/warehouse/outbound/create`, `/warehouse/outbound/post` |
| GET | `/warehouse/outbound/{id}` |
| GET/POST | `/warehouse/inbound`, `/warehouse/inbound/create`, `/warehouse/inbound/post` |
| GET | `/warehouse/inbound/{id}` |
| GET/POST | `/warehouse/audit`, `/warehouse/audit/create`, `/warehouse/audit/record`, `/warehouse/audit/complete`, `/warehouse/audit/post` |
| GET | `/warehouse/audit/{id}` |

## Warehouse fields

`acc_warehouses` includes legacy-compatible fields: `code`, `description`, `location` (synced with `address` on write).

## License (public)

| Method | Path |
|--------|------|
| POST | `/license/check` |
| POST | `/license/activate` |
| POST | `/license/module-clone-url` |

Requires HMAC signature per legacy WordPress contract.

## Non-goals

- Full CRM/HRM/marketplace REST under `webinocrm/v1` — use `/api/v1/crm/*`, `/api/v1/hrm/*`, etc.
- OpenAPI primary spec — legacy prefix excluded from Scramble export

## Tests

`tests/Feature/WebinocrmV1ApiTest.php`
