/**
 * Parity with webinocrm `class-dashboard-router.php` + `webinocrm-dashboard/src/App.tsx`
 * (all dashboard URLs and accounting sub-routes).
 */

export type DashboardRouteMeta = {
  titleFa: string;
  titleEn: string;
  /** Logical area for breadcrumbs */
  group?: 'core' | 'projects' | 'crm' | 'accounting' | 'integrations' | 'hrm' | 'finance' | 'scm' | 'sales' | 'docs' | 'admin' | 'pm' | 'ai_content';
  /** Example REST prefix (see backend/docs/AJAX_TO_API_INVENTORY.md) */
  apiHint?: string;
};

const EXACT: Record<string, DashboardRouteMeta> = {
  '': { titleFa: 'داشبورد', titleEn: 'Dashboard', group: 'core', apiHint: '/v1/core/dashboard' },
  projects: { titleFa: 'پروژه‌ها', titleEn: 'Projects', group: 'projects', apiHint: '/v1/projects/projects' },
  contracts: { titleFa: 'قراردادها', titleEn: 'Contracts', group: 'projects', apiHint: '/v1/projects/contracts' },
  services: { titleFa: 'خدمات و محصولات', titleEn: 'Services & products', group: 'projects', apiHint: '/v1/projects/products' },
  invoices: { titleFa: 'پیش‌فاکتورها', titleEn: 'Pro forma invoices', group: 'projects', apiHint: '/v1/projects/invoices' },
  tickets: { titleFa: 'تیکت‌ها', titleEn: 'Tickets', group: 'projects', apiHint: '/v1/projects/tickets' },
  tasks: { titleFa: 'وظایف', titleEn: 'Tasks', group: 'projects', apiHint: '/v1/projects/tasks' },
  appointments: { titleFa: 'قرار ملاقات‌ها', titleEn: 'Appointments', group: 'projects', apiHint: '/v1/projects/appointments' },
  leads: { titleFa: 'سرنخ‌ها', titleEn: 'Leads', group: 'crm', apiHint: '/v1/crm/leads' },
  licenses: { titleFa: 'لایسنس‌ها', titleEn: 'Licenses', group: 'core', apiHint: '/v1/core/licenses' },
  'hosting-infra': {
    titleFa: 'میزبانی و زیرساخت',
    titleEn: 'Hosting & infra',
    group: 'core',
    apiHint: '/webinocrm/v1/hosting/settings',
  },
  customers: { titleFa: 'مشتریان', titleEn: 'Customers', group: 'crm', apiHint: '/v1/crm/accounts' },
  staff: { titleFa: 'کارکنان', titleEn: 'Staff', group: 'core', apiHint: '/v1/core/users' },
  consultations: { titleFa: 'مشاوره‌ها', titleEn: 'Consultations', group: 'crm', apiHint: '/v1/crm/consultations' },
  campaigns: { titleFa: 'کمپین‌ها', titleEn: 'Campaigns', group: 'crm', apiHint: '—' },
  reports: { titleFa: 'گزارشات', titleEn: 'Reports', group: 'core', apiHint: '/v1/core/reports' },
  logs: { titleFa: 'لاگ‌ها', titleEn: 'Logs', group: 'core', apiHint: '/v1/core/logs' },
  'visitor-statistics': { titleFa: 'آمار بازدید', titleEn: 'Visitor statistics', group: 'core', apiHint: '/v1/core/visitor-stats' },
  settings: { titleFa: 'تنظیمات', titleEn: 'Settings', group: 'core', apiHint: '/v1/core/settings' },
  'bale-business': {
    titleFa: 'ربات بله / کسب‌وکار',
    titleEn: 'Bale business',
    group: 'integrations',
    apiHint: '/webinocrm/v1/bale/settings, /webinocrm/v1/bale/stats, …',
  },
  profile: { titleFa: 'پروفایل من', titleEn: 'My profile', group: 'core', apiHint: '/v1/core/users/me' },
  accounting: {
    titleFa: 'حسابداری',
    titleEn: 'Accounting',
    group: 'accounting',
    apiHint:
      '/v1/accounting/summary, POST /v1/accounting/wp-action/{action}, POST /v1/accounting/warehouse-ajax/{action}, /webinocrm/v1/*',
  },
  'accounting/chart': { titleFa: 'نمودار حساب‌ها', titleEn: 'Chart of accounts', group: 'accounting', apiHint: '/v1/accounting/chart' },
  'accounting/journals': { titleFa: 'اسناد حسابداری', titleEn: 'Journal entries', group: 'accounting', apiHint: '/v1/accounting/journals' },
  'accounting/ledger': { titleFa: 'دفتر کل / معین', titleEn: 'Ledger', group: 'accounting', apiHint: '/v1/accounting/ledger' },
  'accounting/reports': { titleFa: 'گزارشات مالی', titleEn: 'Financial reports', group: 'accounting', apiHint: '/v1/accounting/reports' },
  'accounting/fiscal-year': { titleFa: 'سال مالی', titleEn: 'Fiscal year', group: 'accounting', apiHint: '/v1/accounting/fiscal-years' },
  'accounting/settings': { titleFa: 'تنظیمات حسابداری', titleEn: 'Accounting settings', group: 'accounting', apiHint: '/v1/accounting/settings' },
  'accounting/persons': { titleFa: 'اشخاص (طرف‌های حساب)', titleEn: 'Persons', group: 'accounting', apiHint: '/v1/accounting/persons' },
  'accounting/products': { titleFa: 'کالا و خدمات', titleEn: 'Products & services', group: 'accounting', apiHint: '/v1/accounting/products' },
  'accounting/invoices': { titleFa: 'فاکتورها (حسابداری)', titleEn: 'Accounting invoices', group: 'accounting', apiHint: '/v1/accounting/invoices' },
  'accounting/cash-accounts': { titleFa: 'حساب‌های بانک/صندوق', titleEn: 'Cash & bank', group: 'accounting', apiHint: '/v1/accounting/cash-accounts' },
  'accounting/receipts': { titleFa: 'رسید و پرداخت', titleEn: 'Receipts & payments', group: 'accounting', apiHint: '/v1/accounting/receipts' },
  'accounting/checks': { titleFa: 'چک‌ها', titleEn: 'Checks', group: 'accounting', apiHint: '/v1/accounting/checks' },
  'accounting/warehouses': { titleFa: 'انبارها', titleEn: 'Warehouses', group: 'accounting', apiHint: '/v1/accounting/warehouses' },
  'accounting/warehouse-stock': { titleFa: 'موجودی انبار', titleEn: 'Warehouse stock', group: 'accounting', apiHint: '/v1/accounting/warehouse-stock' },
  'accounting/warehouse-inbound': { titleFa: 'ورود کالا', titleEn: 'Inbound', group: 'accounting', apiHint: '/v1/accounting/warehouse-inbound' },
  'accounting/warehouse-outbound': { titleFa: 'خروج کالا', titleEn: 'Outbound', group: 'accounting', apiHint: '/v1/accounting/warehouse-outbound' },
  'accounting/warehouse-audit': { titleFa: 'انبارگردانی', titleEn: 'Stock audit', group: 'accounting', apiHint: '/v1/accounting/warehouse-audit' },
  finance: { titleFa: 'حسابداری', titleEn: 'Finance', group: 'finance', apiHint: '/v1/accounting/summary' },
  'finance/chart': { titleFa: 'نمودار حساب‌ها', titleEn: 'Chart of accounts', group: 'finance', apiHint: '/v1/accounting/chart' },
  'finance/journals': { titleFa: 'اسناد حسابداری', titleEn: 'Journal entries', group: 'finance', apiHint: '/v1/accounting/journals' },
  'finance/ledger': { titleFa: 'دفتر کل', titleEn: 'Ledger', group: 'finance', apiHint: '/v1/accounting/ledger' },
  'finance/reports': { titleFa: 'گزارشات مالی', titleEn: 'Financial reports', group: 'finance', apiHint: '/v1/accounting/reports' },
  'finance/fiscal-year': { titleFa: 'سال مالی', titleEn: 'Fiscal year', group: 'finance', apiHint: '/v1/accounting/fiscal-years' },
  'finance/settings': { titleFa: 'تنظیمات حسابداری', titleEn: 'Accounting settings', group: 'finance', apiHint: '/v1/accounting/settings' },
  'finance/persons': { titleFa: 'اشخاص', titleEn: 'Persons', group: 'finance', apiHint: '/v1/accounting/persons' },
  'finance/products': { titleFa: 'کالا و خدمات', titleEn: 'Products', group: 'finance', apiHint: '/v1/accounting/products' },
  'finance/invoices': { titleFa: 'فاکتورها', titleEn: 'Invoices', group: 'finance', apiHint: '/v1/accounting/invoices' },
  'finance/cash-accounts': { titleFa: 'حساب‌های بانک', titleEn: 'Cash accounts', group: 'finance', apiHint: '/v1/accounting/cash-accounts' },
  'finance/receipts': { titleFa: 'رسید و پرداخت', titleEn: 'Receipts', group: 'finance', apiHint: '/v1/accounting/receipts' },
  'finance/checks': { titleFa: 'چک‌ها', titleEn: 'Checks', group: 'finance', apiHint: '/v1/accounting/checks' },
  'hrm/staff': { titleFa: 'کارکنان', titleEn: 'Staff', group: 'hrm', apiHint: '/v1/hrm/employees' },
  'hrm/attendance': { titleFa: 'حضور و غیاب', titleEn: 'Attendance', group: 'hrm', apiHint: '/v1/hrm/attendance' },
  'hrm/leave': { titleFa: 'مرخصی', titleEn: 'Leave', group: 'hrm', apiHint: '/v1/hrm/leave' },
  'hrm/payroll': { titleFa: 'حقوق و دستمزد', titleEn: 'Payroll', group: 'hrm', apiHint: '/v1/hrm/payroll' },
  'hrm/payroll/decrees': { titleFa: 'احکام کارگزینی', titleEn: 'Employment decrees', group: 'hrm', apiHint: '/v1/hrm/payroll/decrees' },
  'hrm/my-payroll': { titleFa: 'فیش من', titleEn: 'My payslips', group: 'hrm', apiHint: '/v1/hrm/payroll/my-payslips' },
  'hrm/me': { titleFa: 'پورتال من', titleEn: 'My portal', group: 'hrm', apiHint: '/v1/hrm/me' },
  'hrm/my-time': { titleFa: 'زمان و حضور', titleEn: 'Time & attendance', group: 'hrm', apiHint: '/v1/hrm/me/attendance' },
  'hrm/my-docs': { titleFa: 'مدارک من', titleEn: 'My documents', group: 'hrm', apiHint: '/v1/hrm/me/decrees' },
  'hrm/my-insurance': { titleFa: 'بیمه من', titleEn: 'My insurance', group: 'hrm', apiHint: '/v1/hrm/me/dependents' },
  'hrm/my-org': { titleFa: 'سازمان', titleEn: 'Organization', group: 'hrm', apiHint: '/v1/hrm/me/org-chart' },
  'hrm/my-profile': { titleFa: 'پروفایل من', titleEn: 'My profile', group: 'hrm', apiHint: '/v1/hrm/me/profile' },
  'hrm/cartable': { titleFa: 'کارتابل HR', titleEn: 'HR inbox', group: 'hrm', apiHint: '/v1/hrm/requests/inbox' },
  'hrm/recruitment': { titleFa: 'استخدام', titleEn: 'Recruitment', group: 'hrm', apiHint: '/v1/hrm/recruitment' },
  'hrm/performance': { titleFa: 'ارزیابی عملکرد', titleEn: 'Performance', group: 'hrm', apiHint: '/v1/hrm/performance' },
  'hrm/training': { titleFa: 'آموزش', titleEn: 'Training', group: 'hrm', apiHint: '/v1/hrm/training' },
  'crm/leads': { titleFa: 'سرنخ‌ها', titleEn: 'Leads', group: 'crm', apiHint: '/v1/crm/leads' },
  'crm/customers': { titleFa: 'مشتریان', titleEn: 'Customers', group: 'crm', apiHint: '/v1/crm/accounts' },
  'crm/tickets': { titleFa: 'تیکت‌ها', titleEn: 'Tickets', group: 'crm', apiHint: '/v1/projects/tickets' },
  'crm/consultations': { titleFa: 'مشاوره‌ها', titleEn: 'Consultations', group: 'crm', apiHint: '/v1/crm/consultations' },
  'pm/projects': { titleFa: 'پروژه‌ها', titleEn: 'Projects', group: 'pm', apiHint: '/v1/projects/projects' },
  'pm/tasks': { titleFa: 'وظایف', titleEn: 'Tasks', group: 'pm', apiHint: '/v1/projects/tasks' },
  'pm/appointments': { titleFa: 'قرارها', titleEn: 'Appointments', group: 'pm', apiHint: '/v1/projects/appointments' },
  'pm/chat': { titleFa: 'گفتگو', titleEn: 'Chat', group: 'pm', apiHint: '/v1/projects/chat' },
  'pm/time-tracking': { titleFa: 'ثبت زمان', titleEn: 'Time tracking', group: 'pm', apiHint: '/v1/projects/time-entries' },
  'scm/warehouses': { titleFa: 'انبارها', titleEn: 'Warehouses', group: 'scm', apiHint: '/v1/scm/warehouses' },
  'scm/stock': { titleFa: 'موجودی', titleEn: 'Stock', group: 'scm', apiHint: '/v1/scm/stock' },
  'scm/inbound': { titleFa: 'ورود کالا', titleEn: 'Inbound', group: 'scm', apiHint: '/v1/scm/inbound' },
  'scm/outbound': { titleFa: 'خروج کالا', titleEn: 'Outbound', group: 'scm', apiHint: '/v1/scm/outbound' },
  'scm/audit': { titleFa: 'انبارگردانی', titleEn: 'Audit', group: 'scm', apiHint: '/v1/scm/audit' },
  'sales/invoices': { titleFa: 'فاکتور فروش', titleEn: 'Sales invoices', group: 'sales', apiHint: '/v1/sales/invoices' },
  'sales/catalog': { titleFa: 'کاتالوگ', titleEn: 'Catalog', group: 'sales', apiHint: '/v1/sales/catalog' },
  'sales/campaigns': { titleFa: 'کمپین‌ها', titleEn: 'Campaigns', group: 'sales', apiHint: '/v1/sales/campaigns' },
  'docs/contracts': { titleFa: 'قراردادها', titleEn: 'Contracts', group: 'docs', apiHint: '/v1/docs/contracts' },
  'docs/files': { titleFa: 'فایل‌ها', titleEn: 'Files', group: 'docs', apiHint: '/v1/docs/files' },
  'ai-content': { titleFa: 'هوش مصنوعی محتوا', titleEn: 'AI Content', group: 'ai_content', apiHint: '/v1/ai-content/overview' },
  'ai-content/jobs': { titleFa: 'کارهای AI', titleEn: 'AI jobs', group: 'ai_content', apiHint: '/v1/ai-content/jobs' },
  'ai-content/calendar': { titleFa: 'تقویم محتوا', titleEn: 'AI calendar', group: 'ai_content', apiHint: '/v1/ai-content/calendar' },
  'ai-content/products': { titleFa: 'محصولات AI', titleEn: 'AI products', group: 'ai_content', apiHint: '/v1/ai-content/products' },
  'ai-content/titles': { titleFa: 'عناوین AI', titleEn: 'AI titles', group: 'ai_content', apiHint: '/v1/ai-content/proposals/title' },
  'ai-content/pages': { titleFa: 'صفحات AI', titleEn: 'AI pages', group: 'ai_content', apiHint: '/v1/ai-content/pages' },
  'ai-content/taxonomies': { titleFa: 'دسته‌های AI', titleEn: 'AI taxonomies', group: 'ai_content', apiHint: '/v1/ai-content/suggest-categories' },
  'ai-content/attributes': { titleFa: 'ویژگی‌های AI', titleEn: 'AI attributes', group: 'ai_content', apiHint: '/v1/ai-content/attribute-templates' },
  'ai-content/settings': { titleFa: 'تنظیمات AI', titleEn: 'AI settings', group: 'ai_content', apiHint: '/v1/ai-content/settings' },
  'admin/settings': { titleFa: 'تنظیمات', titleEn: 'Settings', group: 'admin', apiHint: '/v1/core/settings' },
  'admin/logs': { titleFa: 'لاگ‌ها', titleEn: 'Logs', group: 'admin', apiHint: '/v1/core/logs' },
  'admin/licenses': { titleFa: 'لایسنس‌ها', titleEn: 'Licenses', group: 'admin', apiHint: '/v1/core/licenses' },
  'admin/analytics/visitors': { titleFa: 'آمار بازدید', titleEn: 'Visitors', group: 'admin', apiHint: '/v1/core/visitor-stats' },
  'admin/marketplace/products': { titleFa: 'محصولات بازارچه', titleEn: 'Marketplace products', group: 'admin', apiHint: '/v1/marketplace/products' },
  'admin/marketplace/categories': { titleFa: 'دسته‌بندی‌ها', titleEn: 'Categories', group: 'admin', apiHint: '/v1/marketplace/categories' },
  'admin/marketplace/orders': { titleFa: 'سفارش‌ها', titleEn: 'Orders', group: 'admin', apiHint: '/v1/marketplace/orders' },
  'admin/marketplace/gitea': { titleFa: 'Gitea', titleEn: 'Gitea', group: 'admin', apiHint: '/v1/marketplace/gitea' },
  'admin/integrations/bale': { titleFa: 'ربات بله', titleEn: 'Bale business', group: 'integrations', apiHint: '/webinocrm/v1/bale/*' },
  'admin/integrations/modirpayamak': { titleFa: 'مدیرپیامک', titleEn: 'ModirPayamak', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/admin/dashboard' },
  'admin/integrations/modirpayamak/send': { titleFa: 'ارسال پیامک', titleEn: 'Send SMS', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/send' },
  'admin/integrations/modirpayamak/reports': { titleFa: 'گزارش پیامک', titleEn: 'SMS reports', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/reports' },
  'admin/integrations/modirpayamak/customers': { titleFa: 'مشتریان مدیرپیامک', titleEn: 'ModirPayamak customers', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/customers' },
  'admin/integrations/modirpayamak/packages': { titleFa: 'بسته‌ها', titleEn: 'Packages', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/packages' },
  'admin/integrations/modirpayamak/tariffs': { titleFa: 'تعرفه‌ها', titleEn: 'Tariffs', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/admin/tariffs' },
  'admin/integrations/modirpayamak/orders': { titleFa: 'سفارش‌ها', titleEn: 'Orders', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/orders' },
  'admin/integrations/modirpayamak/patterns': { titleFa: 'الگوها', titleEn: 'Patterns', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/patterns' },
  'admin/integrations/modirpayamak/secretaries': { titleFa: 'منشی‌ها', titleEn: 'Secretaries', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/admin/secretaries' },
  'admin/integrations/modirpayamak/phonebooks': { titleFa: 'دفترچه تلفن', titleEn: 'Phonebooks', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/phonebooks' },
  'admin/integrations/modirpayamak/numbers': { titleFa: 'شماره‌ها', titleEn: 'Numbers', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/numbers' },
  'admin/integrations/modirpayamak/users': { titleFa: 'کاربران', titleEn: 'Users', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/admin/proxy' },
  'admin/integrations/modirpayamak/tickets': { titleFa: 'تیکت‌ها', titleEn: 'Tickets', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/admin/proxy' },
  'admin/integrations/modirpayamak/drafts': { titleFa: 'پیش‌نویس‌ها', titleEn: 'Drafts', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/admin/proxy' },
  'admin/integrations/modirpayamak/settings': { titleFa: 'تنظیمات مدیرپیامک', titleEn: 'ModirPayamak settings', group: 'integrations', apiHint: '/v1/integrations/modirpayamak/settings' },
  mfg: { titleFa: 'تولید', titleEn: 'Manufacturing', group: 'core', apiHint: '/v1/mfg/overview' },
  'mfg/boms': { titleFa: 'فهرست مواد', titleEn: 'BOMs', group: 'core', apiHint: '/v1/mfg/boms' },
  'mfg/work-orders': { titleFa: 'دستور کار', titleEn: 'Work orders', group: 'core', apiHint: '/v1/mfg/work-orders' },
  'mfg/quality': { titleFa: 'کیفیت', titleEn: 'Quality', group: 'core', apiHint: '/v1/mfg/inspections' },
  'mfg/planning': { titleFa: 'برنامه‌ریزی', titleEn: 'Planning', group: 'core', apiHint: '/v1/mfg/planning/mrp' },
  'admin/hosting-infra': {
    titleFa: 'میزبانی و زیرساخت',
    titleEn: 'Hosting & infra',
    group: 'admin',
    apiHint: '/webinocrm/v1/hosting/settings',
  },
  core: { titleFa: 'هسته سیستم', titleEn: 'Core', group: 'core' },
  crm: { titleFa: 'ارتباط با مشتری', titleEn: 'CRM', group: 'crm' },
  hrm: { titleFa: 'منابع انسانی', titleEn: 'HR', group: 'hrm' },
  pm: { titleFa: 'مدیریت پروژه', titleEn: 'Projects', group: 'pm' },
  scm: { titleFa: 'زنجیره تأمین', titleEn: 'Supply chain', group: 'scm' },
  sales: { titleFa: 'فروش', titleEn: 'Sales', group: 'sales' },
  docs: { titleFa: 'اسناد', titleEn: 'Documents', group: 'docs' },
  admin: { titleFa: 'سیستم و تنظیمات', titleEn: 'Administration', group: 'admin' },
  marketing: { titleFa: 'سایت عمومی', titleEn: 'Public site', group: 'core' },
  integrations: { titleFa: 'یکپارچگی‌ها', titleEn: 'Integrations', group: 'integrations' },
  'crm/deals': { titleFa: 'معاملات', titleEn: 'Deals', group: 'crm' },
  'crm/pipelines': { titleFa: 'قیف‌های فروش', titleEn: 'Pipelines', group: 'crm' },
  'admin/platform': { titleFa: 'پلتفرم', titleEn: 'Platform', group: 'admin' },
  'admin/platform/servers': { titleFa: 'سرورها', titleEn: 'Servers', group: 'admin' },
  'admin/platform/projects': { titleFa: 'پروژه‌های پلتفرم', titleEn: 'Platform projects', group: 'admin' },
  'admin/platform/resources': { titleFa: 'منابع', titleEn: 'Resources', group: 'admin' },
  'admin/platform/resources/new': { titleFa: 'منبع جدید', titleEn: 'New resource', group: 'admin' },
  'admin/platform/services': { titleFa: 'سرویس‌ها', titleEn: 'Services', group: 'admin' },
  'admin/platform/sources': { titleFa: 'منابع کد', titleEn: 'Sources', group: 'admin' },
  'admin/platform/storages': { titleFa: 'ذخیره‌سازی', titleEn: 'Storages', group: 'admin' },
  'admin/platform/backups': { titleFa: 'پشتیبان‌ها', titleEn: 'Backups', group: 'admin' },
  'admin/platform/keys': { titleFa: 'کلیدها', titleEn: 'Keys', group: 'admin' },
  'admin/platform/tokens': { titleFa: 'توکن‌ها', titleEn: 'Tokens', group: 'admin' },
  'admin/platform/variables': { titleFa: 'متغیرها', titleEn: 'Variables', group: 'admin' },
  'admin/platform/tags': { titleFa: 'برچسب‌ها', titleEn: 'Tags', group: 'admin' },
  'admin/platform/notifications': { titleFa: 'اعلان‌ها', titleEn: 'Notifications', group: 'admin' },
  'admin/platform/terminal': { titleFa: 'ترمینال', titleEn: 'Terminal', group: 'admin' },
  'admin/platform/settings': { titleFa: 'تنظیمات پلتفرم', titleEn: 'Platform settings', group: 'admin' },
  'admin/platform/catalog': { titleFa: 'کاتالوگ', titleEn: 'Catalog', group: 'admin' },
  'admin/platform/sites': { titleFa: 'سایت‌ها', titleEn: 'Sites', group: 'admin' },
  'admin/platform/sites/new': { titleFa: 'سایت جدید', titleEn: 'New site', group: 'admin' },
  'admin/site-builder': { titleFa: 'سازنده سایت', titleEn: 'Site builder', group: 'admin' },
  'admin/site-builder/catalog': { titleFa: 'کاتالوگ', titleEn: 'Catalog', group: 'admin' },
  'admin/site-builder/provisions': { titleFa: 'سایت‌ها', titleEn: 'Sites', group: 'admin' },
  'admin/site-builder/provisions/new': { titleFa: 'سایت جدید', titleEn: 'New site', group: 'admin' },
  'admin/marketplace': { titleFa: 'بازارچه', titleEn: 'Marketplace', group: 'admin' },
  'admin/marketplace/themes': { titleFa: 'پوسته‌ها', titleEn: 'Themes', group: 'admin' },
  'admin/integrations': { titleFa: 'یکپارچگی‌ها', titleEn: 'Integrations', group: 'integrations' },
  'admin/analytics': { titleFa: 'آمار', titleEn: 'Analytics', group: 'admin' },
  'marketing/pages': { titleFa: 'برگه‌ها', titleEn: 'Pages', group: 'core' },
  'marketing/magazine': { titleFa: 'مجله', titleEn: 'Magazine', group: 'core' },
  'marketing/media': { titleFa: 'رسانه', titleEn: 'Media', group: 'core' },
  'marketing/blog': { titleFa: 'بلاگ', titleEn: 'Blog', group: 'core' },
  'marketing/academy': { titleFa: 'آکادمی', titleEn: 'Academy', group: 'core' },
  'marketing/portfolio': { titleFa: 'نمونه کار', titleEn: 'Portfolio', group: 'core' },
  'marketing/faq': { titleFa: 'پرسش‌ها', titleEn: 'FAQ', group: 'core' },
  'marketing/services': { titleFa: 'خدمات', titleEn: 'Services', group: 'core' },
  'marketing/solutions': { titleFa: 'راه‌حل‌ها', titleEn: 'Solutions', group: 'core' },
  'marketing/team': { titleFa: 'تیم', titleEn: 'Team', group: 'core' },
  'marketing/announcements': { titleFa: 'اطلاعیه‌ها', titleEn: 'Announcements', group: 'core' },
  'marketing/testimonials': { titleFa: 'نظرات', titleEn: 'Testimonials', group: 'core' },
  'marketing/downloads': { titleFa: 'دانلودها', titleEn: 'Downloads', group: 'core' },
  documents: { titleFa: 'اسناد', titleEn: 'Documents', group: 'docs' },
};

const DETAIL_ROOTS: Record<string, DashboardRouteMeta> = {
  'pm/projects': { titleFa: 'پروژه', titleEn: 'Project', group: 'pm', apiHint: '/v1/projects/projects/{id}' },
  projects: { titleFa: 'پروژه', titleEn: 'Project', group: 'projects', apiHint: '/v1/projects/projects/{id}' },
  'docs/contracts': { titleFa: 'قرارداد', titleEn: 'Contract', group: 'docs', apiHint: '/v1/docs/contracts/{id}' },
  contracts: { titleFa: 'قرارداد', titleEn: 'Contract', group: 'projects', apiHint: '/v1/projects/contracts/{id}' },
  'hrm/staff': { titleFa: 'کارمند', titleEn: 'Staff user', group: 'hrm', apiHint: '/v1/hrm/employees/{id}' },
  staff: { titleFa: 'کارمند', titleEn: 'Staff user', group: 'core', apiHint: '/v1/hrm/employees/{id}' },
  'hrm/payroll': { titleFa: 'حقوق', titleEn: 'Payroll', group: 'hrm', apiHint: '/v1/hrm/payroll/{id}' },
  payroll: { titleFa: 'حقوق', titleEn: 'Payroll', group: 'hrm', apiHint: '/v1/hrm/payroll/{id}' },
  'crm/customers': { titleFa: 'مشتری', titleEn: 'Customer', group: 'crm', apiHint: '/v1/crm/accounts/{id}' },
  customers: { titleFa: 'مشتری', titleEn: 'Customer', group: 'crm', apiHint: '/v1/crm/accounts/{id}' },
  tasks: { titleFa: 'وظیفه', titleEn: 'Task', group: 'projects', apiHint: '/v1/projects/tasks' },
  tickets: { titleFa: 'تیکت', titleEn: 'Ticket', group: 'projects', apiHint: '/v1/projects/tickets/{id}' },
  invoices: { titleFa: 'پیش‌فاکتور', titleEn: 'Invoice', group: 'projects', apiHint: '/v1/projects/invoices/{id}' },
  appointments: { titleFa: 'قرار', titleEn: 'Appointment', group: 'projects', apiHint: '/v1/projects/appointments/{id}' },
  reports: { titleFa: 'گزارش', titleEn: 'Report', group: 'core', apiHint: '/v1/core/reports' },
  services: { titleFa: 'خدمت / محصول', titleEn: 'Service / product', group: 'projects', apiHint: '/v1/projects/products' },
  campaigns: { titleFa: 'کمپین', titleEn: 'Campaign', group: 'crm', apiHint: '—' },
  settings: { titleFa: 'تنظیمات', titleEn: 'Settings section', group: 'core', apiHint: '/v1/core/settings' },
};

export function resolveDashboardRoute(path: string): DashboardRouteMeta {
  const normalized = path.replace(/^\/+|\/+$/g, '').trim();
  if (normalized === '') {
    return EXACT['']!;
  }
  if (EXACT[normalized]) {
    return EXACT[normalized]!;
  }
  const parts = normalized.split('/').filter(Boolean);
  if (parts.length >= 2) {
    const maybeId = parts[parts.length - 1];
    if (/^\d+$/.test(maybeId ?? '')) {
      const root = parts.slice(0, -1).join('/');
      const short = parts[0]!;
      const base = DETAIL_ROOTS[root] ?? DETAIL_ROOTS[short];
      if (base) {
        return {
          ...base,
          titleFa: `${base.titleFa} (${maybeId})`,
          titleEn: `${base.titleEn} #${maybeId}`,
          apiHint: base.apiHint?.replace('{id}', String(maybeId)),
        };
      }
    }
  }
  const slug = parts[parts.length - 1] ?? normalized;
  const prefix = PREFIX_LABELS[slug] ?? PREFIX_LABELS[parts[0] ?? ''];
  if (prefix) {
    return { ...prefix, apiHint: '—' };
  }
  return {
    titleFa: PREFIX_LABELS[normalized]?.titleFa ?? humanizeSlug(normalized),
    titleEn: PREFIX_LABELS[normalized]?.titleEn ?? humanizeSlug(normalized),
    group: 'core',
    apiHint: '—',
  };
}

const PREFIX_LABELS: Record<string, DashboardRouteMeta> = {
  core: { titleFa: 'هسته سیستم', titleEn: 'Core', group: 'core' },
  crm: { titleFa: 'ارتباط با مشتری', titleEn: 'CRM', group: 'crm' },
  hrm: { titleFa: 'منابع انسانی', titleEn: 'HR', group: 'hrm' },
  pm: { titleFa: 'مدیریت پروژه', titleEn: 'Projects', group: 'pm' },
  scm: { titleFa: 'زنجیره تأمین', titleEn: 'Supply chain', group: 'scm' },
  sales: { titleFa: 'فروش', titleEn: 'Sales', group: 'sales' },
  docs: { titleFa: 'اسناد', titleEn: 'Documents', group: 'docs' },
  admin: { titleFa: 'سیستم و تنظیمات', titleEn: 'Administration', group: 'admin' },
  marketing: { titleFa: 'سایت عمومی', titleEn: 'Public site', group: 'core' },
  accounting: { titleFa: 'حسابداری', titleEn: 'Accounting', group: 'accounting' },
  finance: { titleFa: 'حسابداری', titleEn: 'Finance', group: 'finance' },
  integrations: { titleFa: 'یکپارچگی‌ها', titleEn: 'Integrations', group: 'integrations' },
  platform: { titleFa: 'پلتفرم', titleEn: 'Platform', group: 'admin' },
  marketplace: { titleFa: 'بازارچه', titleEn: 'Marketplace', group: 'admin' },
  'site-builder': { titleFa: 'سازنده سایت', titleEn: 'Site builder', group: 'admin' },
  mfg: { titleFa: 'تولید', titleEn: 'Manufacturing', group: 'core' },
};

function humanizeSlug(value: string): string {
  const last = value.split('/').filter(Boolean).pop() ?? value;
  return last.replace(/[-_]+/g, ' ').trim() || value;
}

export function listKnownPaths(): string[] {
  return Object.keys(EXACT);
}
