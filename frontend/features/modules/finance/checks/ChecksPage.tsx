'use client';

import { useTranslations } from 'next-intl';
import AccChecks from '@/components/dashboard/pages/accounting/AccChecks';
import { FinanceSection } from '../components/FinanceSection';

export function ChecksPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('checks')}><AccChecks /></FinanceSection>;
}
