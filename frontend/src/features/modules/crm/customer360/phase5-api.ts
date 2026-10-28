/**
 * Customer 360 phase 5 — API layer (duplicates / import-export / customer portal / BI reports).
 * Contract: docs/customer360/PHASE5_API.md (backend a176012). Only fields listed there are typed here.
 */
import apiClient from '@/lib/api-client';
import type { PageMeta } from '../customers/hub/hub-api';

export type Area = { path: string | null; ready: boolean };
const area = (path: string): Area => ({ path, ready: true });

type Money = number | null;
type ISO = string | null;

const CUST = '/v1/crm/customers';
const PORTAL = '/v1/projects/portal';

export const PORTAL_SECTIONS = ['profile', 'contracts', 'invoices', 'services', 'documents', 'tickets'] as const;
export const REPORT_SECTIONS = ['ltv', 'debt', 'churn', 'cohorts', 'top', 'segments'] as const;
export type PortalSection = (typeof PORTAL_SECTIONS)[number];
export type ReportSection = (typeof REPORT_SECTIONS)[number];

export const P5_PORTAL: Record<PortalSection, Area> = {
  profile: area(`${PORTAL}/profile`), contracts: area(`${PORTAL}/contracts`), invoices: area(`${PORTAL}/invoices`),
  services: area(`${PORTAL}/services`), documents: area(`${PORTAL}/documents`), tickets: area(`${PORTAL}/summary`),
};
export const P5_REPORTS: Record<ReportSection, Area> = Object.fromEntries(REPORT_SECTIONS.map((s) => [s, area(`${CUST}/reports/${s}`)])) as Record<ReportSection, Area>;
export const P5_DUPLICATES: Area = area(`${CUST}/duplicates`);
export const P5_EXPORT: Area = area(`${CUST}/export`);
/** Existing, already-shipped endpoint (phase 1): POST /v1/crm/customers/{target}/hub/merge {source_id}. */
export const mergeUrl = (targetId: string | number) => `${CUST}/${targetId}/hub/merge`;

// ---------------- Duplicates ----------------
export type DupBrief = { id: number; name: string; mobile: string | null; email: string | null; national_id: string | null; website: string | null; created_at: ISO; invoices_count: number };
export type DupReason = 'national_id' | 'mobile' | 'email' | 'domain' | 'name';
export type DupRow = { key: string; score: number; reasons: DupReason[]; a: DupBrief; b: DupBrief; suggested_target_id: number };
export type DupRes = { data: DupRow[]; meta: PageMeta & { can: { merge: boolean } } };
export const getDuplicates = async (params: { min_score?: number; page?: number; per_page?: number } = {}) =>
  (await apiClient.get(P5_DUPLICATES.path!, { params })).data as DupRes;
export const getCustomerDuplicates = async (id: string | number, params: { min_score?: number } = {}) =>
  (await apiClient.get(`${CUST}/${id}/hub/duplicates`, { params })).data as DupRes;
export const dismissDuplicate = (a_id: number, b_id: number) => apiClient.post(`${CUST}/duplicates/dismiss`, { a_id, b_id });
export const mergeCustomers = (targetId: number, sourceId: number) => apiClient.post(mergeUrl(targetId), { source_id: sourceId });

// ---------------- Import / export ----------------
export const IO_ENTITIES = ['customers', 'addresses', 'contacts'] as const;
export type IoEntity = (typeof IO_ENTITIES)[number];
export type IoFormat = 'csv' | 'xlsx';
export type ImportResult = { total: number; created: number; updated: number; skipped: number; errors: { row: number; field: string; message: string }[] };
export const IMPORT_MAX_BYTES = 5 * 1024 * 1024;

const saveBlob = (data: BlobPart, name: string, type: string) => {
  const url = URL.createObjectURL(new Blob([data], { type }));
  const a = document.createElement('a');
  a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
};
const mime = (f: IoFormat) => (f === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv;charset=utf-8');
export async function exportCustomers(entity: IoEntity, format: IoFormat, opts: { ids?: number[]; q?: string } = {}) {
  const params: Record<string, unknown> = { entity, format };
  if (opts.ids?.length) params['ids[]'] = opts.ids;
  if (opts.q) params.q = opts.q;
  const r = await apiClient.get(P5_EXPORT.path!, { params, responseType: 'blob' });
  saveBlob(r.data as BlobPart, `customers-${entity}.${format}`, mime(format));
}
export async function downloadImportTemplate(entity: IoEntity, format: IoFormat) {
  const r = await apiClient.get(`${CUST}/import/template`, { params: { entity, format }, responseType: 'blob' });
  saveBlob(r.data as BlobPart, `customers-${entity}-template.${format}`, mime(format));
}
export async function importCustomers(file: File, entity: IoEntity, mode: 'create' | 'upsert', dryRun: boolean) {
  const fd = new FormData();
  fd.append('file', file); fd.append('entity', entity); fd.append('mode', mode); fd.append('dry_run', dryRun ? '1' : '0');
  return ((await apiClient.post(`${CUST}/import`, fd)).data as { data: ImportResult }).data;
}

// ---------------- Portal ----------------
export type PortalAccount = { id: number; name: string; brand_name: string | null; national_id: string | null; economic_code: string | null; mobile: string | null; email: string | null; website: string | null; address_line: string | null; city: string | null; province: string | null; postal_code: string | null; hub_status: string | null };
export type PortalProfile = { account: PortalAccount; accounts: { id: number; name: string; is_primary: boolean }[] };
export type PortalContract = { id: number; title: string; status: string; amount: Money; starts_at: ISO; ends_at: ISO; signed_at: ISO; can_sign: boolean; document_id: number | null };
export type PortalContractDetail = PortalContract & { body: string | null; installments: { id: number; amount: Money; due_date: ISO; paid_at: ISO }[] };
export type PortalInvoice = { id: number; number: string; status: string; issue_date: ISO; due_date: ISO; total: Money; remaining: Money; payable: boolean };
export type PortalService = { id: number; kind: string; name: string; plan: string | null; status: string; site_domain: string | null; renews_at: ISO };
export type PortalDomain = { domain: string; expires_at: ISO; auto_renew: boolean };
export type PortalDocument = { id: number; title: string; category: string | null; expires_at: ISO; created_at: ISO; download_url: string };
export type OtpSent = { sent_to: string; expires_in: number; resend_in: number };
export type PayStart = { payment_id: string; redirect_url: string; amount: Money; gateway: string };
export type PaymentStatus = { payment_id: string; status: string; paid: boolean; amount: Money; invoice_id: number | null; settled_at: ISO };

const acct = (accountId?: number | null) => (accountId ? { account_id: accountId } : {});
const pget = async <T>(path: string, accountId?: number | null, params: Record<string, unknown> = {}) =>
  (await apiClient.get(`${PORTAL}${path}`, { params: { ...params, ...acct(accountId) } })).data as T;
const ppost = async <T>(path: string, body: unknown, accountId?: number | null) =>
  ((await apiClient.post(`${PORTAL}${path}`, body, { params: acct(accountId) })).data as { data: T }).data;

export const portal = {
  profile: (a?: number | null) => pget<{ data: PortalProfile }>('/profile', a).then((r) => r.data),
  summary: (a?: number | null) => pget<{ data?: Record<string, unknown> } & Record<string, unknown>>('/summary', a),
  contracts: (a?: number | null) => pget<{ data: PortalContract[] }>('/contracts', a).then((r) => r.data),
  contract: (id: number, a?: number | null) => pget<{ data: PortalContractDetail }>(`/contracts/${id}`, a).then((r) => r.data),
  sendOtp: (id: number, a?: number | null) => ppost<OtpSent>(`/contracts/${id}/sign/otp`, {}, a),
  sign: (id: number, code: string, a?: number | null) => ppost<PortalContract>(`/contracts/${id}/sign`, { code, accept: true }, a),
  invoices: (status: 'open' | 'paid' | 'all', a?: number | null) =>
    pget<{ data: PortalInvoice[]; meta: { kpis: { outstanding: Money; overdue: Money } } }>('/invoices', a, { status }),
  pay: (id: number, b: { gateway?: string; return_url?: string } = {}, a?: number | null) => ppost<PayStart>(`/invoices/${id}/pay`, b, a),
  payment: (paymentId: string, a?: number | null) => pget<{ data: PaymentStatus }>(`/payments/${paymentId}`, a).then((r) => r.data),
  services: (a?: number | null) => pget<{ data: PortalService[]; meta: { domains: PortalDomain[] } }>('/services', a),
  documents: (a?: number | null) => pget<{ data: PortalDocument[] }>('/documents', a).then((r) => r.data),
};

/** Seconds from a 429 `Retry-After` header (the API envelope strips extra body keys). */
export const retryAfter = (e: unknown): number | null => {
  const r = (e as { response?: { status?: number; headers?: Record<string, string> } })?.response;
  if (r?.status !== 429) return null;
  const v = Number(r.headers?.['retry-after'] ?? r.headers?.['Retry-After']);
  return Number.isFinite(v) && v > 0 ? v : 60;
};

// ---------------- BI ----------------
export type Buckets = { current: Money; d1_30: Money; d31_60: Money; d61_90: Money; d90_plus: Money };
export type LtvReport = { avg_ltv: Money; median_ltv: Money; total_revenue: Money; customers: number; buckets: { label: string; from: number; to: number | null; count: number }[] };
export type DebtReport = { total: Money; buckets: Buckets; top_debtors: { id: number; name: string; outstanding: Money; overdue: Money; oldest_days: number }[] };
export type ChurnReport = { active: number; churned: number; at_risk: number; churn_rate: number; by_month: { month: string; churned: number; new: number }[]; at_risk_list: { id: number; name: string; health: number }[] };
export type CohortRow = { cohort: string; size: number; months: number[] };
export type TopRow = { id: number; name: string; revenue: Money; invoices: number; last_paid_at: ISO };
export type SegmentRow = { segment: string; customers: number; revenue: Money; avg_ltv: Money; outstanding: Money };
export type ReportData = { ltv: LtvReport; debt: DebtReport; churn: ChurnReport; cohorts: CohortRow[]; top: TopRow[]; segments: SegmentRow[] };
export type ReportFilters = { from?: string; to?: string; segment?: string; limit?: number };
export const getReport = async <S extends ReportSection>(section: S, f: ReportFilters = {}) =>
  ((await apiClient.get(P5_REPORTS[section].path!, { params: f })).data as { data: ReportData[S] }).data;
