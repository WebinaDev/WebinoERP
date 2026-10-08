# Employee ↔ dashboard user link

**Owner: WebinoDashboard**, not WebinoERP.

ERP already stores `hrm_employees.user_id`. ESS resolves the logged-in user with that column.

Read-only contract:

`GET /api/v1/hrm/staff/{id}/user-link`

```json
{
  "employee_id": 1,
  "employee_code": "1001",
  "user_id": null,
  "linked": false,
  "owner": "WebinoDashboard",
  "contract": {
    "read": "GET /api/v1/hrm/staff/{id}/user-link",
    "write": "WebinoDashboard sets hrm_employees.user_id. Do not create site users from this module.",
    "match": ["employee_code", "national_id", "mobile", "email"]
  }
}
```

Suggested match order for the dashboard job: national id, employee code, mobile, email. This module does not create site users and does not publish the public ATS page.
