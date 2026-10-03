import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/integrations/payments';

export type PaymentField = {
  key: string;
  secret: boolean;
  label_fa: string;
  label_en: string;
  configured: boolean;
  masked: string | null;
};

export type PaymentGatewaySettings = {
  code: string;
  label_fa: string;
  label_en: string;
  supports: Array<'cash' | 'installment' | string>;
  enabled: boolean;
  sandbox: boolean;
  cash_enabled: boolean;
  installment_enabled: boolean;
  fee_percent: number;
  apply_fee_on_cash: boolean;
  callback_base_url: string;
  base_url: string;
  local_simulation: boolean;
  notes_fa: string;
  modes?: string[];
  fields?: PaymentField[];
};

export type PaymentQuote = {
  gateway: string;
  mode: 'cash' | 'installment';
  currency: string;
  base_amount: number;
  fee_percent: number;
  fee_amount: number;
  total_amount: number;
  fee_applied: boolean;
};

export type PaymentIntentRow = {
  payment_id: string;
  payable_type: string;
  payable_id: string;
  domain?: string | null;
  mode: string;
  gateway: string;
  currency: string;
  base_amount: number;
  fee_percent: number;
  fee_amount: number;
  total_amount: number;
  fee_applied: boolean;
  status: string;
  authority?: string | null;
  provider_ref?: string | null;
  redirect_url?: string | null;
  description?: string | null;
  failure_reason?: string | null;
  created_at?: string | null;
};

export type PaymentBill = {
  payable_type: string;
  payable_id: string;
  title: string;
  amount: number;
  currency: string;
  domain?: string | null;
  status: string;
};

export async function getPaymentGateways(): Promise<PaymentGatewaySettings[]> {
  const res = await apiClient.get(`${BASE}/gateways`);
  return (unwrapData<PaymentGatewaySettings[]>(res) ?? []) as PaymentGatewaySettings[];
}

export async function savePaymentGateways(gateways: Record<string, Record<string, unknown>>): Promise<PaymentGatewaySettings[]> {
  const res = await apiClient.put(`${BASE}/gateways`, { gateways });
  return (unwrapData<PaymentGatewaySettings[]>(res) ?? []) as PaymentGatewaySettings[];
}

export async function testPaymentGateway(code: string): Promise<{ ok: boolean; message: string }> {
  const res = await apiClient.post(`${BASE}/gateways/${code}/test`);
  return (unwrapData<{ ok: boolean; message: string }>(res) ?? { ok: false, message: '' });
}

export async function getPaymentOptions(): Promise<{ cash: PaymentGatewaySettings[]; installment: PaymentGatewaySettings[] }> {
  const res = await apiClient.get(`${BASE}/options`);
  const data = unwrapData<{ cash: PaymentGatewaySettings[]; installment: PaymentGatewaySettings[] }>(res);
  return {
    cash: data?.cash ?? [],
    installment: data?.installment ?? [],
  };
}

export async function quotePayment(body: { gateway: string; mode: 'cash' | 'installment'; base_amount: number }): Promise<PaymentQuote> {
  const res = await apiClient.post(`${BASE}/quote`, body);
  return unwrapData<PaymentQuote>(res) as PaymentQuote;
}

export async function createPaymentIntent(body: {
  payable_type: string;
  payable_id?: string;
  mode: 'cash' | 'installment';
  gateway?: string;
  domain?: string;
  mobile?: string;
  amount?: number;
  return_url?: string;
  description?: string;
}): Promise<PaymentIntentRow> {
  const res = await apiClient.post(`${BASE}/intents`, body);
  return unwrapData<PaymentIntentRow>(res) as PaymentIntentRow;
}

export async function listPaymentIntents(params?: { payable_type?: string; payable_id?: string; domain?: string }): Promise<PaymentIntentRow[]> {
  const res = await apiClient.get(`${BASE}/intents`, { params });
  return (unwrapData<PaymentIntentRow[]>(res) ?? []) as PaymentIntentRow[];
}

export async function getPaymentBills(domain?: string): Promise<{ bills: PaymentBill[]; offers: PaymentBill[] }> {
  const res = await apiClient.get(`${BASE}/bills`, { params: domain ? { domain } : undefined });
  const data = unwrapData<{ bills: PaymentBill[]; offers: PaymentBill[] }>(res);
  return { bills: data?.bills ?? [], offers: data?.offers ?? [] };
}
