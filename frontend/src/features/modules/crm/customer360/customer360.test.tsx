import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { afterEach, describe, expect, it, vi } from 'vitest';
import en from '../../../../../messages/en.json';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('@/lib/api-client', () => ({ default: api }));
import { Customer360DataPage, Customer360PortalPage, Customer360ReportsPage } from './Customer360Pages';
import { P5_REPORTS, mergeUrl, retryAfter } from './phase5-api';

const C = en.crm.hub.c360;
const P = C.p5;
const wrap = (ui: React.ReactNode) => render(<NextIntlClientProvider locale="en" messages={en} timeZone="Asia/Tehran">{ui}</NextIntlClientProvider>);
afterEach(() => vi.clearAllMocks());
const page = { current_page: 1, last_page: 1, per_page: 20, total: 1 };
const acc = { id: 3, name: 'Acme', brand_name: null, national_id: null, economic_code: null, mobile: '0912', email: null, website: null, address_line: null, city: null, province: null, postal_code: null, hub_status: 'active' };

describe('Customer 360 phase 5', () => {
  it('portal profile + contract signing via OTP', async () => {
    api.get.mockImplementation((url: string) => Promise.resolve({ data: url.endsWith('/portal/profile')
      ? { data: { account: acc, accounts: [{ id: 3, name: 'Acme', is_primary: true }] } }
      : { data: [{ id: 9, title: 'Hosting', status: 'active', amount: 100, starts_at: null, ends_at: null, signed_at: null, can_sign: true, document_id: null }] } }));
    api.post.mockImplementation((url: string) => Promise.resolve({ data: { data: url.endsWith('/otp') ? { sent_to: '0912***', expires_in: 300, resend_in: 60 } : { id: 9, status: 'signed' } } }));
    wrap(<Customer360PortalPage />);
    expect(await screen.findByText('Acme')).toBeInTheDocument();
    expect(api.get).toHaveBeenCalledWith('/v1/projects/portal/profile', expect.anything());
    fireEvent.click(screen.getByRole('tab', { name: C.contracts }));
    fireEvent.click(await screen.findByRole('button', { name: P.sign }));
    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/v1/projects/portal/contracts/9/sign/otp', {}, expect.anything()));
    fireEvent.change(await screen.findByRole("textbox", { name: new RegExp(`^${P.otpCode}`) }), { target: { value: '123456' } });
    fireEvent.click(screen.getByLabelText(P.accept));
    fireEvent.click(screen.getAllByRole('button', { name: P.sign }).at(-1)!);
    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/v1/projects/portal/contracts/9/sign', { code: '123456', accept: true }, expect.anything()));
  });

  it('reports hit /crm/customers/reports/{section}', async () => {
    api.get.mockResolvedValue({ data: { data: { avg_ltv: null, median_ltv: null, total_revenue: null, customers: 2, buckets: [] } } });
    wrap(<Customer360ReportsPage />);
    expect(await screen.findByText(P.avgLtv)).toBeInTheDocument();
    expect(api.get).toHaveBeenCalledWith('/v1/crm/customers/reports/ltv', expect.anything());
    for (const k of ['ltv', 'debt', 'churn', 'cohorts', 'top'] as const) expect(screen.getByRole('tab', { name: C[k] })).toBeInTheDocument();
    expect(P5_REPORTS.segments.path).toBe('/v1/crm/customers/reports/segments');
  });

  it('duplicates: merge gated by meta.can and posts source_id', async () => {
    const b = (id: number, name: string) => ({ id, name, mobile: null, email: null, national_id: null, website: null, created_at: null, invoices_count: 0 });
    api.get.mockResolvedValue({ data: { data: [{ key: '1-2', score: 90, reasons: ['mobile'], a: b(1, 'Ali'), b: b(2, 'Ali B'), suggested_target_id: 1 }], meta: { ...page, can: { merge: true } } } });
    api.post.mockResolvedValue({ data: {} });
    wrap(<Customer360DataPage />);
    fireEvent.click(await screen.findByRole('button', { name: P.merge }));
    fireEvent.click(screen.getAllByRole('button', { name: P.merge }).at(-1)!);
    await waitFor(() => expect(api.post).toHaveBeenCalledWith(mergeUrl(1), { source_id: 2 }));
    expect(api.get).toHaveBeenCalledWith('/v1/crm/customers/duplicates', expect.anything());
  });

  it('retryAfter reads 429 header', () => {
    expect(retryAfter({ response: { status: 429, headers: { 'retry-after': '42' } } })).toBe(42);
    expect(retryAfter({ response: { status: 422 } })).toBeNull();
  });

  it('phase 5 routes are enabled', async () => {
    const { CUSTOMER360_PHASE5_ENABLED } = await import('./flags');
    expect(CUSTOMER360_PHASE5_ENABLED).toBe(true);
  });
});
