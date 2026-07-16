# @webina/erp-sdk

TypeScript client for the WebinoERP API.

## Regenerate types

```bash
cd WebinoERP/backend && composer openapi
cd ../sdk/typescript
npm install
npm run generate
npm run build
```

## Usage

```ts
import { ErpClient } from "@webina/erp-sdk"

// Browser / SPA (HttpOnly cookie)
const spa = new ErpClient({ baseUrl: "https://erp.example.com", credentials: "include" })

// Server-to-server
const api = new ErpClient({ baseUrl: "https://erp.example.com", token: process.env.ERP_TOKEN! })

await spa.login("admin@example.com", "secret")
const user = await spa.getUser()
```
