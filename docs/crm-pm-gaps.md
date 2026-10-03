# CRM and PM gaps

Routes were not renamed. Existing Persian CRM/PM polish, planning panels, impersonation, and the marketing site stay in place.

## Shipped

### CRM

- Unified activity timeline on an account or deal: call, email, note, meeting, and SMS, with a reminder date. Due reminders are dispatched by `crm:dispatch-reminders`.
- Lead score rules (field and activity) on top of the previous built-in scoring. Completing a timeline item rescores the lead.
- Automation on CRM events: stage or status change can create a task, send an in-app notification, send SMS, or call a webhook.
- Message templates, one-off email and SMS, and nurture sequences. SMS uses ModirPayamak when that edge is configured, then the existing SMS provider, and otherwise logs the message. Due steps run with `crm:run-sequences`.
- Sales forecast: open funnel, weighted amount, win and loss, loss reasons, owner split, and quota attainment.
- Duplicate detection and merge for accounts and contacts. Contact CSV export and import. Custom fields and values.
- Tags, segments, and marketing lists that can point at a sales campaign.

### PM

- Kanban stays. Gantt rows include real `depends_on` links and task start times. Workload groups open tasks, overdue counts, estimates, and logged hours.
- Project budget against logged time (hours and cost). Delay alerts can notify assignees. Timesheets remain the existing time entries.
- Project files with versions and a client-share flag. Task comments notify @mentions in the app and by email when mail is configured.
- Project templates copy tasks (including checklists and estimates) and milestones. Ticket SLA due times are set from priority, first response is stamped, and a SLA report is available.
- Client portal summary includes shared files and approvals. Customers can download a shared file and approve, request changes, or reject.

### Shared

- Finer permissions for activities, messages, forecast, audiences, CRM audit, workload, files, and project audit.
- Audit log of CRM and PM mutations, with a page for each module.
- Global search covers accounts, contacts, deals, leads, projects, and tasks.
- Dashboard cards for weighted forecast, open reminders, delayed tasks, and SLA breaches. Each user can hide those cards.

Calendar and team-chat connectors (Google, Outlook, Slack, Bale) have a settings screen. Without a webhook they stay in a test state. A webhook is posted when one is saved. The chat page links to that screen.

## Deferred

- Google and Outlook OAuth and a live two-way calendar sync.
- A live Slack or Bale bridge beyond the webhook test.
- A full email client. Outbound mail is a plain message from a template.
- Sequence editing in the screen is a single step. More steps can be stored by the API.
- Quota entry asks for a numeric user id.
- Segment creation starts with empty filters. Preview still applies saved filters.
- `crm_accounts` still has no email or phone columns. Search uses name, website, code, and related contacts.
- Staff user list still shows the users API label. That page is outside CRM.
- Legacy `DashboardNavigationService` role menus were not regrouped. The live sidebar is `buildErpNavigation`.
- Win and loss is on the forecast page. The dashboard catalog includes that key, and the home cards show forecast, reminders, delays, and SLA.
