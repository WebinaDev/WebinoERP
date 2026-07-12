/** Manual route inventory with layer statuses (verified 2026-07-08, phase 11 sync) */
export const ROUTE_INVENTORY = [
  // Shell
  { module: 'shell', route: '', legacy: [], menuId: 'dashboard', component: 'DashboardHomePage', api: '/v1/core/dashboard', db: '—', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '✅', notes: 'Role widgets partial; full webinocrm parity → phase 7' },
  { module: 'shell', route: 'login', legacy: [], menuId: '—', component: 'LoginForm', api: '/v1/core/auth/*', db: '—', fe: '✅', apiSt: '✅', i18n: '✅', tests: '🟡', notes: 'login-04; AuthApiTest + phase5-auth E2E' },
  { module: 'shell', route: 'reports', legacy: [], menuId: 'reports', component: 'ReportsPageView', api: '/v1/core/reports', db: '—', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'CoreStaticPages → phase 7' },
  { module: 'shell', route: 'profile', legacy: [], menuId: 'profile', component: 'ProfilePageView', api: '/v1/core/users/me', db: '—', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Not in ERP_MODULES nav → phase 7' },

  // HRM
  { module: 'hrm', route: 'hrm/staff', legacy: ['staff'], menuId: 'staff', component: 'StaffPage', api: '/v1/hrm/employees', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '✅', notes: 'Dedicated list; HrmApiTest' },
  { module: 'hrm', route: 'hrm/staff/:id', legacy: ['staff/:id'], menuId: 'staff', component: 'StaffDetailPage', api: '/v1/hrm/employees/{id}', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Detail exists; full parity → phase 7' },
  { module: 'hrm', route: 'hrm/attendance', legacy: [], menuId: 'hrm-attendance', component: 'AttendancePage', api: '/v1/hrm/attendance', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page; polish → phase 7' },
  { module: 'hrm', route: 'hrm/leave', legacy: [], menuId: 'hrm-leave', component: 'LeavePage', api: '/v1/hrm/leave', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },
  { module: 'hrm', route: 'hrm/payroll', legacy: [], menuId: 'hrm-payroll', component: 'PayrollPage', api: '/v1/hrm/payroll', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },
  { module: 'hrm', route: 'hrm/payroll/:id', legacy: [], menuId: 'hrm-payroll', component: 'PayrollRunDetailPage', api: '/v1/hrm/payroll/runs/{id}', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Detail exists; full parity → phase 7' },
  { module: 'hrm', route: 'hrm/recruitment', legacy: [], menuId: 'hrm-recruitment', component: 'RecruitmentPage', api: '/v1/hrm/recruitment', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },
  { module: 'hrm', route: 'hrm/performance', legacy: [], menuId: 'hrm-performance', component: 'PerformancePage', api: '/v1/hrm/performance', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },
  { module: 'hrm', route: 'hrm/training', legacy: [], menuId: 'hrm-training', component: 'TrainingPage', api: '/v1/hrm/training', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },

  // Finance — features/modules/finance/*
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
    component, api, db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡',
    notes: 'features/modules/finance/*; AccountingPageLayout → phase 7; AccountingApiTest (journals)',
  })),

  // CRM
  { module: 'crm', route: 'crm/leads', legacy: ['leads'], menuId: 'leads', component: 'LeadsListPage', api: '/v1/crm/leads', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'LeadController real CRUD' },
  { module: 'crm', route: 'crm/customers', legacy: ['customers'], menuId: 'customers', component: 'CustomersListPage + Customer360', api: '/v1/crm/accounts', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Hardcoded FA → phase 7' },
  { module: 'crm', route: 'crm/customers/:id', legacy: [], menuId: 'customers', component: 'EntityDetailPage', api: '/v1/crm/accounts/{id}', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Dedicated CustomerDetail → phase 7' },
  { module: 'crm', route: 'crm/tickets', legacy: ['tickets', 'tickets/*'], menuId: 'tickets', component: 'TicketsListPage', api: '/v1/projects/tickets', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Projects parity tickets' },
  { module: 'crm', route: 'crm/consultations', legacy: ['consultations'], menuId: 'consultations', component: 'ConsultationsListPage', api: '/v1/crm/consultations', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'CrmParityController' },
  { module: 'crm', route: 'crm/deals', legacy: [], menuId: 'crm-deals', component: 'DealsKanbanPage', api: '/v1/crm/deals', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '✅', notes: 'DealsKanbanPage; CrmDealsApiTest' },
  { module: 'crm', route: 'crm/pipelines', legacy: [], menuId: 'crm-pipelines', component: 'PipelinesPage', api: '/v1/crm/pipelines', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'PipelinesPage wired' },

  // PM
  { module: 'pm', route: 'pm/projects', legacy: ['projects', 'projects/*'], menuId: 'projects', component: 'ProjectsListPage', api: '/v1/projects/projects', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '✅', notes: 'ProjectsApiTest' },
  { module: 'pm', route: 'pm/projects/:id', legacy: ['projects/:id'], menuId: 'projects', component: 'EntityDetailPage', api: '/v1/projects/projects/{id}/details', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Dedicated ProjectDetail → phase 7' },
  { module: 'pm', route: 'pm/tasks', legacy: ['tasks', 'tasks/*'], menuId: 'tasks', component: 'TasksKanbanPage', api: '/v1/projects/tasks', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Kanban + list + calendar + gantt tabs' },
  { module: 'pm', route: 'pm/chat', legacy: ['chat'], menuId: 'chat', component: 'ChatPage', api: '/v1/core/chat/*', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Reverb chat UX → phase 7' },
  { module: 'pm', route: 'pm/time-tracking', legacy: ['time-tracking'], menuId: 'time-tracking', component: 'TimeTrackingPage', api: '/v1/projects/time-entries', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'TimeTrackingController real' },
  { module: 'pm', route: 'pm/appointments', legacy: ['appointments', 'appointments/*'], menuId: 'appointments', component: 'AppointmentsListPage', api: '/v1/projects/appointments', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Parity controller; polish → phase 7' },

  // SCM
  ...['scm/warehouses', 'scm/stock', 'scm/inbound', 'scm/outbound', 'scm/audit'].map((r, i) => ({
    module: 'scm', route: r,
    legacy: [['accounting/warehouses', 'accounting/warehouse-stock', 'accounting/warehouse-inbound', 'accounting/warehouse-outbound', 'accounting/warehouse-audit'][i]],
    menuId: ['accounting-warehouses', 'accounting-warehouse-stock', 'accounting-warehouse-inbound', 'accounting-warehouse-outbound', 'accounting-warehouse-audit'][i],
    component: ['WarehousesPage', 'StockPage', 'InboundPage', 'OutboundPage', 'AuditPage'][i],
    api: `/v1/scm/${r.split('/')[1]}`, db: '🟡', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡',
    notes: 'WarehouseService bridge; own migrations → phase 8; ScmApiTest (inbound)',
  })),

  // Sales
  { module: 'sales', route: 'sales/invoices', legacy: ['invoices', 'invoices/*'], menuId: 'invoices', component: 'SalesInvoicesPage', api: '/v1/sales/invoices', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '✅', notes: 'SalesApiTest' },
  { module: 'sales', route: 'sales/catalog', legacy: ['services', 'services/*'], menuId: 'services', component: 'CatalogPage', api: '/v1/sales/catalog', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },
  { module: 'sales', route: 'sales/campaigns', legacy: ['campaigns', 'campaigns/*'], menuId: 'campaigns', component: 'CampaignsPage', api: '/v1/sales/campaigns', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },

  // Sales submodule: ModirPayamak
  { module: 'sales/modirpayamak', route: 'admin/integrations/modirpayamak', legacy: ['modirpayamak', 'modirpayamak/*'], menuId: 'modirpayamak', component: 'ModirpayamakPage', api: '/v1/integrations/modirpayamak/admin/dashboard', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '✅', notes: 'Edge client hub; ModirPayamakApiTest' },
  ...['send', 'reports', 'customers', 'packages', 'orders', 'patterns', 'phonebooks', 'numbers', 'settings'].map((s) => ({
    module: 'sales/modirpayamak', route: `admin/integrations/modirpayamak/${s}`, legacy: [`modirpayamak/${s}`], menuId: `modirpayamak-${s}`,
    component: `Modirpayamak${s[0].toUpperCase()}${s.slice(1)}Page`, api: `/v1/integrations/modirpayamak/${s === 'customers' ? 'admin/customers' : s}`,
    db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'ModirPayamak Edge API wired (phase 5)',
  })),
  ...['users', 'tickets', 'drafts'].map((s) => ({
    module: 'sales/modirpayamak', route: `admin/integrations/modirpayamak/${s}`, legacy: [`modirpayamak/${s}`], menuId: `modirpayamak-${s}`,
    component: `Modirpayamak${s[0].toUpperCase()}${s.slice(1)}Page`, api: `/v1/integrations/modirpayamak/${s}`,
    db: '—', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'ModirPayamak proxy UI; phase 9',
  })),

  // Sales submodule: Bale
  { module: 'sales/bale', route: 'admin/integrations/bale', legacy: ['bale-business', 'bots/business'], menuId: 'bale-business', component: 'BaleBusinessDashboard', api: '/v1/integrations/bale/*', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '✅', notes: 'Partial Bale; complete → phase 9' },

  // Docs
  { module: 'docs', route: 'docs/contracts', legacy: ['contracts', 'contracts/*'], menuId: 'contracts', component: 'ContractsPage', api: '/v1/docs/contracts', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '✅', notes: 'DocsApiTest' },
  { module: 'docs', route: 'docs/contracts/:id', legacy: [], menuId: 'contracts', component: 'EntityDetailPage', api: '/v1/docs/contracts/{id}', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'ContractDetail dedicated → phase 7' },
  { module: 'docs', route: 'docs/files', legacy: ['documents'], menuId: 'documents', component: 'FilesPage', api: '/v1/docs/files', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },

  // Distribution (Marketplace)
  { module: 'distribution', route: 'admin/marketplace/products', legacy: ['marketplace/products'], menuId: 'marketplace-products', component: 'ProductsPage', api: '/v1/marketplace/products', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },
  { module: 'distribution', route: 'admin/marketplace/categories', legacy: ['marketplace/categories'], menuId: 'marketplace-categories', component: 'CategoriesPage', api: '/v1/marketplace/categories', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Dedicated page' },
  { module: 'distribution', route: 'admin/marketplace/orders', legacy: ['marketplace/orders'], menuId: 'marketplace-orders', component: 'OrdersPage', api: '/v1/marketplace/orders', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Orders index only; CRUD → phase 8' },
  { module: 'distribution', route: 'admin/marketplace/gitea', legacy: ['marketplace/gitea'], menuId: 'marketplace-gitea', component: 'GiteaPage', api: '/v1/marketplace/gitea', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Settings GET/PUT' },
  { module: 'distribution', route: 'admin/marketplace/modules/new', legacy: ['marketplace/modules/new'], menuId: 'marketplace-products', component: 'ModuleDetailPage (new)', api: '/v1/marketplace/modules', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '✅', notes: 'Module editor; MarketplaceApiTest' },
  { module: 'distribution', route: 'admin/marketplace/modules/:id', legacy: ['marketplace/modules/:id'], menuId: 'marketplace-products', component: 'ModuleDetailPage', api: '/v1/marketplace/modules/{id}', db: '✅', fe: '🟡', apiSt: '✅', i18n: '🟡', tests: '🟡', notes: 'Module editor wired' },
  { module: 'distribution', route: 'admin/licenses', legacy: ['licenses'], menuId: 'licenses', component: 'LicensesPageView', api: '/v1/core/licenses', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '✅', notes: 'CoreRbacApiTest' },

  // Admin
  { module: 'admin', route: 'admin/logs', legacy: ['logs'], menuId: 'logs', component: 'LogsPageView', api: '/v1/core/logs', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'CoreStaticPages' },
  { module: 'admin', route: 'admin/analytics/visitors', legacy: ['visitor-statistics'], menuId: 'visitor-statistics', component: 'VisitorStatsPageView', api: '/v1/core/visitor-stats', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Real aggregation → phase 8' },
  { module: 'admin', route: 'admin/settings', legacy: ['settings'], menuId: 'settings', component: 'SettingsHubPage', api: '/v1/core/settings', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '✅', notes: 'Card hub; dedicated tabs → phase 7; CoreRbacApiTest' },
  { module: 'admin', route: 'admin/settings/general/:tab?', legacy: ['settings/general/:tab?'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Dedicated SettingsGeneralPage → phase 7' },
  { module: 'admin', route: 'admin/settings/projects/:tab?', legacy: ['settings/projects/:tab?'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Dedicated tab page → phase 7' },
  { module: 'admin', route: 'admin/settings/crm/:tab?', legacy: ['settings/crm/:tab?'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Dedicated tab page → phase 7' },
  { module: 'admin', route: 'admin/settings/bots', legacy: ['settings/bots'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Dedicated tab page → phase 7' },
  { module: 'admin', route: 'admin/settings/accounting/:tab?', legacy: ['settings/accounting/:tab?'], menuId: 'settings', component: 'SettingsHubPage → SettingsPageView', api: '/v1/core/settings', db: '✅', fe: '🟡', apiSt: '🟡', i18n: '🟡', tests: '🟡', notes: 'Dedicated tab page → phase 7' },

  // MFG
  { module: 'mfg', route: 'mfg', legacy: ['mfg/'], menuId: 'mfg-overview', component: 'MfgOverviewPage', api: '/v1/mfg/overview', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'defaultEnabled:false; MfgApiTest' },
  { module: 'mfg', route: 'mfg/boms', legacy: [], menuId: 'mfg-boms', component: 'MfgBomsPage', api: '/v1/mfg/boms', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
  { module: 'mfg', route: 'mfg/work-orders', legacy: [], menuId: 'mfg-work-orders', component: 'MfgWorkOrdersPage', api: '/v1/mfg/work-orders', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
  { module: 'mfg', route: 'mfg/quality', legacy: [], menuId: 'mfg-quality', component: 'MfgQualityPage', api: '/v1/mfg/inspections', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },
  { module: 'mfg', route: 'mfg/planning', legacy: [], menuId: 'mfg-planning', component: 'MfgPlanningPage', api: '/v1/mfg/planning/mrp', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: '' },

  // Admin hosting
  { module: 'admin', route: 'admin/hosting-infra', legacy: ['hosting-infra'], menuId: 'hosting-infra', component: 'HostingInfraPageView', api: '/webinocrm/v1/hosting/*', db: '✅', fe: '✅', apiSt: '✅', i18n: '✅', tests: '✅', notes: 'system_manager guard; HostingApiTest' },
];

export const ALLOWLIST_MISSING = new Set([]);
