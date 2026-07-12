'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmPagination } from '@/features/shared/pm';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import { getModirPayamakOrders } from '@/lib/api/modirpayamak';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakOrdersPage() {
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [page, setPage] = useState(1);

  const load = useCallback(async () => {
    try {
      const res = await getModirPayamakOrders(page);
      setRows((res as { orders?: Record<string, unknown>[] })?.orders ?? []);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [page, applyAxiosError]);

  useEffect(() => {
    if (configured) void load();
  }, [configured, load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpOrders')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpOrders')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : (
        <Card>
          <CardContent className="pt-6">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>Domain</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell>{String(r.id ?? '')}</TableCell>
                    <TableCell>{String(r.domain ?? '')}</TableCell>
                    <TableCell>{String(r.amount ?? '')}</TableCell>
                    <TableCell>{String(r.status ?? '')}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <PmPagination page={page} lastPage={page + 1} onPage={setPage} />
          </CardContent>
        </Card>
      )}
    </CrmPageLayout>
  );
}
