import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { afterEach, describe, expect, it, vi } from 'vitest';
import en from '../../../../../../../messages/en.json';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }));
vi.mock('@/lib/api-client', () => ({ default: api }));
const hub = vi.hoisted(() => ({ id: '7', version: 0, canDo: () => true, open: vi.fn(), refresh: vi.fn(), setNotice: vi.fn() }));
vi.mock('../HubContext', () => ({ useHub: () => hub }));

import { AccountingTab, OrdersTab, SmsTab } from './Phase3Tabs';
import { PendingTab } from './PendingTab';
import { anyMoney, hasMoney } from './phase3-api';

const p3 = en.crm.hub.c360.p3;
const wrap = (ui: React.ReactNode) =>
  render(<NextIntlClientProvider locale="en" messages={en} timeZone="Asia/Tehran">{ui}</NextIntlClientProvider>);
afterEach(() => vi.clearAllMocks());
const page = { current_page: 1, last_page: 1, per_page: 15, total: 1 };
const order = { ref: 'mo-12', id: 12, source: 'marketplace', number: 'MO-1001', status: 'paid', total: null, currency: 'IRR', paid: true, paid_at: null, ordered_at: '2026-10-01T10:00:00Z', items_count: 3, buyer_name: 'Ali', buyer_mobile: null, gateway: null, payment_ref: null, store: null };

describe('Customer 360 phase 3', () => {
  it('orders tab hits the contract path and hides null money', async () => {
    api.get.mockImplementation((url: string) => Promise.resolve({ data: url.endsWith('/basalam/stores')
      ? { data: [], meta: { candidates: [], can: { link: false } } }
      : { data: [order], meta: { ...page, kpis: { count: 1, paid_count: 1, total_amount: null, basalam_count: 0, marketplace_count: 1 }, can: { link_basalam: false } } } }));
    wrap(<OrdersTab />);
    expect(await screen.findByText('MO-1001')).toBeInTheDocument();
    expect(api.get).toHaveBeenCalledWith('/v1/crm/customers/7/hub/orders', expect.anything());
    expect(screen.queryByText(p3.total)).not.toBeInTheDocument();
    expect(screen.queryByText(p3.totalAmount)).not.toBeInTheDocument();
  });

  it('accounting loads summary, ledger, invoices and payments', async () => {
    api.get.mockImplementation((url: string) => Promise.resolve({ data: url.endsWith('/hub/accounting')
      ? { data: { currency: 'IRR', balance: -500, outstanding: 500, overdue: null, overdue_count: 0, total_invoiced: 500, total_paid: 0, wallet_credit: null, credit_limit: null, aging: null, last_payment: null, acc_person: null }, meta: { can: { record_payment: false, create_invoice: false } } }
      : { data: [], meta: { ...page, total: 0 } } }));
    wrap(<AccountingTab />);
    expect(await screen.findByText(p3.outstanding)).toBeInTheDocument();
    expect(screen.queryByText(p3.walletCredit)).not.toBeInTheDocument();
    for (const s of ['accounting/ledger', 'accounting/invoices', 'accounting/payments'])
      expect(api.get).toHaveBeenCalledWith(`/v1/crm/customers/7/hub/${s}`, expect.anything());
  });

  it.each([[false], [true]])('sms send button gated by meta.can.send=%s', async (send) => {
    api.get.mockResolvedValue({ data: { data: [], meta: { ...page, total: 0, can: { send }, recipients: [], templates: [], opt_out: false } } });
    wrap(<SmsTab />);
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/v1/crm/customers/7/hub/sms', expect.anything()));
    await screen.findByText(en.crm.hub.noData);
    expect(!!screen.queryByRole('button', { name: p3.send })).toBe(send);
  });

  it('sms send posts body + purpose', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: { ...page, total: 0, can: { send: true }, recipients: [], templates: [], opt_out: false } } });
    api.post.mockResolvedValue({ data: { data: {} } });
    wrap(<SmsTab />);
    fireEvent.click(await screen.findByRole('button', { name: p3.send }));
    fireEvent.change(screen.getByLabelText(p3.body), { target: { value: 'hi' } });
    fireEvent.click(screen.getAllByRole('button', { name: p3.send }).at(-1)!);
    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/v1/crm/customers/7/hub/sms', { purpose: 'transactional', body: 'hi' }));
  });

  it('PendingTab still gates actions on meta.can', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: { ...page, total: 0, can: { create: false } } } });
    wrap(<PendingTab area={{ key: 'x', path: 'x', ready: true }} title="X" actions={(c) => (c.create ? <button>new</button> : null)} />);
    expect(await screen.findByText(en.crm.hub.noData)).toBeInTheDocument();
    expect(screen.queryByText('new')).not.toBeInTheDocument();
  });

  it('hasMoney / anyMoney', () => {
    expect(hasMoney(null)).toBe(false);
    expect(hasMoney(0)).toBe(true);
    expect(anyMoney([{ a: null }, { a: 1 }], 'a')).toBe(true);
    expect(anyMoney([{ a: null }], 'a')).toBe(false);
  });
});
