'use client';

import { useTranslations } from 'next-intl';
import AccChartOfAccounts from '@/components/dashboard/pages/accounting/AccChartOfAccounts';
import { FinanceSection } from '../components/FinanceSection';

export function ChartOfAccountsPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('chart')}><AccChartOfAccounts /></FinanceSection>;
}
