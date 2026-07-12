'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import { getModirPayamakNumbers } from '@/lib/api/modirpayamak';
import { normalizeListPayload } from '@/lib/list-utils';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakNumbersPage() {
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await getModirPayamakNumbers();
      setRows(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    if (configured) void load();
  }, [configured, load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpNumbers')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpNumbers')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : (
        <Card><CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>Number</TableHead><TableHead>Type</TableHead></TableRow></TableHeader>
            <TableBody>
              {rows.map((r, i) => (
                <TableRow key={String(r.id ?? i)}>
                  <TableCell>{String(r.number ?? r.line ?? '')}</TableCell>
                  <TableCell>{String(r.type ?? '')}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent></Card>
      )}
    </CrmPageLayout>
  );
}
