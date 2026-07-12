# Docs API

**Base:** `/api/v1/docs`  
**Auth:** `auth:sanctum` (module license: `docs`)  
**Explorer tag:** [DOCS](/api/explorer/#tag/DOCS)

## Overview

Document management: contracts and file manager with upload, download, folders, sharing, and versions.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET/POST/PATCH/DELETE | `/contracts` | Contracts CRUD |
| POST | `/contracts/{contract}/cancel` | Cancel contract |
| POST | `/contracts/{contract}/projects` | Link project |
| GET/POST | `/files` | List / upload files |
| GET | `/files/{file}/download` | Download file |
| PATCH | `/files/{file}` | Update metadata |
| POST | `/files/folders` | Create folder |
| POST | `/files/{file}/share` | Share file |
| GET | `/files/{file}/versions` | Version history |
| DELETE | `/files/{file}` | Delete file |

## Related frontend

`/docs/contracts`, `/docs/files`
