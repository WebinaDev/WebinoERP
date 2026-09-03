'use client';

import { useTranslations } from 'next-intl';
import AccProducts from '@/components/dashboard/pages/accounting/AccProducts';
import { FinanceSection } from '../components/FinanceSection';

export function FinanceProductsPage() {
  const t = useTranslations('nav.erp.finance');
  return <FinanceSection title={t('products')}><AccProducts /></FinanceSection>;
}
