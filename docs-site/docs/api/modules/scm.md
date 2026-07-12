# SCM API

**Base:** `/api/v1/scm`  
**Auth:** `auth:sanctum` (module license: `scm`)  
**Explorer tag:** [SCM](/api/explorer/#tag/SCM)

## Overview

Supply chain / warehouse management. Uses `WarehouseService` bridge to Accounting `AccWarehouse*` entities (no separate SCM migrations).

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET/POST/DELETE | `/warehouses` | Warehouse CRUD |
| GET | `/stock` | Stock levels |
| GET | `/stock/{warehouseId}/{productId}` | Stock detail |
| GET/POST | `/inbound` | Inbound documents |
| POST | `/inbound/post` | Post inbound |
| GET/POST | `/outbound` | Outbound documents |
| POST | `/outbound/post` | Post outbound |
| GET/POST | `/audit` | Stock audit |
| POST | `/audit/post` | Post audit |

## Workflow

Create document → fill lines → `post` to affect stock. Mirrors legacy warehouse AJAX parity.

## Related frontend

`/scm/warehouses`, `/scm/inbound`, `/scm/outbound`, `/scm/audit`, `/scm/stock`
