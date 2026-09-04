/**
 * Maps backend SystemModule slugs to frontend ERP_MODULES.id values.
 */
export const LICENSE_SLUG_TO_MODULE_ID: Record<string, string> = {
  dashboard: 'cat-dashboard',
  core: 'admin',
  crm: 'crm',
  hrm: 'hrm',
  accounting: 'finance',
  projects: 'pm',
  scm: 'scm',
  sales: 'sales',
  docs: 'docs',
  ai_content: 'ai_content',
  marketplace: 'distribution',
  integrations: 'admin',
  warehouse: 'scm',
  site_builder: 'platform',
  platform: 'platform',
};

export function mapLicensedModulesToNavIds(slugs: string[]): string[] {
  const ids = new Set<string>();
  for (const slug of slugs) {
    const mapped = LICENSE_SLUG_TO_MODULE_ID[slug] ?? slug;
    ids.add(mapped);
  }
  return Array.from(ids);
}
