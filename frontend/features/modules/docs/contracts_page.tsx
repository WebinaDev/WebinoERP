'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { dashboardHref } from '@/lib/route-resolver';
import { normalizeListPayload } from '@/lib/list-utils';

export function ContractsPage() {
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/docs/contracts');
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const cancel = async (id: number) => {
    try {
      await apiClient.post(`/v1/docs/contracts/${id}/cancel`);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.docs.contracts')} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>Title</TableHead><TableHead>Status</TableHead><TableHead /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>
                    <Link href={dashboardHref(locale, `docs/contracts/${r.id}`)} className="underline">{String(r.title ?? r.name ?? r.id)}</Link>
                  </TableCell>
                  <TableCell>{String(r.status ?? '')}</TableCell>
                  <TableCell><Button size="sm" variant="outline" onClick={() => void cancel(Number(r.id))}>Cancel</Button></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
