/**
 * Customer 360 phase 3 — API layer (orders / accounting / SMS).
 * Contract: docs/customer360/PHASE3_API.md. Only fields listed there are typed here.
 */
import apiClient from '@/lib/api-client';
import { hubBase, type PageMeta } from '../hub-api';

export type ContractArea = { key: string; path: string | null; ready: boolean };

export const P3_AREAS = {
  orders: { key: 'orders', path: 'orders', ready: true },
  accounting: { key: 'accounting', path: 'accounting', ready: true },
  sms: { key: 'sms', path: 'sms', ready: true },
} satisfies Record<string, ContractArea>;

/** Money fields come back null when the viewer cannot see `billing` — hide them. */
export const hasMoney = (v: unknown): v is number => v !== null && v !== undefined;
/** True when at least one row carries the money field (otherwise the column is hidden). */
export const anyMoney = (rows: object[], key: string) => rows.some((r) => hasMoney((r as Record<string, unknown>)[key]));

type Money = number | null;
type ISO = string | null;

// ---- Orders ----
export type OrderSource = 'marketplace' | 'basalam';
export type OrderRow = {
  ref: string; id: number; source: OrderSource; number: string; status: string;
  total: Money; currency: 'IRR'; paid: boolean; paid_at: ISO; ordered_at: ISO; items_count: number;
  buyer_name: string | null; buyer_mobile: string | null; gateway: string | null; payment_ref: string | null;
  store: { connection_id: number; site_url: string } | null;
};
export type OrderItem = { title: string; qty: number; unit_price: Money; total: Money; sku: string | null };
export type OrderDetail = OrderRow & { items: OrderItem[] };
export type OrdersMeta = PageMeta & {
  kpis?: { count: number; paid_count: number; total_amount: Money; basalam_count: number; marketplace_count: number };
  can: { link_basalam: boolean };
};
export type BasalamStore = { connection_id: number; site_url: string; vendor_id: unknown; status: string; connected_at: ISO; last_seen_at: ISO; linked: boolean };
export type BasalamStoresRes = { data: BasalamStore[]; meta: { candidates: BasalamStore[]; can: { link: boolean } } };

export const getOrder = async (id: string, ref: string) =>
  ((await apiClient.get(`${hubBase(id)}/orders/${ref}`)).data as { data: OrderDetail }).data;
export const getBasalamStores = async (id: string) =>
  (await apiClient.get(`${hubBase(id)}/basalam/stores`)).data as BasalamStoresRes;
export const linkBasalamStore = (id: string, connection_id: number, force = false) =>
  apiClient.post(`${hubBase(id)}/basalam/stores`, force ? { connection_id, force: true } : { connection_id });
export const unlinkBasalamStore = (id: string, connectionId: number) =>
  apiClient.delete(`${hubBase(id)}/basalam/stores/${connectionId}`);

// ---- Accounting ----
export type Aging = { current: Money; d1_30: Money; d31_60: Money; d61_90: Money; d90_plus: Money };
export type PaymentRow = {
  id: string; source: 'gateway' | 'marketplace' | 'manual'; date: ISO; amount: Money;
  gateway: string | null; tracking_code: string | null; status: string; paid: boolean; invoice_id: number | null;
};
export type AccountingSummary = {
  currency: 'IRR'; balance: Money; outstanding: Money; overdue: Money; overdue_count: number | null;
  total_invoiced: Money; total_paid: Money; wallet_credit: Money; credit_limit: Money;
  aging: Aging | null; last_payment: PaymentRow | null; acc_person: { id: number; name: string } | null;
};
export type AccountingSummaryRes = { data: AccountingSummary; meta: { can: { record_payment: boolean; create_invoice: boolean } } };
export type LedgerRow = { date: ISO; kind: 'invoice' | 'payment' | 'receipt' | 'refund'; ref: string; description: string; debit: Money; credit: Money; balance: Money };
export type LedgerMeta = PageMeta & { opening_balance: Money; closing_balance: Money };
export type InvoiceRow = {
  id: number; number: string; status: string; issue_date: ISO; due_date: ISO; total: Money; paid_amount: Money; remaining: Money;
  overdue_days: number | null; source: 'sales'; project_id: number | null;
};
export type InvoicesMeta = PageMeta & { kpis?: { open: number; overdue: number; paid: number } };

// ---- SMS ----
export type SmsStatus = 'sent' | 'queued' | 'failed' | 'logged';
export type SmsRow = {
  id: number; to: string; body: string; status: SmsStatus; provider: string | null; error: string | null; template_id: number | null;
  related_type: string | null; related_id: number | null; created_at: ISO; created_by: { id: number; name: string } | null;
};
export type SmsRecipient = { kind: 'account' | 'contact'; id: number; label: string; mobile: string };
export type SmsTemplate = { id: number; name: string; body: string };
export type SmsMeta = PageMeta & { can: { send: boolean }; recipients: SmsRecipient[]; templates: SmsTemplate[]; opt_out: boolean };
export type SmsSendBody = { to?: string; contact_id?: number; body?: string; template_id?: number; purpose?: 'transactional' | 'marketing' };
export const SMS_BODY_MAX = 900;

export const sendSms = async (id: string, b: SmsSendBody) =>
  ((await apiClient.post(`${hubBase(id)}/sms`, b)).data as { data: SmsRow }).data;
