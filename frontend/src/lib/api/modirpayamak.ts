import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/integrations/modirpayamak';

export type ModirPayamakAccount = {
  id: number;
  domain: string;
  balance: number;
  default_from?: string;
  status: string;
  expires_at?: string | null;
};

export type ModirPayamakPackage = {
  id: number;
  name: string;
  amount: number;
  bonus: number;
  sort: number;
  status: string;
};

export type ModirPayamakStats = {
  total_customers: number;
  sent_today: number;
  pending_orders: number;
  reseller_credit: unknown;
  price_per_unit: number;
  configured: boolean;
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

export type ModirPayamakTariff = {
  id: number;
  line_type: string;
  operator: 'mci' | 'other' | string;
  rate_fa: number;
  rate_la: number;
  sort: number;
  status: string;
};

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
  const data = unwrapData<ModirPayamakAccount[]>(res);
  return Array.isArray(data) ? data : [];
}

export async function adjustModirPayamakBalance(domain: string, amount: number, note?: string) {
  const res = await apiClient.post(`${BASE}/admin/customers/balance`, { domain, amount, note });
  return unwrapData(res);
}

export async function getModirPayamakPackages() {
  const res = await apiClient.get(`${BASE}/admin/packages`);
  const data = unwrapData<ModirPayamakPackage[]>(res);
  return Array.isArray(data) ? data : [];
}

export async function saveModirPayamakPackage(data: Partial<ModirPayamakPackage>) {
  const res = await apiClient.post(`${BASE}/admin/packages`, data);
  return unwrapData(res);
}

export async function deleteModirPayamakPackage(id: number) {
  const res = await apiClient.delete(`${BASE}/admin/packages/${id}`);
  return unwrapData(res);
}

export async function getModirPayamakOrders(page = 1) {
  const res = await apiClient.get(`${BASE}/admin/orders`, { params: { page } });
  return unwrapData(res);
}

export async function modirPayamakSend(payload: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/admin/send`, payload);
  return unwrapData(res);
}

export async function modirPayamakCalculatePrice(payload: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/send/calculate-price`, payload);
  return unwrapData(res);
}

export async function getModirPayamakOutbox(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/admin/reports/outbox`, { params });
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
