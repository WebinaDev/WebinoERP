'use client';

import { useTranslations } from 'next-intl';
import AccCashAccounts from '@/components/dashboard/pages/accounting/AccCashAccounts';
import { FinanceSection } from '../components/FinanceSection';

export function CashAccountsPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('cashAccounts')}><AccCashAccounts /></FinanceSection>;
}
