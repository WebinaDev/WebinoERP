/** Mirrors backend Modules\Core\Services\DashboardAccess role matrix. */

export type DashboardRole =
  | 'system_manager'
  | 'finance_manager'
  | 'hr_manager'
  | 'project_manager'
  | 'crm_specialist'
  | 'sales_consultant'
  | 'team_member'
  | 'client'
  | 'guest';

const SUITE_WIDGET_ROLES: Record<string, DashboardRole[]> = {
  forecast: ['system_manager', 'finance_manager', 'crm_specialist', 'sales_consultant'],
  reminders: ['system_manager', 'crm_specialist', 'sales_consultant', 'project_manager', 'team_member'],
  win_loss: ['system_manager', 'finance_manager', 'crm_specialist', 'sales_consultant'],
  delays: ['system_manager', 'project_manager', 'team_member', 'crm_specialist'],
  sla: ['system_manager', 'project_manager', 'crm_specialist', 'team_member'],
  workload: ['system_manager', 'project_manager', 'hr_manager', 'team_member'],
};

const LIST_WIDGET_ROLES: Record<string, DashboardRole[]> = {
  recent_leads: ['system_manager', 'crm_specialist', 'sales_consultant'],
  recent_deals: ['system_manager', 'finance_manager', 'crm_specialist', 'sales_consultant'],
  recent_tasks: ['system_manager', 'project_manager', 'team_member', 'crm_specialist', 'hr_manager'],
  recent_tickets: [
    'system_manager',
    'project_manager',
    'crm_specialist',
    'team_member',
    'client',
    'sales_consultant',
  ],
  recent_projects: ['system_manager', 'project_manager', 'team_member', 'client', 'finance_manager'],
  recent_invoices: ['system_manager', 'finance_manager', 'sales_consultant'],
  recent_sites: ['system_manager', 'project_manager'],
};

const SECTION_ROLES: Record<string, DashboardRole[]> = {
  crm_kpis: ['system_manager', 'crm_specialist', 'sales_consultant', 'finance_manager'],
  pm_kpis: ['system_manager', 'project_manager', 'team_member', 'crm_specialist'],
  funnel: ['system_manager', 'crm_specialist', 'sales_consultant', 'finance_manager'],
  trend: ['system_manager', 'finance_manager', 'crm_specialist', 'project_manager', 'sales_consultant'],
  finance: ['system_manager', 'finance_manager'],
  tickets: ['system_manager', 'project_manager', 'crm_specialist', 'team_member', 'sales_consultant'],
  sites: ['system_manager', 'project_manager'],
  marketing: ['system_manager', 'crm_specialist', 'sales_consultant'],
  suite: [
    'system_manager',
    'finance_manager',
    'crm_specialist',
    'sales_consultant',
    'project_manager',
    'team_member',
    'hr_manager',
  ],
  todos: [
    'system_manager',
    'project_manager',
    'team_member',
    'crm_specialist',
    'sales_consultant',
    'hr_manager',
  ],
  notifications: [
    'system_manager',
    'finance_manager',
    'hr_manager',
    'project_manager',
    'crm_specialist',
    'sales_consultant',
    'team_member',
  ],
  team_tab: ['system_manager', 'project_manager', 'hr_manager', 'finance_manager', 'crm_specialist'],
  full_overview: [
    'system_manager',
    'finance_manager',
    'crm_specialist',
    'project_manager',
    'sales_consultant',
    'hr_manager',
  ],
  widget_settings: [
    'system_manager',
    'finance_manager',
    'crm_specialist',
    'project_manager',
    'sales_consultant',
    'hr_manager',
    'team_member',
  ],
};

function allowed(role: string, roles: DashboardRole[]): boolean {
  if (role === 'system_manager') return true;
  return roles.includes(role as DashboardRole);
}

export function canSuiteWidget(role: string, key: string): boolean {
  return allowed(role, SUITE_WIDGET_ROLES[key] ?? []);
}

export function canListWidget(role: string, key: string): boolean {
  return allowed(role, LIST_WIDGET_ROLES[key] ?? []);
}

export function canDashboardSection(role: string, key: string): boolean {
  return allowed(role, SECTION_ROLES[key] ?? []);
}

export function suiteWidgetKeysForRole(role: string): string[] {
  return Object.keys(SUITE_WIDGET_ROLES).filter((k) => canSuiteWidget(role, k));
}

export const SUITE_WIDGET_CATALOG = Object.keys(SUITE_WIDGET_ROLES);
export const LIST_WIDGET_CATALOG = Object.keys(LIST_WIDGET_ROLES);
