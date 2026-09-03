'use client';

import { useTranslations } from 'next-intl';
import AccReports from '@/components/dashboard/pages/accounting/AccReports';
import { FinanceSection } from '../components/FinanceSection';

export function AccountingReportsPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('reports')}><AccReports /></FinanceSection>;
}
