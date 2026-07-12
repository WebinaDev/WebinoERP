'use client';

import { useTranslations } from 'next-intl';
import AccJournals from '@/components/dashboard/pages/accounting/AccJournals';
import { FinanceSection } from '../components/FinanceSection';

export function JournalsPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('journals')}><AccJournals /></FinanceSection>;
}
