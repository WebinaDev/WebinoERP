'use client';

import { useTranslations } from 'next-intl';
import AccReceipts from '@/components/dashboard/pages/accounting/AccReceipts';
import { FinanceSection } from '../components/FinanceSection';

export function ReceiptsPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('receipts')}><AccReceipts /></FinanceSection>;
}
