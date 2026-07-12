# MFG API

**Base:** `/api/v1/mfg`  
**Auth:** `auth:sanctum` + `module:mfg` + `module.permission:mfg`  
**License:** System module slug `mfg` (disabled by default in seeder)

## Overview

Manufacturing module: bills of material (BOM), work orders, quality inspections, and read-only MRP planning. Products reference Accounting `acc_products` — no duplicate catalog.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/overview` | Dashboard counts by status |
| GET/POST/PUT/DELETE | `/boms` | BOM CRUD (lines synced on create/update) |
| GET | `/boms/{id}/lines` | BOM line items |
| GET/POST/PUT/DELETE | `/work-orders` | Work order CRUD |
| POST | `/work-orders/{id}/release` | Release draft work order |
| POST | `/work-orders/{id}/start` | Start production |
| POST | `/work-orders/{id}/complete` | Complete (optional SCM material consumption) |
| POST | `/work-orders/{id}/cancel` | Cancel work order |
| GET/POST/PUT/DELETE | `/inspections` | Quality inspections |
| POST | `/inspections/{id}/complete` | Complete inspection (pass/fail aggregate) |
| GET | `/planning/mrp?horizon_days=30` | MRP shortages (read-only) |

## Permissions

| Segment | view | manage |
|---------|------|--------|
| boms | `mfg.boms.view` | `mfg.boms.manage` |
| work-orders | `mfg.work_orders.view` | `mfg.work_orders.manage` |
| quality | `mfg.quality.view` | `mfg.quality.manage` |
| planning | `mfg.planning.view` | — |

## Related frontend

`/mfg`, `/mfg/boms`, `/mfg/work-orders`, `/mfg/quality`, `/mfg/planning`

## Tests

`tests/Feature/MfgApiTest.php`
