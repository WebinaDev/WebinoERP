import { act, fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import en from '../../../../../../messages/en.json';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }));
vi.mock('@/lib/api-client', () => ({ default: api }));
const hub = vi.hoisted(() => ({ id: '7', version: 0, canDo: () => true, open: vi.fn(), refresh: vi.fn(), setNotice: vi.fn() }));
vi.mock('./HubContext', () => ({ useHub: () => hub }));

import { FormDialog, clean } from './FormDialog';
import { openDocument, useHubLive } from './hub-api';
import { DocumentsTab, SitePanelTab } from './TabsMore';

const wrap = (ui: React.ReactNode) =>
  render(<NextIntlClientProvider locale="en" messages={en} timeZone="Asia/Tehran">{ui}</NextIntlClientProvider>);

afterEach(() => {
  vi.clearAllMocks();
  vi.useRealTimers();
});

describe('FormDialog', () => {
  it('blocks submit until the required confirm checkbox is ticked', async () => {
    const onSubmit = vi.fn().mockResolvedValue({});
    wrap(<FormDialog open onOpenChange={() => undefined} title="Suspend" submitLabel="Go" onSubmit={onSubmit}
      fields={[{ name: 'confirm', label: 'I confirm', type: 'checkbox', required: true }]} />);
    const submit = screen.getByRole('button', { name: 'Go' });
    fireEvent.click(submit);
    expect(await screen.findByText('I confirm *', { selector: 'p' })).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole('checkbox'));
    fireEvent.click(submit);
    await waitFor(() => expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ confirm: true })));
  });

  it('clean() drops null and File values', () => {
    const f = new File(['x'], 'a.pdf');
    expect(clean({ a: 'x', b: null, c: f, d: '', n: '5' }, ['n'])).toEqual({ a: 'x', n: 5 });
  });
});

describe('openDocument', () => {
  it('requests a signed link and opens it', async () => {
    api.post.mockResolvedValue({ data: { data: { url: 'https://erp/signed?sig=1' } } });
    const spy = vi.spyOn(window, 'open').mockReturnValue(null);
    await openDocument('7', 3);
    expect(api.post).toHaveBeenCalledWith('/v1/crm/customers/7/hub/documents/3/download-link');
    expect(spy).toHaveBeenCalledWith('https://erp/signed?sig=1', '_blank', 'noopener');
  });
});

describe('useHubLive', () => {
  beforeEach(() => vi.useFakeTimers());
  it('polls while visible and refreshes when the tab becomes visible', () => {
    const cb = vi.fn();
    const { unmount } = renderHook(() => useHubLive('7', cb, 1000));
    act(() => { vi.advanceTimersByTime(3000); });
    expect(cb).toHaveBeenCalledTimes(3);
    act(() => { document.dispatchEvent(new Event('visibilitychange')); });
    expect(cb).toHaveBeenCalledTimes(4);
    unmount();
    act(() => { vi.advanceTimersByTime(5000); });
    expect(cb).toHaveBeenCalledTimes(4);
  });
});

describe('SitePanelTab', () => {
  it('renders site data, panel links and unreachable sites', async () => {
    api.get.mockResolvedValue({ data: { data: { fetched_at: '2026-10-23T10:00:00Z', sites: [
      { provision_id: 1, domain: 'shop.example.ir', ok: true, panel_url: 'https://shop.example.ir/admin', site: { orders: 12, revenue: 1000, open_tickets: 1 },
        matches: [{ user: { id: 5, name: 'Ali', email: 'ali@x.ir' }, kyc: { status: 'verified' }, orders: [{ id: 9, number: 'A-9', status: 'paid', total: 500 }], tickets: [] }] },
      { provision_id: 2, domain: 'down.example.ir', ok: false, error: 'timeout' },
    ] } } });
    wrap(<SitePanelTab />);
    expect(await screen.findByText('shop.example.ir')).toBeInTheDocument();
    expect(screen.getByText('Ali')).toBeInTheDocument();
    expect(screen.getByText('A-9')).toBeInTheDocument();
    expect(screen.getByText(en.crm.hub.sp.openPanel).closest('a')).toHaveAttribute('href', 'https://shop.example.ir/admin');
    expect(screen.getByTestId('site-error')).toHaveTextContent(en.crm.hub.sp.unreachable);
    expect(api.get).toHaveBeenCalledWith('/v1/crm/customers/7/hub/site_panel');
  });
});

describe('DocumentsTab', () => {
  it('shows a secure download button only for documents with a file', async () => {
    api.get.mockResolvedValue({ data: { data: [
      { id: 1, title: 'Contract', has_file: true, file_name: 'c.pdf', category: 'contract' },
      { id: 2, title: 'Link only', url: 'https://x', has_file: false },
    ], meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 } } });
    api.post.mockResolvedValue({ data: { data: { url: 'https://erp/s' } } });
    vi.spyOn(window, 'open').mockReturnValue(null);
    wrap(<DocumentsTab />);
    expect((await screen.findAllByText('Contract')).length).toBeGreaterThan(0);
    const btns = screen.getAllByLabelText(en.crm.hub.doc.download);
    expect(btns).toHaveLength(1);
    fireEvent.click(btns[0]);
    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/v1/crm/customers/7/hub/documents/1/download-link'));
  });
});
