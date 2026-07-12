# Finance API

**Base:** `/api/v1/accounting`  
**Auth:** `auth:sanctum` (module license: `accounting`)  
**Explorer tag:** [ACCOUNTING](/api/explorer/#tag/ACCOUNTING)

## Overview

Accounting and finance: fiscal years, chart of accounts, invoices, receipts, checks, ledger, financial reports, and warehouse parity endpoints.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/summary` | Dashboard summary |
| GET | `/fiscal-years` | Fiscal years |
| GET | `/journals` | Journal entries |
| GET/POST/PATCH/DELETE | `/chart` | Chart of accounts |
| GET | `/invoices` | Invoices list |
| POST | `/invoices/{id}/confirm` | Confirm invoice |
| GET | `/receipts` | Receipts |
| POST | `/receipts/{id}/post` | Post receipt |
| GET | `/checks` | Checks |
| GET | `/ledger` | General ledger |
| GET | `/reports` | Financial reports |
| GET | `/warehouses` | Warehouse list (parity) |

## Legacy parity

`POST /wp-action/{action}` and `POST /warehouse-ajax/{action}` mirror WordPress admin-ajax handlers for migration.

## Related frontend

`/finance/*` — 13 dedicated finance pages with `FiscalYearProvider`
