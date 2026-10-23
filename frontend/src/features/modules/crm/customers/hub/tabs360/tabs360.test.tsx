import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { afterEach, describe, expect, it, vi } from 'vitest';
import en from '../../../../../../../messages/en.json';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }));
vi.mock('@/lib/api-client', () => ({ default: api }));
const hub = vi.hoisted(() => ({ id: '7', version: 0, canDo: () => true, open: vi.fn(), refresh: vi.fn(), setNotice: vi.fn() }));
vi.mock('../HubContext', () => ({ useHub: () => hub }));

import { ContractsTab, InstallmentsEditor } from './ContractsTab';
import { DocumentsTab360 } from './DocumentsTab360';
import { ProjectsTab } from './ProjectsTab';
import { FileDrop, body, fieldErrors } from './shared';

const T = en.crm.hub.t360;
const wrap = (ui: React.ReactNode) =>
  render(<NextIntlClientProvider locale="en" messages={en} timeZone="Asia/Tehran">{ui}</NextIntlClientProvider>);
const page = (data: unknown[], extra: Record<string, unknown> = {}) => ({ data: { data, meta: { current_page: 1, last_page: 1, per_page: 15, total: data.length, ...extra } } });

afterEach(() => vi.clearAllMocks());

describe('shared helpers', () => {
  it('body() drops empties and casts numeric keys; fieldErrors() flattens 422', () => {
    expect(body({ a: 'x', b: '', n: '5', f: new File(['x'], 'a.pdf') }, ['n'])).toEqual({ a: 'x', n: 5 });
    expect(fieldErrors({ response: { data: { errors: { 'installments.0.amount': ['bad'], title: ['req'] } } } })).toEqual({ installments: 'bad', title: 'req' });
  });

  it('FileDrop rejects oversize and disallowed files', () => {
    const onChange = vi.fn();
    wrap(<FileDrop value={null} onChange={onChange} maxBytes={10} />);
    const input = screen.getByTestId('file-input');
    fireEvent.change(input, { target: { files: [new File(['x'], 'a.exe')] } });
    expect(screen.getByText(T.badType)).toBeInTheDocument();
    fireEvent.change(input, { target: { files: [new File(['01234567890'], 'a.pdf')] } });
    expect(screen.getByText(T.tooLarge)).toBeInTheDocument();
    fireEvent.drop(screen.getByTestId('file-drop'), { dataTransfer: { files: [new File(['ok'], 'b.pdf')] } });
    expect(onChange).toHaveBeenCalledWith(expect.any(File));
  });
});

describe('DocumentsTab360', () => {
  it('lists documents, applies filters and hides upload without permission', async () => {
    api.get.mockResolvedValue(page([
      { id: 1, source: 'hub', title: 'National card', category: 'national_card', version: 2, has_file: true, expired: true, expires_at: '2026-01-01', review_status: 'pending', can: { update: true, delete: true } },
      { id: 'prjfile-3', source: 'projects', title: 'Brief', category: 'brief', has_file: true, url: 'https://x/f', can: {} },
    ], { can: { create: false } }));
    wrap(<DocumentsTab360 />);
    expect(await screen.findByText('National card')).toBeInTheDocument();
    expect(screen.getByText('v2')).toBeInTheDocument();
    expect(screen.getAllByText(en.crm.hub.doc.expired).length).toBeGreaterThan(0);
    expect(screen.queryByText(T.upload)).not.toBeInTheDocument();
    // only the hub row can get a new version
    expect(screen.getAllByLabelText(T.newVersion)).toHaveLength(1);
    fireEvent.change(screen.getByLabelText(en.crm.hub.doc.category), { target: { value: 'contract' } });
    await waitFor(() => expect(api.get).toHaveBeenLastCalledWith('/v1/crm/customers/7/hub/documents', { params: expect.objectContaining({ category: 'contract', page: 1 }) }));
  });

  it('uploads with multipart and shows server validation errors', async () => {
    api.get.mockResolvedValue(page([], { can: { create: true } }));
    api.post.mockRejectedValueOnce({ response: { status: 422, data: { message: 'invalid', errors: { title: ['Title taken'] } } } });
    wrap(<DocumentsTab360 />);
    fireEvent.click(await screen.findByText(T.upload));
    fireEvent.change(screen.getByLabelText(`${en.crm.hub.doc.title} *`), { target: { value: 'Passport' } });
    fireEvent.click(screen.getByRole('button', { name: en.crm.hub.save }));
    expect(await screen.findByTestId('err-title')).toHaveTextContent('Title taken');
    const [url, fd] = api.post.mock.calls[0];
    expect(url).toBe('/v1/crm/customers/7/hub/documents');
    expect(fd).toBeInstanceOf(FormData);
    expect((fd as FormData).get('title')).toBe('Passport');
    expect((fd as FormData).get('category')).toBe('other');
  });
});

describe('ContractsTab', () => {
  it('renders KPIs and the table; requests status filter', async () => {
    api.get.mockResolvedValue(page([
      { id: 5, source: 'projects', title: 'Hosting 1405', contract_no: 'C-1', status: 'active', amount: 1000, paid_total: 400, start_date: '2026-03-21', end_date: '2027-03-20', days_to_end: 20, can: {} },
    ], { kpis: { total: 3, active: 1, expiring_30: 1, active_amount: 1000, paid_total: 400 }, can: { create: true } }));
    wrap(<ContractsTab />);
    expect(await screen.findByText('Hosting 1405')).toBeInTheDocument();
    const kpis = screen.getByTestId('contract-kpis');
    expect(within(kpis).getByText(T.k.total).nextSibling).toHaveTextContent('3');
    fireEvent.change(screen.getByLabelText(T.status), { target: { value: 'expired' } });
    await waitFor(() => expect(api.get).toHaveBeenLastCalledWith('/v1/crm/customers/7/hub/contracts', { params: expect.objectContaining({ status: 'expired' }) }));
  });

  it('opens the detail sheet and invoices an installment', async () => {
    api.get.mockImplementation((url: string) => (url.endsWith('/contracts/5')
      ? Promise.resolve({ data: { data: { id: 5, source: 'projects', title: 'Hosting 1405', status: 'signed', amount: 1000,
        installments: [{ id: 9, title: 'First', due_date: '2026-11-01', amount: 500, status: 'due', invoice_id: null }], projects: [], invoices: [], documents: [],
        can: { update: true, sign: false, cancel: true, delete: false, renew: true, invoice: true } } } })
      : Promise.resolve(page([{ id: 5, source: 'projects', title: 'Hosting 1405', status: 'signed', amount: 1000, can: {} }], { kpis: {}, can: { create: true } }))));
    api.post.mockResolvedValue({ data: { data: {} } });
    wrap(<ContractsTab />);
    fireEvent.click(await screen.findByRole('button', { name: 'Hosting 1405' }));
    fireEvent.click(await screen.findByText(T.makeInvoice));
    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/v1/crm/customers/7/hub/contracts/5/installments/9/invoice'));
    expect(hub.refresh).toHaveBeenCalled();
    expect(screen.getByText(T.renew)).toBeInTheDocument();
    expect(screen.queryByText(T.sign)).not.toBeInTheDocument();
  });

  it('InstallmentsEditor warns when the sum exceeds the amount', () => {
    wrap(<InstallmentsEditor amount={100} onChange={() => undefined} rows={[{ title: '', due_date: '2026-11-01', amount: '60' }, { title: '', due_date: '2026-12-01', amount: '50' }]} />);
    expect(screen.getByTestId('inst-sum')).toHaveTextContent(T.instOver);
  });
});

describe('ProjectsTab', () => {
  it('renders cards with progress and status counts, and creates a project', async () => {
    api.get.mockImplementation((url: string) => Promise.resolve(url.endsWith('/contracts')
      ? page([{ id: 5, source: 'projects', title: 'Hosting 1405', status: 'active' }])
      : page([{ id: 2, name: 'Site redesign', status: 'active', progress_pct: 40, members: [{ id: 1 }], tasks: { done: 2, total: 5 }, overdue: true, due_date: '2026-10-01', contract: { id: 5, title: 'Hosting 1405' } }],
        { status_counts: { active: 1 }, can: { create: true } })));
    api.post.mockResolvedValue({ data: { data: { id: 3 } } });
    wrap(<ProjectsTab />);
    expect(await screen.findByText('Site redesign')).toBeInTheDocument();
    expect(screen.getAllByTestId('project-card')).toHaveLength(1);
    expect(screen.getByText('2 of 5 tasks')).toBeInTheDocument();
    expect(screen.getByText(T.overdue)).toBeInTheDocument();
    fireEvent.click(screen.getByText(T.newProject));
    fireEvent.change(screen.getByLabelText(`${T.projectName} *`), { target: { value: 'App' } });
    await waitFor(() => expect(screen.getByRole('option', { name: 'Hosting 1405' })).toBeInTheDocument());
    fireEvent.change(screen.getByLabelText(T.contract), { target: { value: '5' } });
    fireEvent.click(screen.getByRole('button', { name: en.crm.hub.save }));
    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/v1/crm/customers/7/hub/projects', { name: 'App', status: 'draft', contract_id: 5 }));
  });
});
