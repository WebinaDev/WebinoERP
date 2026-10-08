# HR approval flows, offboarding, staff import, workforce budget

## Approval flows (`/dashboard/hrm/approval-flows`)
`GET/POST /api/v1/hrm/approval-flows`, `DELETE /approval-flows/{id}`, user search `GET /approval-flows/users?search=`.

* Request types: `leave`, `overtime`, `mission`, `remote`, `loan`, `advance`, `timesheet`.
* Each request type gets ordered steps (up to 8). A step is one of:
  * `direct_manager`: the incumbent of the parent position in the org chart. HR manager is the fallback.
  * `role`: any spatie role.
  * `user`: specific users.
* Without a custom flow, the default chain from payroll settings applies (`approval_chain`, `approval_role_map`).
  Under the default chain, `hr_manager` may act on both the manager and HR steps.
* Cartable: `GET /api/v1/hrm/requests/inbox` returns only the requests and leaves the caller can act on now.
  Actions: `POST /requests/{id}/approve|reject` and `POST /requests/leaves/{id}/approve|reject`.
  Any signed-in user can open the inbox. Portal page: `/dashboard/hrm/my-approvals`. Manager page: `/dashboard/hrm/cartable`.

## Offboarding (`/dashboard/hrm/offboarding`, portal `/dashboard/hrm/my-offboarding`)
* Templates: `GET/POST /offboarding/templates`, `PUT/DELETE /offboarding/templates/{id}`.
* Cases:
  * `GET /offboarding`
  * `POST /offboarding/start {employee_id, template_id, last_day, reason}`
  * `PATCH/DELETE /offboarding/{id}`
* Tasks: `PATCH /offboarding/tasks/{id} {status}`.
* Portal:
  * `GET /me/offboarding`
  * `POST /me/offboarding/tasks/{id}/complete` (only tasks owned by the employee)
  * `PATCH /me/offboarding/{id}` (exit interview notes)
* Progress counts done and skipped tasks. When every required task is closed, the case is completed and the employee status becomes `terminated`.

## Staff Excel/CSV (`/dashboard/hrm/staff-import`)
* `GET /staff/import-template?format=xlsx|csv` (Persian headers plus one sample row).
* `GET /staff/export?format=xlsx|csv`.
* `POST /staff/import` with `file` and `dry_run=1|0` returns `{created, updated, skipped, total, errors[{row, field, code, value}]}`.
* Rows match by personnel code.
* Validation covers:
  * national ID checksum and uniqueness
  * mobile, email and IBAN
  * status (Persian aliases accepted)
  * numbers, and Jalali or Gregorian hire dates

## Workforce budget (`/dashboard/hrm/workforce-budget`, also on the analytics page)
* `GET /analytics/budgets?year=1405&month=7` returns budget vs actual per department.
* `POST /analytics/budgets {department, year, month|null, headcount, cost_budget}`.
* `DELETE /analytics/budgets/{id}`.
* A monthly budget wins over an annual one. An annual budget is divided by 12 for a monthly view.
* Actual cost = gross + employer insurance from calculated/approved/paid runs in that period.
* Actual headcount = current active employees. There is no historical headcount snapshot.
