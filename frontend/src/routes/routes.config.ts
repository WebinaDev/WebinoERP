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
  { path: '/dashboard', headerTitleKey: 'nav.dashboard' },
  { path: '/dashboard/projects', headerTitleKey: 'nav.projects' },
  { path: '/dashboard/projects/:id', headerTitleKey: 'nav.projectDetail', headerParamKeys: { id: 'id' } },
  { path: '/dashboard/contracts', headerTitleKey: 'nav.contracts' },
  { path: '/dashboard/invoices', headerTitleKey: 'nav.invoices' },
  { path: '/dashboard/tickets', headerTitleKey: 'nav.tickets' },
  { path: '/dashboard/tasks', headerTitleKey: 'nav.tasks' },
  { path: '/dashboard/crm', headerTitleKey: 'nav.crm' },
  { path: '/dashboard/crm/leads', headerTitleKey: 'nav.leads' },
  { path: '/dashboard/crm/deals', headerTitleKey: 'nav.deals' },
  { path: '/dashboard/crm/contacts', headerTitleKey: 'nav.contacts' },
  { path: '/dashboard/crm/companies', headerTitleKey: 'nav.companies' },
  { path: '/dashboard/finance', headerTitleKey: 'nav.finance' },
  { path: '/dashboard/hrm', headerTitleKey: 'nav.hrm' },
  { path: '/dashboard/scm', headerTitleKey: 'nav.scm' },
  { path: '/dashboard/settings', headerTitleKey: 'nav.settings' },
  { path: '/dashboard/settings/general', headerTitleKey: 'nav.generalSettings' },
  { path: '/dashboard/settings/users', headerTitleKey: 'nav.users' },
  { path: '/dashboard/settings/roles', headerTitleKey: 'nav.roles' },
  { path: '/dashboard/marketing', headerTitleKey: 'nav.marketing' },
  { path: '/dashboard/platform', headerTitleKey: 'nav.platform' },
]
