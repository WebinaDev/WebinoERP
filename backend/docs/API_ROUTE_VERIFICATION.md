# تأیید مسیرهای API در برابر `AJAX_TO_API_INVENTORY.md`

این سند وضعیت **پیاده‌سازی مسیرهای Laravel** را در برابر نقشهٔ [AJAX_TO_API_INVENTORY.md](./AJAX_TO_API_INVENTORY.md) گزارش می‌کند. تاریخ بررسی: 2026-04-22 (Phase 1).

## قرارداد کلی

| پیشوند ماژول | فایل مسیر | میان‌افزار معمول |
|----------------|-----------|-------------------|
| `/api/v1/core` | [Modules/Core/Routes/api.php](../Modules/Core/Routes/api.php) | `api`؛ بیشتر با `auth:sanctum` |
| `/api/v1/crm` | [Modules/Crm/Routes/api.php](../Modules/Crm/Routes/api.php) | `api`, `auth:sanctum` |
| `/api/v1/projects` | [Modules/Projects/Routes/api.php](../Modules/Projects/Routes/api.php) | `api`, `auth:sanctum` (گروه) |
| `/api/v1/accounting` | [Modules/Accounting/Routes/api.php](../Modules/Accounting/Routes/api.php) | `api`, `auth:sanctum` |
| `/api/v1/integrations` | [Modules/Integrations/Routes/api.php](../Modules/Integrations/Routes/api.php) | `api` (بخشی با sanctum) |
| سازگاری `webinocrm/v1` | [Modules/Accounting/Routes/webinocrm-v1.php](../Modules/Accounting/Routes/webinocrm-v1.php)، ثبت در `IntegrationsServiceProvider` / `AccountingServiceProvider` | مطابق هر فایل |

## نتیجهٔ خلاصه

| ناحیه | وضعیت | یادداشت |
|--------|--------|---------|
| Login & auth (inventory) | **پیاده شده** | مسیرها در `Core/Routes/api.php`؛ `autoLogin` در فاز ۱ به جریان Signed Token یک‌بارمصرف ارتقا یافت؛ admin endpoint `/auth/auto-login/issue` اضافه شد. |
| Field Security (`webinocrm_update_field_permissions`) | **پیاده شده (فاز ۱)** | جدول `core_field_permissions` + `FieldSecurityService` + Middleware `fieldsec` + endpoint‌های GET/PUT/DELETE/viewable. |
| Branding (`class-white-label.php`) | **پیاده شده (فاز ۱)** | `GET /api/v1/core/branding.css` و `GET /api/v1/core/branding` (JSON). |
| Roles & Capabilities | **پیاده شده (فاز ۱)** | Migration `2026_04_24_000002_seed_phase1_roles.php` + سری کامل permissions حسابداری. |
| Data Encryption | **پیاده شده (فاز ۱)** | `CoreDataEncryption` + auto-encrypt در `IntegrationSetting` برای credential keys. |
| Cache | **پیاده شده (فاز ۱)** | `CoreCacheService` با prefix / tag-index / entity invalidation. |
| Error Codes | **پیاده شده (فاز ۱)** | `App\Support\ErrorCodes` با ثابت‌های parity + `ErrorCodes::respond()`. |
| DB Optimizer cron | **پیاده شده (فاز ۱)** | Command `webino:db-optimize` + Schedule هفتگی + ثبت در `core_cron_runs`. |
| System Logger | **توسعه‌یافته (فاز ۱)** | ستون‌های `severity`, `error_code`, `ip`, `user_agent` + سرویس `SystemLogger`؛ فیلترهای GET `/logs/system`. |
| SMS / payments | **مسیرها در Integrations** | با `integrations` module routes تطبیق دهید. |
| Leads / CRM | **پیاده شده** | `Crm/Routes/api.php` + `LeadController` / `CrmParityController`. |
| Settings / users / canned / positions / task-categories | **پیاده شده** | `Core/Routes/api.php` + `SettingsParityController` / `CrudParityController`. |
| Projects / contracts / tasks / tickets / invoices / appointments | **پیاده شده** | `Projects/Routes/api.php`؛ dedicated controllers (phase 8); `ProjectsParityController` deprecated. |
| Accounting / warehouse ajax | **پیاده شده** | `Accounting/Routes/api.php`؛ dedicated controllers + `AccountingWpActionController` / `WarehouseAjaxParityController`. |
| Dashboard / logs / reports / licenses / visitor | **پیاده شده** | `ReportsController` + `VisitorStatsController` (aggregate-aware); `DashboardParityController` for dashboard widgets only. |
| Team chat (فاز ۴) | **پیاده شده** | `GET/POST /api/v1/core/chat/*` + Reverb-compatible events؛ `routes/channels.php` برای `private-chat.{id}`. |
| Forms عمومی | **پیاده شده** | `POST /api/v1/forms/{slug}/submit` (عمومی)؛ CRUD ادمین تحت `/api/v1/projects/forms*`. |
| Elementor → Lead | **پیاده شده** | `POST /api/v1/crm/leads/elementor` + امضای `X-Webino-Signature` (HMAC). |
| نگهداری / کش | **پیاده شده** | `POST /api/v1/core/maintenance/optimize`، `POST .../cache/clear`، `GET .../cache/stats` (نقش `system_manager`). |
| PWA manifest | **پیاده شده** | `GET /api/v1/core/manifest.json`. |
| جستجو / آنالیتیکس | **پیاده شده (حداقل)** | `GET /api/v1/core/search`، `GET /api/v1/core/analytics/kpi|funnel|cohort`. |

## فاز ۲ — ماژول‌های Backend (2026-07-08)

| ناحیه | وضعیت | یادداشت |
|--------|--------|---------|
| HRM nested (`/api/v1/hrm/*`) | **پیاده شده** | ~45 route parity: staff/profile, org-positions, leave types/approve, payroll runs/calculate, recruitment, performance, training sessions |
| SCM warehouse (`/api/v1/scm/*`) | **پیاده شده** | Owned `scm_*` schema + `Scm\Services\WarehouseService`; upgrade via `scm:migrate-from-accounting` |
| ModirPayamak (`/api/v1/integrations/modirpayamak/*`) | **پیاده شده** | Edge client + domain wallet + topup؛ admin dashboard/proxy |
| Marketplace modules (`/api/v1/marketplace/modules*`) | **پیاده شده** | modules, releases, repo sync, gitea/test |
| CRM spec (`/api/v1/crm/deals/*/move`, kanban, sources, activities) | **پیاده شده** | |
| Docs manager (`/api/v1/docs/files/*`) | **پیاده شده** | upload/download, folders, share, contract cancel/projects |
| Sales services (`/api/v1/sales/services/*`, invoice pdf/email) | **پیاده شده** | |

تست‌های Feature: `HrmApiTest`, `ScmApiTest`, `CrmDealsApiTest`, `MarketplaceApiTest`, `ModirPayamakApiTest`, `SmsIntegrationApiTest`, `BaleBusinessApiTest`, `DocsApiTest`, `SalesApiTest`, `RbacMatrixTest`, `AccountingApiTest`, `ProjectsApiTest`, `CoreRbacApiTest`, `CoreReportsApiTest`, `CoreVisitorStatsApiTest`, `AuthOtpApiTest`, `AuthApiTest`, `CoreConfigApiTest`.

## فاز ۸ — Backend Depth (2026-07-08)

| ناحیه | وضعیت | یادداشت |
|--------|--------|---------|
| Marketplace orders CRUD | **پیاده شده** | `OrderController` apiResource؛ `MarketplaceApiTest` orders flow |
| Core reports | **پیاده شده** | `ReportsController` — metrics + daily `series` + CSV export |
| Visitor stats aggregate | **پیاده شده** | `VisitorStatsController` reads `core_visitor_daily` when available |
| Projects parity→real | **پیاده شده** | `ProjectController`, `ContractController`, `TaskController`, `TicketController`, `SprintController`, `AppointmentController`, `ProjectInvoiceController`, `WorkflowController`, `SubscriptionController`, `ProjectProductController` |
| Accounting parity→real | **پیاده شده** | `ChartAccountController`, `PersonController`, `ProductController`, `AccInvoiceController`, `ReceiptController`, `CheckController`, `CashAccountController`, `LedgerController`, `FinancialReportsController`, `AccountingSettingsController`, `WarehouseReadController` |
| SCM owned schema | **پیاده شده** | `scm_warehouses`, `scm_warehouse_documents`, `scm_warehouse_stock`; `scm:migrate-from-accounting` command |

## RBAC (فاز ۵)

| لایه | پیاده‌سازی |
|------|------------|
| Permissions | `RolesAndPermissionsSeeder` — `hrm.*`, `scm.*`, `sales.*`, `docs.*`, `marketplace.*`, `integrations.modirpayamak.*` + نقش‌های ۵گانه |
| Route middleware | `EnforceModulePermission` (`module.permission:{module}`) + `config/module_permissions.php` (segment → view/manage) |
| Policies | `LeadPolicy`, `CrmAccountPolicy`, `HrmEmployeePolicy`, `ProjectPolicy`, `ProjectTaskPolicy`, `ModirPayamakPolicy` (Gate) |
| Auth payload | `GET /api/v1/core/auth/user` → `roles[]`, `permissions[]`, `licensed_modules[]` (+ `active_modules` سازگاری) |
| Frontend | `usePermissions`, `PermissionGate`, `ModuleRouteGuard`, `module-license-map.ts`, `requiredPermission` در registry |
| Tests | `RbacMatrixTest` (403 per role); `CoreRbacApiTest`; existing Feature tests با `SeedsRbac` trait |

**نقشه نمونه permissions (اولین segment بعد از `/api/v1/{module}/`):**

| ماژول | segment | view | manage |
|-------|---------|------|--------|
| core | settings | `core.settings.view` | `core.settings.manage` |
| crm | leads | `crm.leads.view` | `crm.leads.manage` |
| hrm | employees | `hrm.staff.view` | `hrm.staff.manage` |
| accounting | * | `accounting.view` | `accounting.manage` |
| integrations | modirpayamak | `integrations.modirpayamak.view` | `integrations.modirpayamak.manage` |

Webhookهای عمومی (`bale/webhook`, `telegram/webhook`, `payments/verify`, `forms/{slug}/submit`) بدون `permission:` — فقط throttle/signature.

## نکتهٔ تفاوت با وردپرس

- اکشن‌های `admin-ajax.php` با نام‌های `webino_*` به مسیرهای REST با بدنهٔ JSON نگاشت شده‌اند؛ رفتار دقیق هر اکشن به تکمیل منطق داخل کنترلر بستگی دارد (بخشی عمداً stub برای parity است).

## اقدام بعدی (نگهداری)

هنگام افزودن endpoint جدید:

1. ردیف مربوط را در `AJAX_TO_API_INVENTORY.md` به‌روز کنید.
2. مسیر را در فایل `Routes/api.php` ماژول مناسب اضافه کنید + entry در `config/module_permissions.php`.
3. یک تست ویژگی یا `RbacMatrixTest` case در صورت امکان اضافه کنید.
4. spec OpenAPI را بازتولید کنید: `cd backend && composer export-openapi`

## فاز ۹ — Integrations (2026-07-08)

| ناحیه | وضعیت | یادداشت |
|--------|--------|---------|
| ModirPayamak tickets/users/drafts | **پیاده شده** | `POST .../modirpayamak/admin/proxy` + FE `modirpayamak-edge.ts`; mock lists in `ModirPayamakEdgeClient` |
| SMS settings unified | **پیاده شده** | `IntegrationSetting` canonical؛ mirror از `SettingsParityController`؛ `GET/PUT /v1/integrations/sms/settings` |
| SMS production validation | **پیاده شده** | `php artisan webino:integrations:validate`؛ guide `docs/guides/sms-production.md` |
| Bale namespace fix | **پیاده شده** | `Services/Bale/BaleApiClient.php` |
| Bale business UI | **پیاده شده** | `features/modules/admin/integrations/bale/` → `webinocrm/v1/bale/*` |
| Bale webhook menu/callback | **پیاده شده** | `BaleWebhookHandler` — start, support, FAQ, plan_click flows |
| Tests | **پیاده شده** | `ModirPayamakApiTest` (proxy), `SmsIntegrationApiTest`, `BaleBusinessApiTest`, `AuthOtpApiTest` (production debug_code) |

## فاز ۱۰ — MFG & Legacy (2026-07-08)

| ناحیه | وضعیت | یادداشت |
|--------|--------|---------|
| MFG module (`/api/v1/mfg/*`) | **پیاده شده** | BOM، work orders، QC، MRP read-only؛ `MfgApiTest` |
| MFG frontend | **پیاده شده** | `/mfg/*` pages + `mfg.*` i18n + module-registry children |
| hosting-infra | **پیاده شده** | `/admin/hosting-infra` nav؛ `hosting.*` i18n؛ `HostingApiTest`؛ Playwright smoke |
| webinocrm/v1 warehouse | **پیاده شده** | Legacy envelope (`?legacy=1` / header)؛ warehouse fields + pagination؛ `WebinocrmV1ApiTest` |
| Docs | **پیاده شده** | `api/modules/mfg.md`؛ `api/legacy/webinocrm-v1.md`؛ `api/legacy/webinocrm-hosting.md` |

تست‌های Feature جدید: `MfgApiTest`, `HostingApiTest`, `WebinocrmV1ApiTest`.

## فاز ۱۱ — Test & Performance Expansion (2026-07-08)

| ناحیه | وضعیت | یادداشت |
|--------|--------|---------|
| Route manifest | **پیاده شده** | `route-inventory.mjs` → `generate-route-manifest.mjs` → `routes.manifest.json` (FE + BE fixtures) |
| PHPUnit matrix | **پیاده شده** | `ApiRouteMatrixTest` — `@group matrix`; data provider from manifest; 84 GET smoke endpoints |
| Feature smoke expansion | **پیاده شده** | `CrmDealsApiTest`, `HrmApiTest`, `ScmApiTest`, `CoreRbacApiTest`, `MarketplaceApiTest` list GETs |
| Playwright route matrix | **پیاده شده** | `e2e/route-matrix.spec.ts` — ~84 authenticated routes; `@slow` tag; CI workers=2 |
| TTFB CI gate | **پیاده شده** | `perf-budget.mjs` — fail TTFB > 600ms in CI; `perf-summary.json` artifact |
| API p95 smoke | **پیاده شده** | `api-p95-smoke.mjs` — 10 endpoints × 5 samples; budget 300ms; `api-perf-summary.json` |
| Lighthouse URLs | **پیاده شده** | login, dashboard, crm/leads, finance/journals, scm/inbound, hosting-infra |
| Docs | **پیاده شده** | `docs/guides/testing.md` |

تست‌های Feature: 22+ files including `ApiRouteMatrixTest` (matrix group filterable in CI).

## فاز ۱۲–۱۷ — Full Completion (2026-07-08)

تست‌های Feature جدید: `CrmLeadAdvancedTest`, `AccountApiTest`, `HealthReadinessApiTest`, `CrmAutomationTest`, `PageStates.test.tsx` (Vitest).

| فاز | ناحیه | وضعیت |
|-----|--------|--------|
| 12 | i18n + PageStates + check-i18n strict + LeadsListPage i18n | **پیاده شده** |
| 13 | AppliesIndexQuery via PaginatesApi; SystemLogController; Gitea FormRequest; Sales/Docs index | **پیاده شده** |
| 14 | CRM automation dispatcher; LeadsListPage convert/merge/bulk UI; Customer360 activities | **پیاده شده** |
| 15 | CrmAutomationTest; Vitest PageStates; CI coverage gates | **پیاده شده** |
| 16 | docker healthchecks; config/backup.php; health in p95 smoke + artifact | **پیاده شده** |
| 17 | auth rate-limit; 2FA admin endpoints; nginx CSP; audit fail CI | **پیاده شده** |

## OpenAPI (فاز ۴)

- **Regenerate:** `cd backend && composer export-openapi`
- **Spec:** `docs-site/openapi/openapi.json` (~370 paths under `/api/v1/*`)
- **Explorer:** Docusaurus Redoc at `/docs/api/explorer/`
- **CI:** backend job fails if committed spec is stale (`git diff --exit-code`)
- **Legacy excluded:** `api/webinocrm/v1/*`, `api/woobale/v1/*` not in primary spec
