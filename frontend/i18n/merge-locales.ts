/** Map module/menu ids to layout.* i18n keys used by sidebar labels. */
export function resolveLayoutNavKey(id: string): string {
  const map: Record<string, string> = {
    dashboard: 'dashboard',
    reports: 'reports',
    'cat-hrm': 'hrm',
    'cat-finance': 'finance',
    'cat-crm': 'crm',
    'cat-pm': 'pm',
    'cat-scm': 'scm',
    'cat-sales': 'sales',
    'cat-docs': 'docs',
    'cat-admin': 'admin',
  };
  return map[id] ?? id.replace(/-/g, '_');
}
