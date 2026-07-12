# Marketplace API

**Base:** `/api/v1/marketplace`  
**Auth:** `auth:sanctum` (module license: `marketplace`)  
**Explorer tag:** [MARKETPLACE](/api/explorer/#tag/MARKETPLACE)

## Overview

Distribution marketplace: products, categories, orders, Gitea integration, and module/release management.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET/POST/PATCH/DELETE | `/products` | Marketplace products |
| GET/POST/PATCH/DELETE | `/categories` | Categories |
| GET | `/orders` | Orders list |
| GET/PUT | `/gitea/settings` | Gitea connection |
| POST | `/gitea/test` | Test Gitea connection |
| GET/POST/PATCH/DELETE | `/modules` | ERP modules |
| POST | `/modules/{module}/repo` | Attach Git repo |
| POST | `/modules/{module}/repo/sync` | Sync repo |
| GET/POST | `/modules/{module}/releases` | Module releases |
| POST | `/releases/{release}/publish` | Publish release |

## Related frontend

`/admin/marketplace/products`, `/admin/marketplace/modules`, `/admin/marketplace/gitea`
