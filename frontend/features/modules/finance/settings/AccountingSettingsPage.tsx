'use client';

import { useTranslations } from 'next-intl';
import AccSettings from '@/components/dashboard/pages/accounting/AccSettings';
import { FinanceSection } from '../components/FinanceSection';

export function AccountingSettingsPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('settings')}><AccSettings /></FinanceSection>;
}
