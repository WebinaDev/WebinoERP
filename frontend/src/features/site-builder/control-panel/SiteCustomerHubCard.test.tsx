import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { afterEach, describe, expect, it, vi } from 'vitest';
import fa from '../../../../messages/fa.json';

const api = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn(), post: vi.fn() }));
vi.mock('@/lib/api-client', () => ({ default: api }));
vi.mock('@/features/shared/crm/AccountSelect', () => ({
  AccountSelect: ({ onChange }: { onChange: (v: string) => void }) => (
    <button type="button" onClick={() => onChange('42')}>pick-42</button>
  ),
}));

import { SiteCustomerHubCard } from './SiteCustomerHubCard';

const wrap = () =>
  render(
    <NextIntlClientProvider locale="fa" messages={fa} timeZone="Asia/Tehran">
      <SiteCustomerHubCard provisionId={1} />
    </NextIntlClientProvider>,
  );

const full = {
  linked: true, restricted: false, can_link: true,
  sections: ['identity', 'licenses', 'billing', 'support', 'usage', 'timeline', 'site_panel', 'assets'],
  actions: {}, provision: { id: 1, domain: 'ali.ir' }, site_status: { status: 'coming_soon' },
  customer: { id: 7, name: 'علی خباری' },
  header: { id: 7, name: 'علی خباری', code: 'C-7', status: 'active', primary_email: 'ali@ali.ir' },
  kpis: { active_licenses: 1, open_tickets: 2, active_sites: 1, last_payment: { amount: 500000, date: '2026-10-01' } },
  health: { score: 72, grade: 'fair', churn_risk: 30 },
  alerts: [{ code: 'license_expiring', severity: 'critical', body: 'لایسنس رو به انقضا', value: 'ali.ir', days: 5 }],
  identity: { profile: { national_id: '123' }, kyc: { status: 'verified' }, contacts: [] },
  licenses: [{ id: 3, domain: 'ali.ir', expires_at: '2026-10-28', days_left: 5, effective_status: 'expiring', this_site: true, key_masked: '••••1234' }],
  assets: { sites: [{ provision_id: 1, domain: 'ali.ir', status: 'ready', this_site: true }], domains: [], hosting: [], integrations_count: 0 },
  billing: { outstanding: 1200000, overdue: 0, wallet_credit: 0, sms_credit: 0, revenue_12m: 0,
    recent_invoices: [{ id: 9, number: 'INV-9', total: 1200000, status: 'unpaid', issue_date: '2026-10-01' }], recent_payments: [] },
  support: { open: 2, breached: 0, total: 3, recent: [{ id: 4, subject: 'فونت خراب', status: 'open', created_at: '2026-10-20' }] },
  usage: { site_orders: 12, sms: { sent: 0 } },
  timeline: [{ kind: 'site', title: 'ایجاد سایت ali.ir', date: '2026-10-10' }],
};

afterEach(() => vi.clearAllMocks());

describe('SiteCustomerHubCard', () => {
  it('renders a rich owner summary and the site panel row for this site', async () => {
    api.get.mockImplementation((url: string) =>
      url.endsWith('/hub/site_panel')
        ? Promise.resolve({ data: { data: { sites: [
          { provision_id: 2, ok: true, matches: [] },
          { provision_id: 1, ok: true, matches: [{ user: { email: 'ali@ali.ir' }, kyc: { status: 'verified' }, orders: [{}, {}], tickets: [] }] },
        ] } } })
        : Promise.resolve({ data: { data: full } }),
    );
    wrap();
    expect(await screen.findByTestId('customer-hub-header')).toHaveTextContent('علی خباری');
    expect(screen.getByText('هاب مشتری')).toBeInTheDocument();
    expect(screen.getByTestId('customer-hub-licenses')).toHaveTextContent('این سایت');
    expect(screen.getByTestId('customer-hub-billing')).toHaveTextContent('INV-9');
    expect(screen.getByTestId('customer-hub-support')).toHaveTextContent('فونت خراب');
    expect(screen.getByTestId('customer-hub-alerts')).toHaveTextContent('لایسنس رو به انقضا');
    expect(screen.getByTestId('customer-hub-kpis')).toHaveTextContent('به‌زودی');
    await waitFor(() => expect(screen.getByTestId('customer-hub-site-panel')).toHaveTextContent('ali@ali.ir'));
    expect(api.get).toHaveBeenCalledWith('/v1/crm/customers/7/hub/site_panel', expect.anything());
    expect(screen.getByRole('link', { name: /باز کردن هاب کامل مشتری/ })).toHaveAttribute('href', expect.stringContaining('crm/customers/7'));
  });

  it('hides sections the user cannot see', async () => {
    api.get.mockResolvedValue({ data: { data: { ...full, sections: ['identity'], billing: undefined, support: undefined, licenses: undefined, usage: undefined, timeline: undefined, assets: undefined } } });
    wrap();
    await screen.findByTestId('customer-hub-identity');
    expect(screen.queryByTestId('customer-hub-billing')).toBeNull();
    expect(screen.queryByTestId('customer-hub-support')).toBeNull();
    expect(screen.queryByTestId('customer-hub-site-panel')).toBeNull();
    expect(api.get).toHaveBeenCalledTimes(1);
  });

  it('offers link-customer when the site has no owner', async () => {
    api.get.mockResolvedValue({ data: { data: { linked: false, can_link: true, sections: [], actions: {}, provision: { id: 1 }, site_status: {} } } });
    api.put.mockResolvedValue({ data: { data: { ...full, sections: ['identity'] } } });
    wrap();
    await screen.findByTestId('customer-hub-unlinked');
    fireEvent.click(screen.getByText('pick-42'));
    fireEvent.click(screen.getByTestId('customer-hub-link'));
    await waitFor(() => expect(api.put).toHaveBeenCalledWith('/v1/site-builder/provisions/1/customer-hub/link', { crm_account_id: 42 }));
    expect(await screen.findByTestId('customer-hub-header')).toBeInTheDocument();
  });
});
