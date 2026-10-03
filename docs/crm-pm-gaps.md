# CRM and PM gaps

What this pass covers, and what is still open. Routes were not renamed.

## Done in this pass

- Project detail lists and edits milestones, sprints, and epics (create, update, delete; sprint start and finish).
- Deal cards edit amount, probability, and expected close date in place.
- Pipeline stages can be reordered, recolored, and deleted. Adding a stage still works and now sends order and color.
- Consultation list and filters use `crm_consultation_statuses` instead of a free-text status.
- Project cards and project detail link invoices, tickets, and appointments. Ticket subjects open `crm/tickets?ticket_id=`.
- Task editor saves recurrence and dependencies.
- `common.emptyValue` is «ندارد» in Persian and "None" in English, matching `common.none`. Empty CRM and PM values use that label.
- ModirPayamak settings and module titles say پیامک.

- Persian copy on deals, pipelines, consultations, customers, projects, and tasks (titles, columns, filters, empty states, status and priority chips, toasts).
- Shared `formatDigits` / `formatNumber` for Persian digits on those pages, plus pagination.
- RTL direction and start alignment on the customers table, project cards and detail tables, and the tasks board and list.
- Consultations no longer show a raw `GET /api/v1/crm/consultations` label.
- Project list and project detail attach related contracts and site-builder entries. Contract links open `docs/contracts?contract_id=`. Builder links open `admin/platform/sites/{id}`. A live site link appears when the provision domain is ready, active, or launched.
- Customer list shows the primary contact phone.
- Dragging a deal onto a lost stage asks for a loss reason before the move.
- Dashboard home (staff) and reports show CRM/PM count cards and a real todos widget: overdue, due today, and assigned to the current user.
- ERP sidebar: توزیع, بازارچه, لایسنس, and سایت عمومی sit under پلتفرم. The ModirPayamak nav label is پیامک. Paths are unchanged.

## Still open

1. Customer search matches account name and website, plus contact name, email, phone, and mobile. `crm_accounts` has no email or phone columns.
2. Staff user list still shows `GET /api/v1/core/users`. That page is outside CRM and was left as-is.
3. The legacy navigation API (`DashboardNavigationService` role menus other than the manager menu) was not regrouped. The live sidebar is `buildErpNavigation`.
