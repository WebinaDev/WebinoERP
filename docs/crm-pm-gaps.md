# CRM and PM gaps

What this pass covers, and what is still open. Routes were not renamed.

## Done in this pass

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

1. Milestones, sprints, and epics have API models. Project detail loads milestones and does not render them. There is no milestone editor.
2. Deal cards still do not edit amount, probability, or expected close date in place. Lost-stage moves now collect a reason; other stage fields stay thin.
3. Pipelines can add a stage. Reorder, delete, and color editing are not in the UI.
4. Consultation status is free text. The `crm_consultation_statuses` lookup is not wired into the list or filters.
5. Project cards link contracts and sites. Invoices, tickets, and appointments are not linked from the project card (tickets are listed on the detail page without a ticket route).
6. Task recurrence and dependencies exist on the task payload and have no editor.
7. Customer search matches account name and website, plus contact name, email, phone, and mobile. `crm_accounts` has no email or phone columns.
8. `common.emptyValue` is still an em dash and is used on pages this pass did not touch. New copy on the polished pages uses `common.none` (ندارد).
9. Staff user list still shows `GET /api/v1/core/users`. That page is outside CRM and was left as-is.
10. ModirPayamak settings copy inside the module still says مدیرپیامک. Only the sidebar label was shortened to پیامک.
11. The legacy navigation API (`DashboardNavigationService` role menus other than the manager menu) was not regrouped. The live sidebar is `buildErpNavigation`.

## Suggested order

1. Render milestones on project detail and link invoices the same way contracts are linked.
2. Replace remaining `emptyValue` em dashes on CRM and PM forms with `none`.
3. Add pipeline stage reorder and delete, then deal amount and close date on the kanban card.
4. Bind consultation status to the lookup table.
5. Add task recurrence and dependency fields only after the list and kanban stay stable in Persian.
