import { fireEvent, render, screen } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { describe, expect, it, vi } from 'vitest';
import en from '../../../../../messages/en.json';

vi.mock('@/features/modules/crm/ImportCsvDialog', () => ({ ImportCsvDialog: ({ open }: { open: boolean }) => (open ? <div>import-dialog</div> : null) }));
import { Customer360DataPage, Customer360PortalPage, Customer360ReportsPage } from './Customer360Pages';
import { mergeUrl } from './phase5-api';

const C = en.crm.hub.c360;
const wrap = (ui: React.ReactNode) => render(<NextIntlClientProvider locale="en" messages={en} timeZone="Asia/Tehran">{ui}</NextIntlClientProvider>);

describe('Customer 360 phase 5 skeleton', () => {
  it('portal lists all sections and switches', () => {
    wrap(<Customer360PortalPage />);
    for (const k of ['profile', 'contracts', 'invoices', 'services', 'documents', 'tickets'] as const) expect(screen.getByRole('tab', { name: C[k] })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('tab', { name: C.invoices }));
    expect(screen.getByRole('tab', { name: C.invoices })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByTestId('contract-pending')).toBeInTheDocument();
  });
  it('reports lists BI sections', () => {
    wrap(<Customer360ReportsPage />);
    for (const k of ['ltv', 'debt', 'churn', 'cohorts', 'top'] as const) expect(screen.getByRole('tab', { name: C[k] })).toBeInTheDocument();
  });
  it('data page opens existing import dialog', () => {
    wrap(<Customer360DataPage />);
    expect(screen.getAllByTestId('contract-pending')).toHaveLength(2);
    fireEvent.click(screen.getByText(C.import));
    expect(screen.getByText('import-dialog')).toBeInTheDocument();
    expect(mergeUrl(5)).toBe('/v1/crm/customers/5/hub/merge');
  });
});
