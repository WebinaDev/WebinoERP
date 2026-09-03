'use client';

import { useTranslations } from 'next-intl';
import AccInvoices from '@/components/dashboard/pages/accounting/AccInvoices';
import { FinanceSection } from '../components/FinanceSection';

export function FinanceInvoicesPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('invoices')}><AccInvoices /></FinanceSection>;
}
