import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

async function get<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  const res = await apiClient.get(url, { params });
  return unwrapData<T>(res);
}

async function send<T>(method: 'post' | 'put' | 'patch', url: string, body?: unknown): Promise<T> {
  const res = await apiClient[method](url, body);
  return unwrapData<T>(res);
}

export type Row = Record<string, unknown>;

export const expansionApi = {
  calendars: () => get<Row[]>('/v1/integrations/calendars'),
  connectUrl: (provider: 'google' | 'outlook') => get<{ url: string }>(`/v1/integrations/calendars/connect/${provider}`),
  syncCalendar: (id: number) => send<Row>('post', `/v1/integrations/calendars/${id}/sync`),
  bridges: () => get<Row[]>('/v1/integrations/bridges'),
  saveBridge: (body: Row) => send<Row>('post', '/v1/integrations/bridges', body),
  bridgeMessages: (id: number) => get<Row[]>(`/v1/integrations/bridges/${id}/messages`),
  mailboxes: () => get<Row[]>('/v1/integrations/mailbox'),
  saveMailbox: (body: Row) => send<Row>('post', '/v1/integrations/mailbox', body),
  syncMailbox: (id: number) => send<Row>('post', `/v1/integrations/mailbox/${id}/sync`),
  threads: (id: number) => get<Row[]>(`/v1/integrations/mailbox/${id}/threads`),
  sendMail: (id: number, body: Row) => send<Row>('post', `/v1/integrations/mailbox/${id}/send`, body),
  linkMail: (id: number) => send<Row>('post', `/v1/integrations/mailbox/messages/${id}/link`),
  feed: () => get<{ notifications: Row[]; deliveries: Row[] }>('/v1/integrations/notifications/feed'),
  smsPolicy: () => get<{ mode: string; floor: string; rules: Row[] }>('/v1/integrations/sms-rules'),
  saveSmsPolicy: (body: Row) => send<Row>('put', '/v1/integrations/sms-rules', body),

  companies: () => get<Row[]>('/v1/crm/companies'),
  activeCompany: () => get<Row | null>('/v1/crm/companies/active'),
  saveCompany: (body: Row) => send<Row>('post', '/v1/crm/companies', body),
  switchCompany: (id: number) => send<Row>('post', `/v1/crm/companies/${id}/switch`),
  currencies: () => get<Row[]>('/v1/crm/currencies'),
  saveRate: (body: Row) => send<Row>('post', '/v1/crm/fx-rates', body),
  convert: (params: { amount: number; from: string; to: string }) => get<Row>('/v1/crm/fx/convert', params),
  products: () => get<Row[]>('/v1/crm/catalog-products'),
  saveProduct: (body: Row) => send<Row>('post', '/v1/crm/catalog-products', body),
  priceBooks: () => get<Row[]>('/v1/crm/price-books'),
  savePriceBook: (body: Row) => send<Row>('post', '/v1/crm/price-books', body),
  quote: (dealId: number, body: Row) => send<Row>('post', `/v1/crm/deals/${dealId}/quote`, body),
  forms: () => get<Row[]>('/v1/crm/lead-forms'),
  saveForm: (body: Row) => send<Row>('post', '/v1/crm/lead-forms', body),
  crmAssist: (purpose: string, context: Row) => send<Row>('post', '/v1/crm/ai/assist', { purpose, context }),
  envelopes: () => get<Row[]>('/v1/crm/esign'),
  saveEnvelope: (body: Row) => send<Row>('post', '/v1/crm/esign', body),
  requestOtp: (envelopeId: number, signerId: number) =>
    send<Row>('post', `/v1/crm/esign/${envelopeId}/signers/${signerId}/otp`),
  sign: (envelopeId: number, signerId: number, code: string) =>
    send<Row>('post', `/v1/crm/esign/${envelopeId}/signers/${signerId}/sign`, { code }),
  invoices: () => get<Row[]>('/v1/crm/einvoices'),
  saveInvoice: (body: Row) => send<Row>('post', '/v1/crm/einvoices', body),
  submitInvoice: (id: number) => send<Row>('post', `/v1/crm/einvoices/${id}/submit`),
  contentCalendars: (accountId?: number) =>
    get<Row[]>('/v1/crm/content-calendars', accountId ? { account_id: accountId } : undefined),
  saveContentCalendar: (body: Row) => send<Row>('post', '/v1/crm/content-calendars', body),
  saveContentItem: (calendarId: number, body: Row) => send<Row>('post', `/v1/crm/content-calendars/${calendarId}/items`, body),
  updateContentItem: (id: number, body: Row) => send<Row>('patch', `/v1/crm/content-items/${id}`, body),

  criticalPath: (body: Row) => send<Row>('post', '/v1/projects/planning/critical-path', body),
  baselines: (projectId?: number) => get<Row[]>('/v1/projects/planning/baselines', projectId ? { project_id: projectId } : undefined),
  saveBaseline: (body: Row) => send<Row>('post', '/v1/projects/planning/baselines', body),
  saveCapacity: (body: Row) => send<Row>('post', '/v1/projects/planning/capacity', body),
  saveLeave: (body: Row) => send<Row>('post', '/v1/projects/planning/leave', body),
  capacity: (params?: Row) => get<Row[]>('/v1/projects/planning/capacity', params),
  updateBoard: (id: number, body: Row) => send<Row>('patch', `/v1/projects/kanban/boards/${id}`, body),
  offlineOps: (ops: Row[]) => send<Row[]>('post', '/v1/projects/offline/ops', { ops }),
  pmAssist: (purpose: string, context: Row) => send<Row>('post', '/v1/projects/ai/assist', { purpose, context }),

  workflows: () => get<Row[]>('/v1/core/workflows'),
  saveWorkflow: (body: Row) => send<Row>('post', '/v1/core/workflows', body),
  runWorkflow: (id: number) => send<Row>('post', `/v1/core/workflows/${id}/run`, { context: {} }),
  reports: () => get<Row[]>('/v1/core/bi-reports'),
  saveReport: (body: Row) => send<Row>('post', '/v1/core/bi-reports', body),
  runReport: (id: number) => get<Row>(`/v1/core/bi-reports/${id}/run`),
  flags: () => get<Row[]>('/v1/core/flags'),
  saveFlag: (body: Row) => send<Row>('post', '/v1/core/flags', body),
  evaluateFlags: (keys: string[], sandbox: boolean) =>
    get<{ sandbox: boolean; flags: Record<string, boolean> }>('/v1/core/flags/evaluate', { keys, sandbox: sandbox ? 1 : 0 }),
  sso: () => get<Row[]>('/v1/core/sso'),
  saveSso: (body: Row) => send<Row>('post', '/v1/core/sso', body),
  oidcRedirect: (id: number) => get<{ url: string }>(`/v1/core/sso/oidc/${id}/redirect`),
  issueScim: (name: string) => send<{ token: string }>('post', '/v1/core/sso/scim-tokens', { name }),
};
