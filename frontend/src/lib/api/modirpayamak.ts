import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/integrations/modirpayamak';

export type ModirPayamakDomainNumber = {
  id?: number;
  domain?: string;
  number: string;
  role: 'service' | 'personal' | 'marketing' | string;
  label?: string;
  is_default?: boolean;
};

export type ModirPayamakAccount = {
  id: number;
  domain: string;
  balance: number;
  default_from?: string;
  status: string;
  expires_at?: string | null;
  numbers?: ModirPayamakDomainNumber[];
};

export type ModirPayamakLedgerRow = {
  id: number;
  type: string;
  amount: number;
  balance_after: number;
  note?: string;
  created_at?: string;
};

export type ModirPayamakPackage = {
  id: number;
  name: string;
  amount: number;
  bonus: number;
  sort: number;
  status: string;
  sms_units?: number;
  sort_order?: number;
  is_active?: boolean;
};

export type ModirPayamakOrder = {
  id: number;
  domain: string;
  package_id?: number;
  amount: number;
  credit_amount?: number;
  status: string;
  authority?: string;
  ref_id?: string;
  created_at?: string;
};

export type ModirPayamakStats = {
  total_customers: number;
  sent_today: number;
  pending_orders: number;
  reseller_credit: unknown;
  price_per_unit: number;
  configured: boolean;
};

export type ModirPayamakTariff = {
  id: number;
  line_type: string;
  operator: 'mci' | 'other' | string;
  rate_fa: number;
  rate_la: number;
  sort: number;
  status: string;
};

export type ModirPayamakPatternScope = 'order_customer' | 'order_admin' | 'site';

export type ModirPayamakRegistryRow = {
  domain?: string;
  scope?: string;
  event_key?: string;
  ippanel_code?: string;
  sync_status?: string;
  last_error?: string;
  param_map?: Record<string, string>;
};

export async function getModirPayamakDashboard() {
  const res = await apiClient.get(`${BASE}/admin/dashboard`);
  return unwrapData<{
    stats?: ModirPayamakStats;
    configured?: boolean;
    accounts?: number;
    orders_pending?: number;
    orders_paid?: number;
  }>(res);
}

export async function getModirPayamakTariffs() {
  const res = await apiClient.get(`${BASE}/admin/tariffs`);
  return unwrapData<{
    tariffs: ModirPayamakTariff[];
    tax_percent?: number;
    surcharge_rial?: number;
  }>(res);
}

export async function saveModirPayamakTariff(data: Partial<ModirPayamakTariff>) {
  const res = await apiClient.post(`${BASE}/admin/tariffs`, data);
  return unwrapData(res);
}

export async function deleteModirPayamakTariff(id: number) {
  const res = await apiClient.delete(`${BASE}/admin/tariffs/${id}`);
  return unwrapData(res);
}

export async function getModirPayamakDomainSecretaries(domain: string) {
  const res = await apiClient.get(`${BASE}/admin/secretaries`, { params: { domain } });
  return unwrapData<{ secretaries: Array<Record<string, unknown>> }>(res);
}

export async function saveModirPayamakDomainSecretary(data: {
  domain: string;
  type?: string;
  name?: string;
  keywords?: string;
  reply_body?: string;
  forward_to?: string;
  pattern_code?: string;
  enabled?: boolean;
  id?: number;
}) {
  const res = await apiClient.post(`${BASE}/admin/secretaries`, data);
  return unwrapData(res);
}

export async function deleteModirPayamakDomainSecretary(domain: string, id: number) {
  const res = await apiClient.post(`${BASE}/admin/secretaries/delete`, { domain, id });
  return unwrapData(res);
}

export async function getModirPayamakAccount() {
  const res = await apiClient.get(`${BASE}/account`);
  return unwrapData(res);
}

export async function getModirPayamakCustomers() {
  const res = await apiClient.get(`${BASE}/admin/customers`);
  const data = unwrapData<{ accounts?: ModirPayamakAccount[] } | ModirPayamakAccount[]>(res);
  if (Array.isArray(data)) return { accounts: data };
  return { accounts: Array.isArray(data?.accounts) ? data.accounts : [] };
}

export async function adjustModirPayamakBalance(domain: string, amount: number, note?: string) {
  const res = await apiClient.post(`${BASE}/admin/customers/balance`, { domain, amount, note });
  return unwrapData<{ account?: ModirPayamakAccount; message?: string }>(res);
}

export async function getModirPayamakCustomerLedger(domain: string, page = 1) {
  const res = await apiClient.get(`${BASE}/admin/customers/ledger`, { params: { domain, page } });
  return unwrapData<{ ledger: ModirPayamakLedgerRow[]; account?: ModirPayamakAccount }>(res);
}

export async function attachModirPayamakNumber(data: {
  domain: string;
  number: string;
  role: 'service' | 'personal' | 'marketing';
  label?: string;
}) {
  const res = await apiClient.post(`${BASE}/admin/numbers/attach`, {
    domain: data.domain,
    number: data.number,
    role: data.role === 'marketing' ? 'personal' : data.role,
    label: data.label ?? '',
  });
  return unwrapData<{
    number: ModirPayamakDomainNumber;
    account?: ModirPayamakAccount;
    attachments?: ModirPayamakDomainNumber[];
    message?: string;
  }>(res);
}

export async function detachModirPayamakNumber(
  domain: string,
  role: 'service' | 'personal' | 'marketing',
  number?: string,
) {
  const res = await apiClient.post(`${BASE}/admin/numbers/detach`, {
    domain,
    role: role === 'marketing' ? 'personal' : role,
    number: number ?? '',
  });
  return unwrapData<{ account?: ModirPayamakAccount; message?: string }>(res);
}

export async function attachModirPayamakPattern(data: {
  domain: string;
  scope: ModirPayamakPatternScope;
  event_key: string;
  pattern_code: string;
  param_map?: Record<string, string>;
}) {
  const res = await apiClient.post(`${BASE}/admin/patterns/attach`, {
    domain: data.domain,
    scope: data.scope,
    event_key: data.event_key,
    pattern_code: data.pattern_code,
    param_map: data.param_map ?? undefined,
  });
  return unwrapData<{ message?: string; result?: unknown; registry?: ModirPayamakRegistryRow[] }>(res);
}

export async function detachModirPayamakPattern(data: {
  domain: string;
  scope: ModirPayamakPatternScope;
  event_key: string;
}) {
  const res = await apiClient.post(`${BASE}/admin/patterns/detach`, data);
  return unwrapData<{ message?: string; registry?: ModirPayamakRegistryRow[] }>(res);
}

export async function getModirPayamakDomainPatternRegistry(domain = '') {
  const res = await apiClient.get(`${BASE}/admin/patterns/registry`, {
    params: domain ? { domain } : {},
  });
  return unwrapData<{ registry: ModirPayamakRegistryRow[]; events?: string[] }>(res);
}

export async function getModirPayamakMessages(domain = '', page = 1, limit = 20) {
  const res = await apiClient.get(`${BASE}/admin/messages`, {
    params: { domain, page, limit },
  });
  return unwrapData<{ messages: Array<Record<string, unknown>> }>(res);
}

export async function getModirPayamakPackages() {
  const res = await apiClient.get(`${BASE}/admin/packages`);
  const data = unwrapData<{ packages?: ModirPayamakPackage[] } | ModirPayamakPackage[]>(res);
  if (Array.isArray(data)) return { packages: data };
  return { packages: Array.isArray(data?.packages) ? data.packages : [] };
}

export async function saveModirPayamakPackage(data: Partial<ModirPayamakPackage>) {
  const res = await apiClient.post(`${BASE}/admin/packages`, {
    id: data.id,
    name: data.name ?? '',
    amount: data.amount ?? 0,
    bonus: data.bonus ?? data.sms_units ?? 0,
    sort: data.sort ?? data.sort_order ?? 0,
    status: data.status ?? (data.is_active === false ? 'inactive' : 'active'),
  });
  return unwrapData(res);
}

export async function deleteModirPayamakPackage(id: number) {
  const res = await apiClient.delete(`${BASE}/admin/packages/${id}`);
  return unwrapData(res);
}

export async function getModirPayamakOrders(page = 1) {
  const res = await apiClient.get(`${BASE}/admin/orders`, { params: { page } });
  const body = res.data as {
    data?: { orders?: ModirPayamakOrder[] };
    meta?: { current_page?: number; last_page?: number; total?: number; per_page?: number };
  };
  const data = unwrapData<{ orders?: ModirPayamakOrder[] }>(res);
  return {
    orders: Array.isArray(data?.orders) ? data.orders : [],
    meta: body.meta ?? {},
  };
}

export async function modirPayamakSend(payload: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/admin/send`, payload);
  return unwrapData(res);
}

export async function adminModirPayamakSend(payload: Record<string, unknown>) {
  return modirPayamakSend(payload);
}

export async function modirPayamakCalculatePrice(payload: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/send/calculate-price`, payload);
  return unwrapData(res);
}

export async function getModirPayamakOutbox(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/admin/reports/outbox`, { params });
  return unwrapData(res);
}

export async function getModirPayamakInbox(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/admin/reports/inbox`, { params });
  return unwrapData(res);
}

export async function getModirPayamakOutboxDetail(id: string | number) {
  const res = await apiClient.get(`${BASE}/admin/reports/outbox/${id}`);
  return unwrapData(res);
}

export async function getModirPayamakPatterns() {
  const res = await apiClient.get(`${BASE}/admin/patterns`);
  return unwrapData(res);
}

export async function getModirPayamakNumbers() {
  const res = await apiClient.get(`${BASE}/admin/numbers`);
  return unwrapData(res);
}

export async function getModirPayamakPhonebooks() {
  const res = await apiClient.get(`${BASE}/admin/phonebooks`);
  return unwrapData(res);
}

export async function saveModirPayamakPhonebook(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/admin/phonebooks`, data);
  return unwrapData(res);
}

export async function getModirPayamakPhonebookContacts(id: number | string) {
  const res = await apiClient.get(`${BASE}/admin/phonebooks/${id}/contacts`);
  return unwrapData(res);
}

export async function saveModirPayamakPhonebookContact(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/admin/phonebooks/${id}/contacts`, data);
  return unwrapData(res);
}

export async function getModirPayamakSettings() {
  const res = await apiClient.get(`${BASE}/settings`);
  return unwrapData(res);
}

export async function updateModirPayamakSettings(data: Record<string, unknown>) {
  const res = await apiClient.put(`${BASE}/settings`, data);
  return unwrapData(res);
}

export async function modirPayamakTopupInit(packageId: number) {
  const res = await apiClient.post(`${BASE}/topup/init`, { package_id: packageId });
  return unwrapData(res);
}

export async function modirPayamakProxy(
  method: string,
  path: string,
  body?: Record<string, unknown>,
  query?: Record<string, unknown>,
) {
  const res = await apiClient.post(`${BASE}/admin/proxy`, { method, path, body, query });
  const envelope = res.data as { data?: unknown; meta?: Record<string, unknown>; message?: string };
  return {
    ok: res.status < 400,
    data: envelope.data ?? envelope,
    meta: envelope.meta ?? {},
    message: typeof envelope.message === 'string' ? envelope.message : '',
  };
}
