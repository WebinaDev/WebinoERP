# HRM API

**Base:** `/api/v1/hrm`  
**Auth:** `auth:sanctum` (module license: `hrm`)  
**Explorer tag:** [HRM](/api/explorer/#tag/HRM)

## Overview

Human resource management with nested parity routes for staff profiles, attendance, leave, payroll, recruitment, performance, and training.

## Key endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET/POST | `/staff` | Staff list / create |
| GET/POST | `/staff/{staff}/profile` | Staff profile |
| POST | `/attendance/check-in` | Check in |
| POST | `/attendance/check-out` | Check out |
| GET/POST | `/leave/requests` | Leave requests |
| POST | `/leave/requests/{id}/approve` | Approve leave |
| GET/POST | `/payroll/runs` | Payroll runs |
| POST | `/payroll/runs/{run}/calculate` | Calculate payroll |
| GET/POST | `/recruitment/postings` | Job postings |
| GET/POST | `/performance/reviews` | Performance reviews |
| GET/POST | `/training/sessions` | Training sessions |
| GET/POST | `/employees` | Legacy flat CRUD |

## Nested vs flat routes

Nested routes (`/staff`, `/payroll/runs`, …) power the dedicated HRM UI. Flat `apiResource` routes remain for `EntityCrudPage` compatibility.

## Related frontend

`/hrm/staff`, `/hrm/attendance`, `/hrm/leave`, `/hrm/payroll`, `/hrm/recruitment`
