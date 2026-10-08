# تأمین اجتماعی (SSO) list and diskette export

UI: **مدیریت منابع انسانی ← لیست بیمه تأمین اجتماعی** (`/dashboard/hrm/sso-list`).
It has a workshop section, a Jalali year/month picker, a preview table with per-row warnings,
and download buttons. A payroll run detail page also has run-level downloads.

## Endpoints

| Endpoint | Purpose |
| --- | --- |
| `GET /api/v1/hrm/payroll/insurance-list?year=1405&month=7&format=…` | Whole period. Uses the latest item per employee from runs in status calculated/approved/paid. |
| `GET /api/v1/hrm/payroll/runs/{id}/insurance-list?format=…` | One payroll run. |

Use a Jalali year (< 1700) for Jalali periods. A Gregorian year also works.

| `format` | Output |
| --- | --- |
| *(none)* | JSON `{header, rows, count, totals, warnings}` |
| `zip` | `DSKKAR00.DBF` + `DSKWOR00.DBF` in one archive |
| `dbf_wor` / `dbf_kar` | One dBase III file each |
| `dskwor` (`txt`, `diskette`) / `dskkar` | UTF-8 comma-delimited text, same columns as the DBF |
| `list` | Persian-header CSV (لیست بیمه) |
| `csv` | English-header CSV |

## Workshop settings

These are saved in payroll settings (`POST /api/v1/hrm/payroll/settings`) and edited on the SSO page:
`workshop_code` (کد کارگاه), `workshop_name`, `employer_name`, `workshop_address`,
`sso_list_no` (default `01`), `sso_contract_row` (ردیف پیمان, default `000`),
`sso_list_description`, `employer_insurance_percent`.

Each employee needs these profile fields: `insurance_number`, `national_id`, `father_name`, `birth_date`, `gender`.
They can be edited on the staff profile tab or imported from Excel.
Each row can carry these warnings: `missing_insurance_no`, `missing_national_id`, `invalid_national_id`, `missing_workshop_code`.

## DBF details (what is real, what is not)

* The files are real dBase III (`0x03`) and are written in pure PHP (`Modules/Hrm/Support/DbfWriter.php`). No extension or vendor SDK is needed.
* Character fields use **Windows-1256 (CP1256)**, and the language driver byte is set to `0x57`.
  CP1256 has no Persian `ی`, so it is written as Arabic `ي` (the usual practice).
  `ک گ پ چ ژ` are kept.
* Field names, types and widths follow the publicly documented DSKKAR00 / DSKWOR00 layout.
  This includes DSW_ID = workshop code, DSW_ID1 = insurance number, DSW_YY = 2-digit year,
  and DSK_TKOSO = employer share. See `HrmInsuranceListService::DSKKAR_FIELDS` / `DSKWOR_FIELDS`.
* **Not certified.** The layout has not been validated against the official SSO
  "لیست بیمه" software or the e-services portal. Before the first real submission,
  open the files in the SSO software (or with your insurance broker) and compare them.
  Older tools that expect **Iran System** encoding are not supported.
* Worked days come from the employee's hire date and end date inside the Jalali month,
  up to 31 days. Daily wage = monthly wage / days.
