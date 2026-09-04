'use client';

import type { ReactNode } from 'react';
import { normalizeDashboardPath } from '@/lib/route-resolver';
import { DashboardHomePage } from '@/components/dashboard/pages/DashboardHomePage';
import { LeadsListPage } from '@/components/dashboard/pages/LeadsListPage';
import { ProjectsListPage } from '@/components/dashboard/pages/ProjectsListPage';
import { TicketsListPage } from '@/components/dashboard/pages/TicketsListPage';
import { TasksKanbanPage } from '@/components/dashboard/pages/TasksKanbanPage';
import {
  AppointmentsListPage,
  ConsultationsListPage,
  ContractsListPage,
  CustomersListPage,
  InvoicesListPage,
  ServicesListPage,
} from '@/components/dashboard/pages/ModuleListPages';
import { EntityDetailPage } from '@/components/dashboard/pages/EntityDetailPage';
import { BaleBusinessDashboard } from '@/components/dashboard/BaleBusinessDashboard';
import {
  LicensesPageView,
  LogsPageView,
  VisitorStatsPageView,
} from '@/components/dashboard/pages/CoreStaticPages';
import { ProfilePage, ReportsPage } from '@/features/modules/core/core_pages';
import {
  SettingsAccountingPage,
  SettingsBotsPage,
  SettingsCrmPage,
  SettingsGeneralPage,
  SettingsProjectsPage,
} from '@/features/modules/admin/settings/hub_pages';
import { SettingsHubPage } from '@/components/dashboard/pages/SettingsHubPage';
import { HostingInfraPageView } from '@/components/dashboard/pages/HostingInfraPageView';
import { SiteBuilderCatalogPage } from '@/features/site-builder/SiteBuilderCatalogPage';
import { SiteProvisionsListPage } from '@/features/site-builder/SiteProvisionsListPage';
import { SiteProvisionWizardPage } from '@/features/site-builder/SiteProvisionWizardPage';
import { SiteControlPanelPage } from '@/features/site-builder/control-panel/SiteControlPanelPage';
import { PlatformDashboardPage } from '@/features/platform/PlatformDashboardPage';
import { ServersListPage } from '@/features/platform/servers/ServersListPage';
import { ServerDetailPage } from '@/features/platform/servers/ServerDetailPage';
import { ProjectsListPage as PlatformProjectsListPage } from '@/features/platform/projects/ProjectsListPage';
import { ProjectDetailPage as PlatformProjectDetailPage } from '@/features/platform/projects/ProjectDetailPage';
import { ResourcesListPage } from '@/features/platform/resources/ResourcesListPage';
import { AddResourceWizardPage } from '@/features/platform/resources/AddResourceWizardPage';
import { ResourceDetailPage } from '@/features/platform/resources/ResourceDetailPage';
import { ServicesCatalogPage } from '@/features/platform/services/ServicesCatalogPage';
import { ServiceDetailPage } from '@/features/platform/services/ServiceDetailPage';
import { SourcesPage } from '@/features/platform/SourcesPage';
import { StoragesPage } from '@/features/platform/StoragesPage';
import { BackupsPage } from '@/features/platform/BackupsPage';
import { KeysPage } from '@/features/platform/KeysPage';
import { TokensPage } from '@/features/platform/TokensPage';
import { VariablesPage } from '@/features/platform/VariablesPage';
import { TagsPage } from '@/features/platform/TagsPage';
import { NotificationsPage } from '@/features/platform/NotificationsPage';
import { TerminalPage } from '@/features/platform/TerminalPage';
import { SettingsPage as PlatformSettingsPage } from '@/features/platform/SettingsPage';
import { StaffPage } from '@/features/modules/hrm/staff_page';
import { StaffDetailPage } from '@/features/modules/hrm/staff_detail_page';
import { AttendancePage } from '@/features/modules/hrm/attendance_page';
import { LeavePage } from '@/features/modules/hrm/leave_page';
import { PayrollPage } from '@/features/modules/hrm/payroll_page';
import { PayrollRunDetailPage } from '@/features/modules/hrm/payroll_run_detail_page';
import { RecruitmentPage } from '@/features/modules/hrm/recruitment_page';
import { PerformancePage } from '@/features/modules/hrm/performance_page';
import { TrainingPage } from '@/features/modules/hrm/training_page';
import {
  HrmCartablePage,
  MyDocsPage,
  MyInsurancePage,
  MyOrgPage,
  MyPayrollPage,
  MyPortalPage,
  MyProfilePage,
  MyTimePage,
  PayrollDecreesPage,
} from '@/features/modules/hrm/portal';
import { AccountingDashboardPage } from '@/features/modules/finance/dashboard/AccountingDashboardPage';
import { PersonsPage } from '@/features/modules/finance/persons/PersonsPage';
import { FinanceProductsPage } from '@/features/modules/finance/products/ProductsPage';
import { FinanceInvoicesPage } from '@/features/modules/finance/invoices/InvoicesPage';
import { CashAccountsPage } from '@/features/modules/finance/cash-accounts/CashAccountsPage';
import { ReceiptsPage } from '@/features/modules/finance/receipts/ReceiptsPage';
import { ChecksPage } from '@/features/modules/finance/checks/ChecksPage';
import { ChartOfAccountsPage } from '@/features/modules/finance/chart/ChartOfAccountsPage';
import { JournalsPage } from '@/features/modules/finance/journals/JournalsPage';
import { LedgerPage } from '@/features/modules/finance/ledger/LedgerPage';
import { AccountingReportsPage } from '@/features/modules/finance/reports/AccountingReportsPage';
import { FiscalYearPage } from '@/features/modules/finance/fiscal-year/FiscalYearPage';
import { AccountingSettingsPage } from '@/features/modules/finance/settings/AccountingSettingsPage';
import { DealsKanbanPage } from '@/features/modules/crm/deals/DealsKanbanPage';
import { PipelinesPage } from '@/features/modules/crm/pipelines/PipelinesPage';
import { CustomerDetailPage } from '@/features/modules/crm/customers/CustomerDetailPage';
import { WarehousesPage } from '@/features/modules/scm/warehouses_page';
import { StockPage } from '@/features/modules/scm/stock_page';
import { InboundPage } from '@/features/modules/scm/inbound_page';
import { OutboundPage } from '@/features/modules/scm/outbound_page';
import { AuditPage } from '@/features/modules/scm/audit_page';
import { InvoicesPage as SalesInvoicesPage } from '@/features/modules/sales/invoices_page';
import { CatalogPage } from '@/features/modules/sales/catalog_page';
import { CampaignsPage } from '@/features/modules/sales/campaigns_page';
import { ContractsPage } from '@/features/modules/docs/contracts_page';
import { ContractDetailPage } from '@/features/modules/docs/contract_detail_page';
import { FilesPage } from '@/features/modules/docs/files_page';
import {
  AiOverviewPage,
  AiJobsPage,
  AiCalendarPage,
  AiProductsPage,
  AiTitlesPage,
  AiPagesPage,
  AiTaxonomiesPage,
  AiAttributesPage,
  AiSettingsPage,
} from '@/features/modules/admin/ai-content';
import { ProductsPage } from '@/features/modules/admin/marketplace/products_page';
import { CategoriesPage } from '@/features/modules/admin/marketplace/categories_page';
import { OrdersPage } from '@/features/modules/admin/marketplace/orders_page';
import { GiteaPage } from '@/features/modules/admin/marketplace/gitea_page';
import { SiteThemesPage } from '@/features/modules/admin/marketplace/themes_page';
import { ModuleDetailPage } from '@/features/modules/admin/marketplace/module_detail_page';
import {
  MfgOverviewPage,
  MfgBomsPage,
  MfgWorkOrdersPage,
  MfgQualityPage,
  MfgPlanningPage,
} from '@/features/modules/mfg';
import { ModirpayamakSendPage } from '@/features/modules/admin/integrations/modirpayamak/send_page';
import { ModirpayamakReportsPage } from '@/features/modules/admin/integrations/modirpayamak/reports_page';
import { ModirpayamakCustomersPage } from '@/features/modules/admin/integrations/modirpayamak/customers_page';
import { ModirpayamakPackagesPage } from '@/features/modules/admin/integrations/modirpayamak/packages_page';
import { ModirpayamakTariffsPage } from '@/features/modules/admin/integrations/modirpayamak/tariffs_page';
import { ModirpayamakOrdersPage } from '@/features/modules/admin/integrations/modirpayamak/orders_page';
import { ModirpayamakPatternsPage } from '@/features/modules/admin/integrations/modirpayamak/patterns_page';
import { ModirpayamakSecretariesPage } from '@/features/modules/admin/integrations/modirpayamak/secretaries_page';
import { ModirpayamakPhonebooksPage } from '@/features/modules/admin/integrations/modirpayamak/phonebooks_page';
import { ModirpayamakNumbersPage } from '@/features/modules/admin/integrations/modirpayamak/numbers_page';
import { ModirpayamakUsersPage } from '@/features/modules/admin/integrations/modirpayamak/users_page';
import { ModirpayamakTicketsPage } from '@/features/modules/admin/integrations/modirpayamak/tickets_page';
import { ModirpayamakDraftsPage } from '@/features/modules/admin/integrations/modirpayamak/drafts_page';
import { ModirpayamakSettingsPage } from '@/features/modules/admin/integrations/modirpayamak/settings_page';
import { ModirpayamakDashboardPage } from '@/features/modules/admin/integrations/modirpayamak/dashboard_page';
import { ChatPage } from '@/features/modules/pm/chat_page';
import { ProjectDetailPage } from '@/features/modules/pm/project_detail_page';
import { TimeTrackingPage } from '@/features/modules/pm/time_tracking_page';
import {
  MarketingAcademyPage,
  MarketingAnnouncementsPage,
  MarketingBlogPage,
  MarketingDownloadsPage,
  MarketingFaqPage,
  MarketingMagazinePage,
  MarketingMediaPage,
  MarketingPagesPage,
  MarketingPortfolioPage,
  MarketingServicesPage,
  MarketingSolutionsPage,
  MarketingTeamPage,
  MarketingTestimonialsPage,
} from '@/features/modules/marketing/marketing_pages';
import { UnknownRoutePage } from '@/features/shell/errors/UnknownRoutePage';

const FINANCE_PAGES: Record<string, ReactNode> = {
  finance: <AccountingDashboardPage />,
  'finance/persons': <PersonsPage />,
  'finance/products': <FinanceProductsPage />,
  'finance/invoices': <FinanceInvoicesPage />,
  'finance/cash-accounts': <CashAccountsPage />,
  'finance/receipts': <ReceiptsPage />,
  'finance/checks': <ChecksPage />,
  'finance/chart': <ChartOfAccountsPage />,
  'finance/journals': <JournalsPage />,
  'finance/ledger': <LedgerPage />,
  'finance/reports': <AccountingReportsPage />,
  'finance/fiscal-year': <FiscalYearPage />,
  'finance/settings': <AccountingSettingsPage />,
};

const EXACT: Record<string, ReactNode> = {
  '': <DashboardHomePage />,
  reports: <ReportsPage />,
  profile: <ProfilePage />,
  'crm/leads': <LeadsListPage />,
  leads: <LeadsListPage />,
  'crm/customers': <CustomersListPage />,
  customers: <CustomersListPage />,
  'crm/deals': <DealsKanbanPage />,
  'crm/pipelines': <PipelinesPage />,
  'crm/tickets': <TicketsListPage />,
  tickets: <TicketsListPage />,
  'crm/consultations': <ConsultationsListPage />,
  consultations: <ConsultationsListPage />,
  'pm/projects': <ProjectsListPage />,
  projects: <ProjectsListPage />,
  'pm/tasks': <TasksKanbanPage />,
  tasks: <TasksKanbanPage />,
  'pm/appointments': <AppointmentsListPage />,
  appointments: <AppointmentsListPage />,
  'pm/chat': <ChatPage />,
  'pm/time-tracking': <TimeTrackingPage />,
  'docs/contracts': <ContractsPage />,
  contracts: <ContractsListPage />,
  'docs/files': <FilesPage />,
  documents: <FilesPage />,
  'ai-content': <AiOverviewPage />,
  'ai-content/jobs': <AiJobsPage />,
  'ai-content/calendar': <AiCalendarPage />,
  'ai-content/products': <AiProductsPage />,
  'ai-content/titles': <AiTitlesPage />,
  'ai-content/pages': <AiPagesPage />,
  'ai-content/taxonomies': <AiTaxonomiesPage />,
  'ai-content/attributes': <AiAttributesPage />,
  'ai-content/settings': <AiSettingsPage />,
  'sales/invoices': <SalesInvoicesPage />,
  invoices: <InvoicesListPage />,
  'sales/catalog': <CatalogPage />,
  services: <ServicesListPage />,
  'sales/campaigns': <CampaignsPage />,
  campaigns: <CampaignsPage />,
  'hrm/staff': <StaffPage />,
  staff: <StaffPage />,
  'hrm/attendance': <AttendancePage />,
  'hrm/leave': <LeavePage />,
  'hrm/payroll': <PayrollPage />,
  'hrm/payroll/decrees': <PayrollDecreesPage />,
  'hrm/my-payroll': <MyPayrollPage />,
  'hrm/me': <MyPortalPage />,
  'hrm/my-time': <MyTimePage />,
  'hrm/my-docs': <MyDocsPage />,
  'hrm/my-insurance': <MyInsurancePage />,
  'hrm/my-org': <MyOrgPage />,
  'hrm/my-profile': <MyProfilePage />,
  'hrm/cartable': <HrmCartablePage />,
  'hrm/recruitment': <RecruitmentPage />,
  'hrm/performance': <PerformancePage />,
  'hrm/training': <TrainingPage />,
  'scm/warehouses': <WarehousesPage />,
  'scm/stock': <StockPage />,
  'scm/inbound': <InboundPage />,
  'scm/outbound': <OutboundPage />,
  'scm/audit': <AuditPage />,
  mfg: <MfgOverviewPage />,
  'mfg/boms': <MfgBomsPage />,
  'mfg/work-orders': <MfgWorkOrdersPage />,
  'mfg/quality': <MfgQualityPage />,
  'mfg/planning': <MfgPlanningPage />,
  'admin/licenses': <LicensesPageView />,
  licenses: <LicensesPageView />,
  'admin/logs': <LogsPageView />,
  logs: <LogsPageView />,
  'admin/analytics/visitors': <VisitorStatsPageView />,
  'visitor-statistics': <VisitorStatsPageView />,
  'admin/settings': <SettingsHubPage />,
  settings: <SettingsHubPage />,
  'admin/integrations/bale': <BaleBusinessDashboard />,
  'bale-business': <BaleBusinessDashboard />,
  'hosting-infra': <HostingInfraPageView />,
  'admin/hosting-infra': <HostingInfraPageView />,
  'admin/site-builder': <SiteProvisionsListPage />,
  'admin/site-builder/catalog': <SiteBuilderCatalogPage />,
  'admin/site-builder/provisions': <SiteProvisionsListPage />,
  'admin/site-builder/provisions/new': <SiteProvisionWizardPage />,
  'site-builder': <SiteProvisionsListPage />,
  'site-builder/catalog': <SiteBuilderCatalogPage />,
  'site-builder/provisions': <SiteProvisionsListPage />,
  'site-builder/provisions/new': <SiteProvisionWizardPage />,
  'admin/platform': <PlatformDashboardPage />,
  'admin/platform/catalog': <SiteBuilderCatalogPage />,
  'admin/platform/sites': <SiteProvisionsListPage />,
  'admin/platform/sites/new': <SiteProvisionWizardPage />,
  'admin/platform/servers': <ServersListPage />,
  'admin/platform/projects': <PlatformProjectsListPage />,
  'admin/platform/resources': <ResourcesListPage />,
  'admin/platform/resources/new': <AddResourceWizardPage />,
  'admin/platform/services': <ServicesCatalogPage />,
  'admin/platform/sources': <SourcesPage />,
  'admin/platform/storages': <StoragesPage />,
  'admin/platform/backups': <BackupsPage />,
  'admin/platform/keys': <KeysPage />,
  'admin/platform/tokens': <TokensPage />,
  'admin/platform/variables': <VariablesPage />,
  'admin/platform/tags': <TagsPage />,
  'admin/platform/notifications': <NotificationsPage />,
  'admin/platform/terminal': <TerminalPage />,
  'admin/platform/settings': <PlatformSettingsPage />,
  'admin/marketplace/products': <ProductsPage />,
  'admin/marketplace/themes': <SiteThemesPage />,
  'admin/marketplace/categories': <CategoriesPage />,
  'admin/marketplace/orders': <OrdersPage />,
  'admin/marketplace/gitea': <GiteaPage />,
  'admin/marketplace/modules/new': <ModuleDetailPage moduleId="new" />,
  'admin/integrations/modirpayamak': <ModirpayamakDashboardPage />,
  'admin/integrations/modirpayamak/send': <ModirpayamakSendPage />,
  'admin/integrations/modirpayamak/reports': <ModirpayamakReportsPage />,
  'admin/integrations/modirpayamak/customers': <ModirpayamakCustomersPage />,
  'admin/integrations/modirpayamak/packages': <ModirpayamakPackagesPage />,
  'admin/integrations/modirpayamak/tariffs': <ModirpayamakTariffsPage />,
  'admin/integrations/modirpayamak/orders': <ModirpayamakOrdersPage />,
  'admin/integrations/modirpayamak/patterns': <ModirpayamakPatternsPage />,
  'admin/integrations/modirpayamak/secretaries': <ModirpayamakSecretariesPage />,
  'admin/integrations/modirpayamak/phonebooks': <ModirpayamakPhonebooksPage />,
  'admin/integrations/modirpayamak/numbers': <ModirpayamakNumbersPage />,
  'admin/integrations/modirpayamak/users': <ModirpayamakUsersPage />,
  'admin/integrations/modirpayamak/tickets': <ModirpayamakTicketsPage />,
  'admin/integrations/modirpayamak/drafts': <ModirpayamakDraftsPage />,
  'admin/integrations/modirpayamak/settings': <ModirpayamakSettingsPage />,
  'marketing/pages': <MarketingPagesPage />,
  'marketing/magazine': <MarketingMagazinePage />,
  'marketing/media': <MarketingMediaPage />,
  'marketing/blog': <MarketingBlogPage />,
  'marketing/academy': <MarketingAcademyPage />,
  'marketing/portfolio': <MarketingPortfolioPage />,
  'marketing/faq': <MarketingFaqPage />,
  'marketing/services': <MarketingServicesPage />,
  'marketing/solutions': <MarketingSolutionsPage />,
  'marketing/team': <MarketingTeamPage />,
  'marketing/announcements': <MarketingAnnouncementsPage />,
  'marketing/testimonials': <MarketingTestimonialsPage />,
  'marketing/downloads': <MarketingDownloadsPage />,
};

const DETAIL_ROOTS = new Set(['pm/projects', 'projects']);

function resolveFinancePage(normalized: string): ReactNode | null {
  if (FINANCE_PAGES[normalized]) return FINANCE_PAGES[normalized];
  if (normalized.startsWith('accounting')) {
    const seg = normalized.replace(/^accounting\/?/, '');
    const key = seg ? `finance/${seg}` : 'finance';
    return FINANCE_PAGES[key] ?? FINANCE_PAGES.finance ?? null;
  }
  return null;
}

function resolvePlatformPage(normalized: string): ReactNode | null {
  if (normalized.startsWith('admin/platform/services/')) {
    const slug = normalized.replace('admin/platform/services/', '');
    if (slug && slug !== 'services') return <ServiceDetailPage slug={slug} />;
  }
  if (normalized.startsWith('admin/platform/servers/')) {
    const id = normalized.replace('admin/platform/servers/', '');
    if (id && id !== 'servers') return <ServerDetailPage id={id} />;
  }
  if (normalized.startsWith('admin/platform/projects/')) {
    const id = normalized.replace('admin/platform/projects/', '');
    if (id && id !== 'projects') return <PlatformProjectDetailPage id={id} />;
  }
  if (normalized.startsWith('admin/platform/resources/')) {
    const tail = normalized.replace('admin/platform/resources/', '');
    if (tail && tail !== 'resources' && tail !== 'new') return <ResourceDetailPage id={tail} />;
  }
  if (normalized.startsWith('admin/platform/sites/')) {
    const id = normalized.replace('admin/platform/sites/', '');
    if (id && id !== 'sites' && id !== 'new') return <SiteControlPanelPage id={id} />;
  }
  return null;
}

function resolveDetailPage(normalized: string): ReactNode | null {
  const parts = normalized.split('/').filter(Boolean);
  if (parts.length < 2) return null;
  const id = parts[parts.length - 1]!;
  const root = parts.slice(0, -1).join('/');

  if (root === 'hrm/staff' || root === 'staff') return <StaffDetailPage id={id} />;
  if ((root === 'hrm/payroll' || root === 'payroll') && /^\d+$/.test(id)) {
    return <PayrollRunDetailPage id={id} />;
  }
  if (root === 'crm/customers' || root === 'customers') return <CustomerDetailPage id={id} />;
  if (root === 'docs/contracts' || root === 'contracts') return <ContractDetailPage id={id} />;
  if (root === 'pm/projects' || root === 'projects') return <ProjectDetailPage id={id} />;

  if (/^\d+$/.test(id) && (DETAIL_ROOTS.has(root) || DETAIL_ROOTS.has(parts[0]!))) {
    return <EntityDetailPage root={DETAIL_ROOTS.has(root) ? root : parts[0]!} id={id} />;
  }

  return null;
}

function resolveSettingsPage(normalized: string): ReactNode | null {
  const match = normalized.match(/^(?:admin\/)?settings\/([^/]+)(?:\/([^/]+))?$/);
  if (!match) return null;
  const hub = match[1]!;
  const tab = match[2];
  if (hub === 'general') return <SettingsGeneralPage tab={tab} />;
  if (hub === 'projects') return <SettingsProjectsPage tab={tab} />;
  if (hub === 'crm') return <SettingsCrmPage tab={tab} />;
  if (hub === 'bots') return <SettingsBotsPage tab={tab} />;
  if (hub === 'accounting') return <SettingsAccountingPage tab={tab} />;
  return null;
}

export function resolveDashboardPage(path: string): ReactNode {
  const normalized = normalizeDashboardPath(path);

  const financePage = resolveFinancePage(normalized);
  if (financePage) return financePage;

  const settingsPage = resolveSettingsPage(normalized);
  if (settingsPage) return settingsPage;

  if (normalized === 'admin/settings' || normalized === 'settings') {
    return <SettingsHubPage />;
  }

  if (normalized.startsWith('admin/marketplace/modules/')) {
    const moduleId = normalized.replace('admin/marketplace/modules/', '');
    return <ModuleDetailPage moduleId={moduleId} />;
  }

  const platformPage = resolvePlatformPage(normalized);
  if (platformPage) return platformPage;

  if (EXACT[normalized]) return EXACT[normalized];

  const detailPage = resolveDetailPage(normalized);
  if (detailPage) return detailPage;

  return <UnknownRoutePage path={normalized} />;
}
