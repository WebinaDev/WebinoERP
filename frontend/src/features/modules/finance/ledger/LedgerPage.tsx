'use client';

import { useTranslations } from 'next-intl';
import AccLedger from '@/components/dashboard/pages/accounting/AccLedger';
import { FinanceSection } from '../components/FinanceSection';

export function LedgerPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('ledger')}><AccLedger /></FinanceSection>;
}
