'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useFormatter, useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getModirPayamakDashboard, type ModirPayamakStats } from '@/lib/api/modirpayamak';
import { getAxiosMessage } from '@/lib/api-helpers';
import { dashboardHref } from '@/lib/route-resolver';
import { ModirPayamakQuickLinks } from './components/shared';

function formatCredit(
  credit: unknown,
  formatNumber: (n: number) => string,
): { balance: string; expiry: string } {
  let src: unknown = credit;
  if (typeof src === 'string') {
    try {
      src = JSON.parse(src) as unknown;
    } catch {
      const raw = typeof credit === 'string' ? credit.trim() : '';
      return { balance: raw || '—', expiry: '—' };
    }
  }
  if (Array.isArray(src) && src[0]) src = src[0];
  if (!src || typeof src !== 'object') {
    if (typeof credit === 'number') return { balance: formatNumber(credit), expiry: '—' };
    return { balance: '—', expiry: '—' };
  }
  const c = src as Record<string, unknown>;
  const nested =
    c.data && typeof c.data === 'object' && !Array.isArray(c.data)
      ? (c.data as Record<string, unknown>)
      : c;
  const balance = nested.balance ?? nested.credit ?? nested.amount ?? nested.remaining ?? nested.reseller_credit;
  const expiry = nested.expire_at ?? nested.expires_at ?? nested.expiry ?? nested.expire;
  const balanceNum =
    typeof balance === 'string' ? Number(balance.replace(/,/g, '')) : Number(balance as number);
  return {
    balance: Number.isFinite(balanceNum) ? formatNumber(balanceNum) : balance != null ? String(balance) : '—',
    expiry: expiry != null ? String(expiry) : '—',
  };
}

export function ModirpayamakDashboardPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const format = useFormatter();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setError } = useCrmFeedback();
  const [stats, setStats] = useState<ModirPayamakStats | null>(null);
  const [loading, setLoading] = useState(true);

  const formatNumber = useCallback(
    (n: number) => format.number(n),
    [format],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getModirPayamakDashboard();
      const next = data.stats ?? {
        total_customers: data.accounts ?? 0,
        sent_today: 0,
        pending_orders: data.orders_pending ?? 0,
        reseller_credit: null,
        price_per_unit: 0,
        configured: Boolean(data.configured),
      };
      setStats(next);
    } catch (e) {
      setStats(null);
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [setError]);

  useEffect(() => {
    void load();
  }, [load]);

  const settingsHref = dashboardHref(locale, 'admin/integrations/modirpayamak/settings');
  const settingsButton = (
    <Button variant="outline" asChild>
      <Link href={settingsHref}>{tNav('nav.erp.admin.mpSettings')}</Link>
    </Button>
  );

  const credit = formatCredit(stats?.reseller_credit, formatNumber);

  return (
    <CrmPageLayout
      title={t('dashboardTitle')}
      description={t('dashboardDesc')}
      actions={settingsButton}
      {...layoutProps}
    >
      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Card key={i}>
              <CardContent className="h-24 animate-pulse bg-muted/40" />
            </Card>
          ))}
        </div>
      ) : (
        <>
          {!stats?.configured ? (
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
              <span>{t('notConfigured')}</span>
              <Button variant="outline" size="sm" asChild>
                <Link href={settingsHref}>{t('configureCta')}</Link>
              </Button>
            </div>
          ) : null}

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-sm font-medium">{t('customers')}</CardTitle>
              </CardHeader>
              <CardContent className="text-2xl font-bold">{formatNumber(stats?.total_customers ?? 0)}</CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle className="text-sm font-medium">{t('sentToday')}</CardTitle>
              </CardHeader>
              <CardContent className="text-2xl font-bold">{formatNumber(stats?.sent_today ?? 0)}</CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle className="text-sm font-medium">{t('pendingOrders')}</CardTitle>
              </CardHeader>
              <CardContent className="text-2xl font-bold">{formatNumber(stats?.pending_orders ?? 0)}</CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle className="text-sm font-medium">{t('pricePerUnit')}</CardTitle>
              </CardHeader>
              <CardContent className="text-2xl font-bold">{formatNumber(stats?.price_per_unit ?? 0)}</CardContent>
            </Card>
          </div>

          {stats?.reseller_credit ? (
            <Card>
              <CardHeader>
                <CardTitle>{t('resellerCredit')}</CardTitle>
              </CardHeader>
              <CardContent className="grid gap-4 sm:grid-cols-2">
                <div>
                  <p className="text-sm text-muted-foreground">{t('balance')}</p>
                  <p className="text-2xl font-semibold">{credit.balance}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">{t('creditExpiry')}</p>
                  <p className="text-lg font-medium">{credit.expiry}</p>
                </div>
              </CardContent>
            </Card>
          ) : null}

          <ModirPayamakQuickLinks />
        </>
      )}
    </CrmPageLayout>
  );
}
