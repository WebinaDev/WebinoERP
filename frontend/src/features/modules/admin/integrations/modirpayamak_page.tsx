'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getModirPayamakDashboard, getModirPayamakAccount } from '@/lib/api/modirpayamak';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured, ModirPayamakQuickLinks } from './modirpayamak/components/shared';
import { useModirPayamakConfigured } from './modirpayamak/hooks/useModirPayamakConfigured';

export function ModirpayamakPage() {
  const t = useTranslations();
  const { layoutProps } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [stats, setStats] = useState<Record<string, unknown>>({});
  const [account, setAccount] = useState<Record<string, unknown> | null>(null);

  useEffect(() => {
    if (!configured) return;
    void getModirPayamakDashboard().then((d) => setStats(d as Record<string, unknown>));
    void getModirPayamakAccount().then((d) => setAccount(d as Record<string, unknown>)).catch(() => {});
  }, [configured]);

  return (
    <CrmPageLayout title={t('nav.erp.sales.modirpayamak')} {...layoutProps}>
      <ModirPayamakBreadcrumb />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : (
        <>
          <div className="grid gap-4 md:grid-cols-3">
            <Card>
              <CardHeader><CardTitle className="text-base">{t('modirpayamak.creditBalance')}</CardTitle></CardHeader>
              <CardContent className="text-2xl font-semibold">{String(account?.balance ?? stats.reseller_credit ?? '—')}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">{t('nav.erp.admin.mpCustomers')}</CardTitle></CardHeader>
              <CardContent className="text-2xl font-semibold">{String(stats.accounts ?? stats.total_customers ?? 0)}</CardContent>
            </Card>
            <Card>
              <CardHeader><CardTitle className="text-base">{t('nav.erp.admin.mpOrders')}</CardTitle></CardHeader>
              <CardContent className="text-2xl font-semibold">{String(stats.orders_pending ?? stats.pending_orders ?? 0)}</CardContent>
            </Card>
          </div>
          <ModirPayamakQuickLinks />
        </>
      )}
    </CrmPageLayout>
  );
}
