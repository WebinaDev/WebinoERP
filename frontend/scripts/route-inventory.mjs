/** Manual route inventory with layer statuses (verified 2026-09-04, CRM↔ERP parity phases 0–10) */
export const ROUTE_INVENTORY = [
  // Shell
  { module: 'shell', route: '', legacy: [], menuId: 'dashboard', component: 'DashboardHomePage', api: '/v1/core/dashboard', db: '—', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'Role widgets + quick links (phase 7)' },
  { module: 'shell', route: 'login', legacy: [], menuId: '—', component: 'LoginForm', api: '/v1/core/auth/*', db: '—', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '2FA + AuthApiTest' },
  { module: 'shell', route: 'reports', legacy: [], menuId: 'reports', component: 'ReportsPage', api: '/v1/core/reports', db: '—', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '8 CRM tabs + CSV/JSON export (phase 7)' },
  { module: 'shell', route: 'profile', legacy: [], menuId: 'profile', component: 'ProfilePageView', api: '/v1/core/users/me', db: '—', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },

  // HRM admin
  { module: 'hrm', route: 'hrm/staff', legacy: ['staff'], menuId: 'staff', component: 'StaffPage', api: '/v1/hrm/employees', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'CRUD dialogs (phase 5)' },
  { module: 'hrm', route: 'hrm/staff/:id', legacy: ['staff/:id'], menuId: 'staff', component: 'StaffDetailPage', api: '/v1/hrm/employees/{id}', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'profile/dependents/assets/shifts tabs' },
  { module: 'hrm', route: 'hrm/attendance', legacy: [], menuId: 'hrm-attendance', component: 'AttendancePage', api: '/v1/hrm/attendance', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'hrm', route: 'hrm/leave', legacy: [], menuId: 'hrm-leave', component: 'LeavePage', api: '/v1/hrm/leave', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'my/pending/types tabs (phase 5)' },
  { module: 'hrm', route: 'hrm/payroll', legacy: [], menuId: 'hrm-payroll', component: 'PayrollPage', api: '/v1/hrm/payroll', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'periods/settings/components' },
  { module: 'hrm', route: 'hrm/payroll/:id', legacy: [], menuId: 'hrm-payroll', component: 'PayrollRunDetailPage', api: '/v1/hrm/payroll/runs/{id}', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'hrm', route: 'hrm/payroll/decrees', legacy: [], menuId: 'hrm-payroll-decrees', component: 'PayrollDecreesPage', api: '/v1/hrm/payroll/decrees', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'phase 6' },
  { module: 'hrm', route: 'hrm/recruitment', legacy: [], menuId: 'hrm-recruitment', component: 'RecruitmentPage', api: '/v1/hrm/recruitment', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'pipeline + hire (phase 5)' },
  { module: 'hrm', route: 'hrm/performance', legacy: [], menuId: 'hrm-performance', component: 'PerformancePage', api: '/v1/hrm/performance', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'KPI/periods/reviews' },
  { module: 'hrm', route: 'hrm/training', legacy: [], menuId: 'hrm-training', component: 'TrainingPage', api: '/v1/hrm/training', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'courses/sessions/enroll' },

  // HRM employee portal (phase 6)
  { module: 'hrm', route: 'hrm/me', legacy: [], menuId: 'hrm-me', component: 'MyPortalPage', api: '/v1/hrm/me', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'MePortalController' },
  { module: 'hrm', route: 'hrm/my-time', legacy: [], menuId: 'hrm-my-time', component: 'MyTimePage', api: '/v1/hrm/me/attendance', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'hrm', route: 'hrm/my-docs', legacy: [], menuId: 'hrm-my-docs', component: 'MyDocsPage', api: '/v1/hrm/me/decrees', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'hrm', route: 'hrm/my-insurance', legacy: [], menuId: 'hrm-my-insurance', component: 'MyInsurancePage', api: '/v1/hrm/me/dependents', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'hrm', route: 'hrm/my-org', legacy: [], menuId: 'hrm-my-org', component: 'MyOrgPage', api: '/v1/hrm/me/org-chart', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'hrm', route: 'hrm/my-profile', legacy: [], menuId: 'hrm-my-profile', component: 'MyProfilePage', api: '/v1/hrm/me/profile', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'hrm', route: 'hrm/my-payroll', legacy: [], menuId: 'hrm-my-payroll', component: 'MyPayrollPage', api: '/v1/hrm/payroll/my-payslips', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'hrm', route: 'hrm/cartable', legacy: [], menuId: 'hrm-cartable', component: 'HrmCartablePage', api: '/v1/hrm/requests/inbox', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },

  // Finance
  ...[
    ['finance', 'AccountingDashboardPage', '/v1/accounting/summary'],
    ['finance/persons', 'PersonsPage', '/v1/accounting/persons'],
    ['finance/products', 'FinanceProductsPage', '/v1/accounting/products'],
    ['finance/invoices', 'FinanceInvoicesPage', '/v1/accounting/invoices'],
    ['finance/cash-accounts', 'CashAccountsPage', '/v1/accounting/cash-accounts'],
    ['finance/receipts', 'ReceiptsPage', '/v1/accounting/receipts'],
    ['finance/checks', 'ChecksPage', '/v1/accounting/checks'],
    ['finance/chart', 'ChartOfAccountsPage', '/v1/accounting/chart'],
    ['finance/journals', 'JournalsPage', '/v1/accounting/journals'],
    ['finance/ledger', 'LedgerPage', '/v1/accounting/ledger'],
    ['finance/reports', 'AccountingReportsPage', '/v1/accounting/reports'],
    ['finance/fiscal-year', 'FiscalYearPage', '/v1/accounting/fiscal-years'],
    ['finance/settings', 'AccountingSettingsPage', '/v1/accounting/settings'],
  ].map(([r, component, api]) => ({
    module: 'finance', route: r, legacy: r === 'finance' ? ['accounting'] : [`accounting/${r.split('/')[1]}`], menuId: 'accounting',
    component, api, db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡',
    notes: r === 'finance/journals' ? 'create dialog debit/credit (phase 4)' : r === 'finance/products' ? 'price lists/categories/units tabs (phase 4)' : 'AccountingPageLayout',
  })),

  // CRM (phase 1)
  { module: 'crm', route: 'crm/leads', legacy: ['leads'], menuId: 'leads', component: 'LeadsListPage', api: '/v1/crm/leads', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'table/card + CSV + status dialog' },
  { module: 'crm', route: 'crm/customers', legacy: ['customers'], menuId: 'customers', component: 'CustomersListPage + Customer360', api: '/v1/crm/accounts', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'CSV export; tickets via /projects/tickets' },
  { module: 'crm', route: 'crm/customers/:id', legacy: [], menuId: 'customers', component: 'CustomerDetailPage', api: '/v1/crm/accounts/{id}', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '360 detail; tickets path fixed phase 0' },
  { module: 'crm', route: 'crm/tickets', legacy: ['tickets', 'tickets/*'], menuId: 'tickets', component: 'TicketsListPage', api: '/v1/projects/tickets', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'query ticket_id/action=new + canned' },
  { module: 'crm', route: 'crm/consultations', legacy: ['consultations'], menuId: 'consultations', component: 'ConsultationsListPage', api: '/v1/crm/consultations', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'AccountSelect' },
  { module: 'crm', route: 'crm/deals', legacy: [], menuId: 'crm-deals', component: 'DealsKanbanPage', api: '/v1/crm/deals', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
  { module: 'crm', route: 'crm/pipelines', legacy: [], menuId: 'crm-pipelines', component: 'PipelinesPage', api: '/v1/crm/pipelines', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },

  // PM (phase 2)
  { module: 'pm', route: 'pm/projects', legacy: ['projects', 'projects/*'], menuId: 'projects', component: 'ProjectsListPage', api: '/v1/projects/projects', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '4-step wizard + AccountSelect' },
  { module: 'pm', route: 'pm/projects/:id', legacy: ['projects/:id'], menuId: 'projects', component: 'EntityDetailPage', api: '/v1/projects/projects/{id}/details', db: '✅', fe: '🟡', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'pm', route: 'pm/tasks', legacy: ['tasks', 'tasks/*'], menuId: 'tasks', component: 'TasksKanbanPage', api: '/v1/projects/tasks', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'list/kanban/calendar/gantt + detail sheet' },
  { module: 'pm', route: 'pm/chat', legacy: ['chat'], menuId: 'chat', component: 'ChatPage', api: '/v1/core/chat/*', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'create channel' },
  { module: 'pm', route: 'pm/time-tracking', legacy: ['time-tracking'], menuId: 'time-tracking', component: 'TimeTrackingPage', api: '/v1/projects/time-entries', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'timer + manual /manual endpoint' },
  { module: 'pm', route: 'pm/appointments', legacy: ['appointments', 'appointments/*'], menuId: 'appointments', component: 'AppointmentsListPage', api: '/v1/projects/appointments', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'calendar + drag reschedule' },

  // SCM (phase 4)
  ...['scm/warehouses', 'scm/stock', 'scm/inbound', 'scm/outbound', 'scm/audit'].map((r, i) => ({
    module: 'scm', route: r,
    legacy: [['accounting/warehouses', 'accounting/warehouse-stock', 'accounting/warehouse-inbound', 'accounting/warehouse-outbound', 'accounting/warehouse-audit'][i]],
    menuId: ['accounting-warehouses', 'accounting-warehouse-stock', 'accounting-warehouse-inbound', 'accounting-warehouse-outbound', 'accounting-warehouse-audit'][i],
    component: ['WarehousesPage', 'StockPage', 'InboundPage', 'OutboundPage', 'AuditPage'][i],
    api: `/v1/scm/${r.split('/')[1]}`, db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡',
    notes: 'line items + stock table + audit register→complete→post',
  })),

  // Sales (phase 3)
  { module: 'sales', route: 'sales/invoices', legacy: ['invoices', 'invoices/*'], menuId: 'invoices', component: 'SalesInvoicesPage', api: '/v1/sales/invoices', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'edit/email/PDF' },
  { module: 'sales', route: 'sales/catalog', legacy: ['services', 'services/*'], menuId: 'services', component: 'CatalogPage', api: '/v1/sales/catalog', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'subscription/product tabs + convert' },
  { module: 'sales', route: 'sales/campaigns', legacy: ['campaigns', 'campaigns/*'], menuId: 'campaigns', component: 'CampaignsPage', api: '/v1/sales/campaigns', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'full form + delete' },

  // ModirPayamak (phase 8)
  { module: 'sales/modirpayamak', route: 'admin/integrations/modirpayamak', legacy: ['modirpayamak', 'modirpayamak/*'], menuId: 'modirpayamak', component: 'ModirpayamakDashboardPage', api: '/v1/integrations/modirpayamak/admin/dashboard', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'dashboard root (phase 8)' },
  ...['send', 'reports', 'customers', 'packages', 'orders', 'patterns', 'phonebooks', 'numbers', 'settings', 'tariffs', 'secretaries', 'users', 'tickets', 'drafts'].map((s) => ({
    module: 'sales/modirpayamak', route: `admin/integrations/modirpayamak/${s}`, legacy: [`modirpayamak/${s}`], menuId: `modirpayamak-${s}`,
    component: `Modirpayamak${s[0].toUpperCase()}${s.slice(1)}Page`,
    api: s === 'tariffs' || s === 'secretaries' || s === 'customers'
      ? `/v1/integrations/modirpayamak/admin/${s === 'customers' ? 'customers' : s}`
      : `/v1/integrations/modirpayamak/${s}`,
    db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡',
    notes: s === 'tariffs' || s === 'secretaries' ? 'phase 8 CRUD' : 'ModirPayamak Edge/proxy',
  })),

  // Bale (phase 8)
  { module: 'sales/bale', route: 'admin/integrations/bale', legacy: ['bale-business', 'bots/business'], menuId: 'bale-business', component: 'BaleBusinessDashboard', api: '/webinocrm/v1/bale/*', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'settings/webhook/logs/funnel tabs' },

  // Docs (phase 3)
  { module: 'docs', route: 'docs/contracts', legacy: ['contracts', 'contracts/*'], menuId: 'contracts', component: 'ContractsPage', api: '/v1/docs/contracts', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '4-step wizard + from_lead' },
  { module: 'docs', route: 'docs/contracts/:id', legacy: [], menuId: 'contracts', component: 'EntityDetailPage', api: '/v1/docs/contracts/{id}', db: '✅', fe: '🟡', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'docs', route: 'docs/files', legacy: ['documents'], menuId: 'documents', component: 'FilesPage', api: '/v1/docs/files', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'folders/versions/share/delete' },

  // AI Content (phase 9)
  { module: 'ai_content', route: 'ai-content', legacy: [], menuId: 'ai-content', component: 'AiOverviewPage', api: '/v1/ai-content/overview', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'AiContent module' },
  { module: 'ai_content', route: 'ai-content/jobs', legacy: [], menuId: 'ai-content-jobs', component: 'AiJobsPage', api: '/v1/ai-content/jobs', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'ai_content', route: 'ai-content/calendar', legacy: [], menuId: 'ai-content-calendar', component: 'AiCalendarPage', api: '/v1/ai-content/calendar', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'ai_content', route: 'ai-content/products', legacy: [], menuId: 'ai-content-products', component: 'AiProductsPage', api: '/v1/ai-content/products', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'ai_content', route: 'ai-content/titles', legacy: [], menuId: 'ai-content-titles', component: 'AiTitlesPage', api: '/v1/ai-content/proposals/title', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'ai_content', route: 'ai-content/pages', legacy: [], menuId: 'ai-content-pages', component: 'AiPagesPage', api: '/v1/ai-content/pages', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'ai_content', route: 'ai-content/taxonomies', legacy: [], menuId: 'ai-content-taxonomies', component: 'AiTaxonomiesPage', api: '/v1/ai-content/suggest-categories', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'ai_content', route: 'ai-content/attributes', legacy: [], menuId: 'ai-content-attributes', component: 'AiAttributesPage', api: '/v1/ai-content/attribute-templates', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'ai_content', route: 'ai-content/settings', legacy: [], menuId: 'ai-content-settings', component: 'AiSettingsPage', api: '/v1/ai-content/settings', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },

  // Distribution
  { module: 'distribution', route: 'admin/marketplace/products', legacy: ['marketplace/products'], menuId: 'marketplace-products', component: 'ProductsPage', api: '/v1/marketplace/products', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'distribution', route: 'admin/marketplace/categories', legacy: ['marketplace/categories'], menuId: 'marketplace-categories', component: 'CategoriesPage', api: '/v1/marketplace/categories', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'distribution', route: 'admin/marketplace/orders', legacy: ['marketplace/orders'], menuId: 'marketplace-orders', component: 'OrdersPage', api: '/v1/marketplace/orders', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'distribution', route: 'admin/marketplace/gitea', legacy: ['marketplace/gitea'], menuId: 'marketplace-gitea', component: 'GiteaPage', api: '/v1/marketplace/gitea', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'distribution', route: 'admin/marketplace/modules/new', legacy: ['marketplace/modules/new'], menuId: 'marketplace-products', component: 'ModuleDetailPage (new)', api: '/v1/marketplace/modules', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
  { module: 'distribution', route: 'admin/marketplace/modules/:id', legacy: ['marketplace/modules/:id'], menuId: 'marketplace-products', component: 'ModuleDetailPage', api: '/v1/marketplace/modules/{id}', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'distribution', route: 'admin/licenses', legacy: ['licenses'], menuId: 'licenses', component: 'LicensesPageView', api: '/v1/core/licenses', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },

  // Admin settings (phase 7)
  { module: 'admin', route: 'admin/logs', legacy: ['logs'], menuId: 'logs', component: 'LogsPageView', api: '/v1/core/logs', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'admin', route: 'admin/analytics/visitors', legacy: ['visitor-statistics'], menuId: 'visitor-statistics', component: 'VisitorStatsPageView', api: '/v1/core/visitor-stats', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'admin', route: 'admin/settings', legacy: ['settings'], menuId: 'settings', component: 'SettingsHubPage', api: '/v1/core/settings', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'CRM parity tabs (phase 7)' },
  { module: 'admin', route: 'admin/settings/general/:tab?', legacy: ['settings/general/:tab?'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'auth/style/visitor/sms/…' },
  { module: 'admin', route: 'admin/settings/projects/:tab?', legacy: ['settings/projects/:tab?'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'admin', route: 'admin/settings/crm/:tab?', legacy: ['settings/crm/:tab?'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'forms/leads/workflow/automations' },
  { module: 'admin', route: 'admin/settings/bots', legacy: ['settings/bots'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },
  { module: 'admin', route: 'admin/settings/accounting/:tab?', legacy: ['settings/accounting/:tab?'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: '' },

  // MFG (ERP-only, out of CRM parity scope)
  { module: 'mfg', route: 'mfg', legacy: ['mfg/'], menuId: 'mfg-overview', component: 'MfgOverviewPage', api: '/v1/mfg/overview', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'ERP-only' },
  { module: 'mfg', route: 'mfg/boms', legacy: [], menuId: 'mfg-boms', component: 'MfgBomsPage', api: '/v1/mfg/boms', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
  { module: 'mfg', route: 'mfg/work-orders', legacy: [], menuId: 'mfg-work-orders', component: 'MfgWorkOrdersPage', api: '/v1/mfg/work-orders', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
  { module: 'mfg', route: 'mfg/quality', legacy: [], menuId: 'mfg-quality', component: 'MfgQualityPage', api: '/v1/mfg/inspections', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
  { module: 'mfg', route: 'mfg/planning', legacy: [], menuId: 'mfg-planning', component: 'MfgPlanningPage', api: '/v1/mfg/planning/mrp', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },

  { module: 'admin', route: 'admin/hosting-infra', legacy: ['hosting-infra'], menuId: 'hosting-infra', component: 'HostingInfraPageView', api: '/webinocrm/v1/hosting/*', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
];

export const ALLOWLIST_MISSING = new Set([]);
