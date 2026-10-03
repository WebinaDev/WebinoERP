# HRM — Iranian payroll year ۱۴۰۵ (1405)

## Numbers seeded (`law_year: 1405`)

All amounts in **Rials**. Sources: شورای عالی کار / بخشنامه مزد ۱۴۰۵ (public summaries on kartaban.com, hesabamooz.com, ravihesab.com) and بودجه ۱۴۰۵ tax bands.

| Key | Value (ریال) |
| --- | --- |
| `minimum_daily_wage` | 5,541,850 |
| `minimum_monthly_wage` (۳۰ روز) | 166,255,500 |
| `minimum_hourly_wage` | 756,050 |
| بن کارگری (`bon`) | 22,000,000 |
| حق مسکن (`housing`) | 30,000,000 |
| حق اولاد / فرزند (`child`) | 16,625,550 |
| پایه سنوات ماهانه (`seniority`) | 5,000,000 (روزانه 166,667) |
| حق تاهل (`marital`) | 5,000,000 |
| SSO worker / employer / unemployment | 7% / 20% / 3% |
| Tax exemption band | up to 400,000,000 @ 0% … then 10/15/20/25/30% |

Settings also store `law_year_label: ۱۴۰۵` and `law_year_note`. Override anytime via `GET/POST /api/v1/hrm/payroll/settings`.

## Eidi (عیدی) & end-of-service (سنوات پایان خدمت)

- **عیدی**: `eydi_months_factor` (2) × last monthly wage, **cap** `eydi_cap_min_wage_months` (3) × minimum monthly wage; if `eydi_prorate=true`, × months worked / 12. Paid in `eydi_month` (default 12).
- **سنوات پایان خدمت**: `severance_months_per_year` (1) × last wage × years of service (prorated by months when `severance_prorate=true`). Triggered when an employment decree of type `termination|exit|end_of_service|resign` is effective in the payroll month, or employee `contract_status` is terminated/resigned.
- Preview: `POST /api/v1/hrm/payroll/severance-preview` `{ "employee_id": … }`.

## PDF payslip / decree

Uses existing `App\Services\PdfGeneratorService` (barryvdh/laravel-dompdf when installed).

- `GET /api/v1/hrm/payroll/payslip-items/{item}/pdf?signer_name=&signer_role=`
- `GET /api/v1/hrm/payroll/decrees/{decree}/pdf?signer_name=&signer_role=`

Returns HTML always; PDF `url/path` when Dompdf is available. Organizational serials from `hrm_serial_sequences` (`PS-`, `DC-`, `PR-`). Templates include stamp placeholder + signer block.

Enable Dompdf: `composer require barryvdh/laravel-dompdf` (+ PHP `ext-dom`) in backend.

## Accounting link + SSO list

On `POST .../payroll/runs/{run}/approve` (and `.../mark-paid`): `HrmPayrollAccountingBridge` posts a journal entry when Accounting tables/chart codes exist (`payroll_expense_account_code` default `5`, payable `211`). Graceful no-op otherwise. Toggle with `payroll_accounting_enabled`.

SSO export: `GET /api/v1/hrm/payroll/runs/{run}/insurance-list?format=json|csv|html&workshop_id=`

## Multi-step approval (manager → HR → finance)

Chain in settings: `approval_chain` + `approval_role_map`. Statuses: `pending_manager` → `pending_hr` → `pending_finance` → `approved` / `rejected`.

Applies to `hrm_requests` (overtime, mission, remote, loan, advance, …) and leave requests. Cartable: `GET /api/v1/hrm/requests/inbox` returns pending items for the caller’s step roles.

## External notifications

`HrmNotifier` writes in-app `hrm_notices` and fans out via Integrations `NotificationFanout` (SMS / chat bridges) and optional `BaleBusinessService::sendMessageToUser`. No-op when channels are not configured. Toggle `external_notifications_enabled`. Kinds: `leave_approved`, `salary_paid`, `payroll_paid`, `missing_docs`, `request_approved`, `request_rejected`.

## Migrate

```bash
# from backend container / host with PHP
php artisan migrate --path=Modules/Hrm/Database/Migrations/2026_10_03_230000_hrm_1405_law_and_gaps.php
# or full
php artisan migrate
```

If PHP is missing on the host, run inside the `backend` Docker service.
