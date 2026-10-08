# Attendance devices (ZKTeco and similar)

UI: **مدیریت منابع انسانی ← دستگاه‌های حضور و غیاب** (`/dashboard/hrm/attendance-devices`). It has four tabs:
devices, user mapping, punch log, and CSV upload.

WebinoERP takes punches over a token-protected JSON API or from CSV/attlog uploads.
A native ZKTeco SDK or an iClock/ADMS push listener is **not** included.
A small bridge (script, or the vendor PC software exporting files) has to forward punches.

## Device registry (HR manager)

| Method | Path |
| --- | --- |
| GET / POST | `/api/v1/hrm/attendance/devices` (POST returns `api_key` + `ingest_url` once) |
| PATCH / DELETE | `/api/v1/hrm/attendance/devices/{id}` |
| POST | `/api/v1/hrm/attendance/devices/{id}/rotate-key` |

Only a SHA-256 hash of the key is stored.

## Ingest API (no user session, 120 req/min)

`POST /api/v1/hrm/attendance/ingest` with headers `X-Device-Id: <device_code>` and `X-Device-Key: <api key>`.

```json
{"punches": [
  {"pin": "77", "punched_at": "2026-10-04 08:01:00", "status": 0},
  {"employee_code": "1001", "punched_at": "1405/07/12 17:02", "direction": "out"},
  {"national_id": "0499370899", "timestamp": 1791100000}
]}
```

* The code can be sent as `device_user_id`, `pin`, `uid`, `user_id`, `badge`, `ac_no` or `employee_code`.
* Time formats: ISO, Unix timestamp, or Jalali date. Persian digits are accepted.
* Direction: `in`/`out`, or ZKTeco numeric state (`1`, `2`, `5` = out; others = in).
* Up to 2000 punches per request. The response returns counts per status.

## Matching an employee

The lookup runs in this order:
1. A mapping for this device (`hrm_attendance_device_users`).
2. A global mapping (device left empty).
3. Personnel code (`employee_code`).
4. National ID (also when the code itself is a 10-digit national ID).

Mappings are managed under `GET/POST /api/v1/hrm/attendance/device-users` and `DELETE …/device-users/{id}`.

## Punch statuses

| Status | Meaning |
| --- | --- |
| `applied` | Attendance row created/updated (earliest in, latest out per day). |
| `duplicate` | Same punch again, same minute, or inside the existing in/out window. |
| `conflict` | Day has a **manual** attendance row; it is never overwritten (`manual_record_kept`). |
| `unmatched` | No employee; HR can assign it (`POST /attendance/punches/{id}/assign`, optionally remembering the mapping). |
| `invalid` | Time could not be parsed. |

Punch log: `GET /api/v1/hrm/attendance/punches?status=&device_id=&employee_id=&from=&to=`.

## CSV / attlog upload

`POST /api/v1/hrm/attendance/import-csv` (`file` or `csv`, optional `device_id`).
The separator (tab, `;` or `,`) is detected automatically.
Recognised headers include `PIN`, `AC-No.`, `No.`, `User ID`, `DateTime`, `Time`, `Date` + `Time`, `Status`/`State` (`C/In`, `C/Out`),
and the Persian headers کد پرسنلی / تاریخ / ساعت / وضعیت.
Files without headers are read as `code, datetime, status, device`. The response lists issues by line number.
