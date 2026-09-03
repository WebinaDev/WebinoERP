'use client';

import { useTranslations } from 'next-intl';
import AccFiscalYear from '@/components/dashboard/pages/accounting/AccFiscalYear';
import { FinanceSection } from '../components/FinanceSection';

export function FiscalYearPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('fiscalYear')}><AccFiscalYear /></FinanceSection>;
}
