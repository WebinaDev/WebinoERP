# Sales API

**Base:** `/api/v1/sales`  
**Auth:** `auth:sanctum` (module license: `sales`)  
**Explorer tag:** [SALES](/api/explorer/#tag/SALES)

## Overview

Sales catalog, campaigns, invoices with PDF/email, and subscription services.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET/POST/PATCH/DELETE | `/catalog` | Product catalog |
| GET/POST/PATCH/DELETE | `/campaigns` | Marketing campaigns |
| GET/POST/PATCH/DELETE | `/invoices` | Sales invoices |
| POST | `/invoices/{invoice}/pdf` | Generate PDF |
| POST | `/invoices/{invoice}/email` | Email invoice |
| GET | `/services/subscriptions` | Subscriptions |
| GET | `/services/products` | Service products |
| POST | `/services/subscriptions/{catalog}/convert-contract` | Convert to contract |

## Related frontend

`/sales/catalog`, `/sales/campaigns`, `/sales/invoices`
