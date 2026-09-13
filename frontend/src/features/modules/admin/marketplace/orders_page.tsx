'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { ShoppingCart } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { getMarketplaceOrders, type MarketplaceOrder } from '@/lib/api/marketplace';
import { useLocale } from '@/hooks/use-locale-next';
import { PmEmptyState } from './components/PmEmptyState';

export function OrdersPage() {
  const t = useTranslations('marketplace');
  const { formatDateTime, formatNumber } = useLocale();
  const { layoutProps, setError, applyAxiosError } = useCrmFeedback();
  const [orders, setOrders] = useState<MarketplaceOrder[]>([]);
  const [entitlements, setEntitlements] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getMarketplaceOrders();
      setOrders(res.orders ?? []);
      setEntitlements(res.entitlements ?? []);
    } catch (err) {
      applyAxiosError(err, t('loadError'));
      setOrders([]);
      setEntitlements([]);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, setError, t]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={t('ordersTitle')} description={t('ordersDesc')} {...layoutProps}>
      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="bg-muted/40 h-16 animate-pulse rounded-xl" />
          ))}
        </div>
      ) : (
        <>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('ordersSection')}</CardTitle>
            </CardHeader>
            <CardContent>
              {orders.length === 0 ? (
                <PmEmptyState icon={ShoppingCart} message={t('ordersEmpty')} />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('name')}</TableHead>
                      <TableHead>{t('colDomain')}</TableHead>
                      <TableHead>{t('releaseStatus')}</TableHead>
                      <TableHead>{t('colAmount')}</TableHead>
                      <TableHead>{t('colDate')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {orders.map((o) => (
                      <TableRow key={o.id}>
                        <TableCell className="font-medium">
                          {o.module_name ?? o.module_slug ?? o.order_number ?? o.id}
                        </TableCell>
                        <TableCell>
                          {o.domain ??
                            (o.site_provision_id != null ? `site#${o.site_provision_id}` : '—')}
                        </TableCell>
                        <TableCell>{o.status}</TableCell>
                        <TableCell>{formatNumber(Number(o.amount ?? o.total ?? 0))}</TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {o.created_at ? formatDateTime(o.created_at) : '—'}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('entitlementsSection')}</CardTitle>
            </CardHeader>
            <CardContent>
              {entitlements.length === 0 ? (
                <PmEmptyState icon={ShoppingCart} message={t('entitlementsEmpty')} />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('name')}</TableHead>
                      <TableHead>{t('colDomain')}</TableHead>
                      <TableHead>{t('releaseStatus')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {entitlements.map((e, i) => (
                      <TableRow key={i}>
                        <TableCell className="font-medium">
                          {(e.module_name as string) ?? (e.module_slug as string) ?? '—'}
                        </TableCell>
                        <TableCell>{(e.domain as string) ?? '—'}</TableCell>
                        <TableCell>{(e.status as string) ?? '—'}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </CrmPageLayout>
  );
}
