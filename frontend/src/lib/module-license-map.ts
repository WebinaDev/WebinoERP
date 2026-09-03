/**
 * Maps backend SystemModule slugs to frontend ERP_MODULES.id values.
 */
export const LICENSE_SLUG_TO_MODULE_ID: Record<string, string> = {
  dashboard: 'cat-dashboard',
  crm: 'crm',
  hrm: 'hrm',
  accounting: 'finance',
  projects: 'pm',
  scm: 'scm',
  sales: 'sales',
  docs: 'docs',
  marketplace: 'distribution',
  integrations: 'admin',
  warehouse: 'scm',
};

export function mapLicensedModulesToNavIds(slugs: string[]): string[] {
  const ids = new Set<string>();
  for (const slug of slugs) {
    const mapped = LICENSE_SLUG_TO_MODULE_ID[slug] ?? slug;
    ids.add(mapped);
  }
  return Array.from(ids);
}
