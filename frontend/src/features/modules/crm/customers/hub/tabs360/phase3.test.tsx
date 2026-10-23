import { render, screen } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { afterEach, describe, expect, it, vi } from 'vitest';
import en from '../../../../../../../messages/en.json';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }));
vi.mock('@/lib/api-client', () => ({ default: api }));
const hub = vi.hoisted(() => ({ id: '7', version: 0, canDo: () => true, open: vi.fn(), refresh: vi.fn(), setNotice: vi.fn() }));
vi.mock('../HubContext', () => ({ useHub: () => hub }));

import { AccountingTab, OrdersTab, SmsTab } from './Phase3Tabs';
import { PendingTab } from './PendingTab';
import { hasMoney } from './phase3-api';

const wrap = (ui: React.ReactNode) =>
  render(<NextIntlClientProvider locale="en" messages={en} timeZone="Asia/Tehran">{ui}</NextIntlClientProvider>);
afterEach(() => vi.clearAllMocks());

describe('Customer 360 phase 3 skeleton', () => {
  it.each([[OrdersTab, 'orders'], [AccountingTab, 'accounting'], [SmsTab, 'sms']] as const)('%s shows pending notice and does not fetch', (Tab, k) => {
    wrap(<Tab />);
    expect(screen.getByText(en.crm.hub.c360[k])).toBeInTheDocument();
    expect(screen.getByTestId('contract-pending')).toBeInTheDocument();
    expect(api.get).not.toHaveBeenCalled();
  });

  it('ready area fetches, shows empty state and gates actions on meta.can', async () => {
    api.get.mockResolvedValue({ data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0, can: { create: false } } } });
    wrap(<PendingTab area={{ key: 'x', path: 'x', ready: true }} title="X" actions={(c) => (c.create ? <button>new</button> : null)} />);
    expect(await screen.findByText(en.crm.hub.noData)).toBeInTheDocument();
    expect(screen.queryByText('new')).not.toBeInTheDocument();
    expect(api.get).toHaveBeenCalledWith('/v1/crm/customers/7/hub/x', expect.anything());
  });

  it('hasMoney hides null money', () => {
    expect(hasMoney(null)).toBe(false);
    expect(hasMoney(0)).toBe(true);
  });
});
