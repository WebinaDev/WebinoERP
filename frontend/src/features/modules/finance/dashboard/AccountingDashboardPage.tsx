'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { accountingFiscalYears, accountingJournalList } from '@/lib/api/accounting';
import { dashboardHref } from '@/lib/route-resolver';
import { FinanceSection } from '../components/FinanceSection';

const LINKS = [
  'persons', 'products', 'invoices', 'cash-accounts', 'receipts', 'checks',
  'chart', 'journals', 'ledger', 'reports', 'fiscal-year', 'settings',
] as const;

export function AccountingDashboardPage() {
  const t = useTranslations('nav.erp.finance');
  const tFin = useTranslations('finance');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const [fyCount, setFyCount] = useState(0);
  const [journalTotal, setJournalTotal] = useState(0);

  useEffect(() => {
    void accountingFiscalYears().then((r) => setFyCount(r.data?.items?.length ?? 0));
    void accountingJournalList({ per_page: 1 }).then((r) => setJournalTotal(r.data?.total ?? 0));
  }, []);

  return (
    <FinanceSection title={t('dashboard')} description={tFin('dashboardDescription')}>
      <div className="mb-6 grid gap-4 md:grid-cols-2">
        <Card><CardHeader><CardTitle className="text-base">{t('fiscalYear')}</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{fyCount}</CardContent></Card>
        <Card><CardHeader><CardTitle className="text-base">{tFin('recentJournals')}</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{journalTotal}</CardContent></Card>
      </div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {LINKS.map((seg) => (
          <Link key={seg} href={dashboardHref(locale, `finance/${seg}`)} className="rounded-lg border p-4 transition-colors hover:bg-muted/50">
            <p className="font-medium">{t(seg === 'cash-accounts' ? 'cashAccounts' : seg === 'fiscal-year' ? 'fiscalYear' : seg)}</p>
          </Link>
        ))}
      </div>
    </FinanceSection>
  );
}
