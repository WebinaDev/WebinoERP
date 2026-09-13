'use client';

import { useCallback, useEffect, useState } from 'react';
import { useFormatter, useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState, PmPagination } from '@/features/shared/pm';
import { Card, CardContent } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { getModirPayamakOrders, type ModirPayamakOrder } from '@/lib/api/modirpayamak';
import { getAxiosMessage } from '@/lib/api-helpers';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakOrdersPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const format = useFormatter();
  const { layoutProps, setError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [orders, setOrders] = useState<ModirPayamakOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const load = useCallback(async () => {
    if (!configured) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await getModirPayamakOrders(page);
      const list = res.orders ?? [];
      setOrders(list);
      const meta = (res as { meta?: { last_page?: number } }).meta;
      if (meta?.last_page && Number.isFinite(meta.last_page)) {
        setLastPage(Math.max(1, Number(meta.last_page)));
      } else {
        setLastPage(Math.max(1, page + (list.length >= 20 ? 1 : 0)));
      }
    } catch (e) {
      setOrders([]);
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [configured, page, setError]);

  useEffect(() => {
    void load();
  }, [load]);

  const formatDate = (raw?: string) => {
    if (!raw) return '—';
    try {
      return format.dateTime(new Date(raw), { dateStyle: 'short', timeStyle: 'short' });
    } catch {
      return raw;
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpOrders')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpOrders')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}

      {!configured ? null : loading ? (
        <Card>
          <CardContent className="h-40 animate-pulse bg-muted/30" />
        </Card>
      ) : orders.length === 0 ? (
        <PmEmptyState title={t('ordersEmpty')} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>{t('domain')}</TableHead>
                  <TableHead>{t('amount')}</TableHead>
                  <TableHead>{t('creditAmount')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{t('colDate')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {orders.map((o) => (
                  <TableRow key={o.id}>
                    <TableCell>{o.id}</TableCell>
                    <TableCell className="font-mono">{o.domain}</TableCell>
                    <TableCell>{format.number(o.amount)}</TableCell>
                    <TableCell>{format.number(o.credit_amount ?? 0)}</TableCell>
                    <TableCell>
                      <ModirPayamakStatusBadge status={o.status} />
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">{formatDate(o.created_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      {configured ? <PmPagination page={page} lastPage={lastPage} onPage={setPage} /> : null}
    </CrmPageLayout>
  );
}
