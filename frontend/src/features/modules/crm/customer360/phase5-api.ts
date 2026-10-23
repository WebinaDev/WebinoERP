/**
 * Customer 360 phase 5 — thin API layer (portal / BI reports / duplicates / import-export).
 * SKELETON: PHASE5_API.md is not written yet, so no endpoint paths or field
 * names are assumed. Fill `path` and flip `ready` per area when the contract lands.
 */
export type Area = { path: string | null; ready: boolean };
const pending = (): Area => ({ path: null, ready: false });

export const PORTAL_SECTIONS = ['profile', 'contracts', 'invoices', 'services', 'documents', 'tickets'] as const;
export const REPORT_SECTIONS = ['ltv', 'debt', 'churn', 'cohorts', 'top'] as const;
export type PortalSection = (typeof PORTAL_SECTIONS)[number];
export type ReportSection = (typeof REPORT_SECTIONS)[number];

export const P5_PORTAL: Record<PortalSection, Area> = Object.fromEntries(PORTAL_SECTIONS.map((s) => [s, pending()])) as Record<PortalSection, Area>;
export const P5_REPORTS: Record<ReportSection, Area> = Object.fromEntries(REPORT_SECTIONS.map((s) => [s, pending()])) as Record<ReportSection, Area>;
export const P5_DUPLICATES: Area = pending();
export const P5_EXPORT: Area = pending();
/** Existing, already-shipped endpoint (phase 1): POST /v1/crm/customers/{target}/hub/merge {source_id}. */
export const mergeUrl = (targetId: string | number) => `/v1/crm/customers/${targetId}/hub/merge`;
