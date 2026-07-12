'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmPagination } from '@/features/shared/pm';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { getModirPayamakOutbox, getModirPayamakOutboxDetail } from '@/lib/api/modirpayamak';
import { normalizeListPayload } from '@/lib/list-utils';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakReportsPage() {
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [detail, setDetail] = useState<Record<string, unknown> | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await getModirPayamakOutbox({ page });
      const body = res as { data?: unknown; meta?: { last_page?: number } };
      setRows(normalizeListPayload(body));
      setLastPage(body.meta?.last_page ?? 1);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [page, applyAxiosError]);

  useEffect(() => {
    if (configured) void load();
  }, [configured, load]);

  const openDetail = async (id: string | number) => {
    try {
      const d = await getModirPayamakOutboxDetail(id);
      setDetail(d as Record<string, unknown>);
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpReports')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpReports')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : (
        <Card>
          <CardContent className="pt-6">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>To</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={String(r.id)} className="cursor-pointer" onClick={() => void openDetail(r.id as string | number)}>
                    <TableCell>{String(r.id ?? '')}</TableCell>
                    <TableCell>{String(r.to ?? r.recipient ?? '')}</TableCell>
                    <TableCell>{String(r.status ?? '')}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <PmPagination page={page} lastPage={lastPage} onPage={setPage} />
          </CardContent>
        </Card>
      )}
      <Sheet open={!!detail} onOpenChange={(o) => !o && setDetail(null)}>
        <SheetContent className="overflow-y-auto sm:max-w-lg">
          <SheetHeader><SheetTitle>Detail</SheetTitle></SheetHeader>
          <pre className="mt-4 text-xs">{detail ? JSON.stringify(detail, null, 2) : ''}</pre>
        </SheetContent>
      </Sheet>
    </CrmPageLayout>
  );
}
