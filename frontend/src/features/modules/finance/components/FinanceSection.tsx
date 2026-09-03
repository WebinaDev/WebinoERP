'use client';

import type { ReactNode } from 'react';
import { AccountingPageLayout } from '@/features/shared/layout/AccountingPageLayout';
import { FiscalYearProvider } from './FiscalYearProvider';
import { FiscalYearSelect } from './FiscalYearSelect';

type Props = {
  title: string;
  description?: string;
  actions?: ReactNode;
  error?: string | null;
  success?: string | null;
  children: ReactNode;
};

export function FinanceSection({ title, description, actions, error, success, children }: Props) {
  return (
    <FiscalYearProvider>
      <AccountingPageLayout title={title} description={description} actions={actions} error={error} success={success}>
        <div className="mb-4"><FiscalYearSelect allowAll /></div>
        {children}
      </AccountingPageLayout>
    </FiscalYearProvider>
  );
}
