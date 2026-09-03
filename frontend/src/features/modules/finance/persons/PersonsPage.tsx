'use client';

import { useTranslations } from 'next-intl';
import AccPersons from '@/components/dashboard/pages/accounting/AccPersons';
import { FinanceSection } from '../components/FinanceSection';

export function PersonsPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('persons')}><AccPersons /></FinanceSection>;
}
