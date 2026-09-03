/**
 * Dashboard route definitions used for dynamic header titles.
 * Each entry maps a URL pattern to an i18n key for the page title.
 */

export interface DashboardRouteDef {
  /** URL path pattern, may contain :paramName segments */
  path: string
  /** i18n key for the header title (optional) */
  headerTitleKey?: string
  /** Map of URL params to i18n interpolation keys */
  headerParamKeys?: Record<string, string>
}

export const dashboardRoutes: DashboardRouteDef[] = [
  { path: '/' },
  { path: '/admin', headerTitleKey: 'nav.dashboard' },
  { path: '/admin/projects', headerTitleKey: 'nav.projects' },
  { path: '/admin/projects/:id', headerTitleKey: 'nav.projectDetail', headerParamKeys: { id: 'id' } },
  { path: '/admin/contracts', headerTitleKey: 'nav.contracts' },
  { path: '/admin/invoices', headerTitleKey: 'nav.invoices' },
  { path: '/admin/tickets', headerTitleKey: 'nav.tickets' },
  { path: '/admin/tasks', headerTitleKey: 'nav.tasks' },
  { path: '/admin/crm', headerTitleKey: 'nav.crm' },
  { path: '/admin/crm/leads', headerTitleKey: 'nav.leads' },
  { path: '/admin/crm/deals', headerTitleKey: 'nav.deals' },
  { path: '/admin/crm/contacts', headerTitleKey: 'nav.contacts' },
  { path: '/admin/crm/companies', headerTitleKey: 'nav.companies' },
  { path: '/admin/finance', headerTitleKey: 'nav.finance' },
  { path: '/admin/hrm', headerTitleKey: 'nav.hrm' },
  { path: '/admin/scm', headerTitleKey: 'nav.scm' },
  { path: '/admin/settings', headerTitleKey: 'nav.settings' },
  { path: '/admin/settings/general', headerTitleKey: 'nav.generalSettings' },
  { path: '/admin/settings/users', headerTitleKey: 'nav.users' },
  { path: '/admin/settings/roles', headerTitleKey: 'nav.roles' },
  { path: '/admin/marketing', headerTitleKey: 'nav.marketing' },
  { path: '/admin/platform', headerTitleKey: 'nav.platform' },
]
