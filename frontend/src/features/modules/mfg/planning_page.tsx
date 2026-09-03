'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PermissionGate } from '@/components/auth/PermissionGate';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { mfgMrp } from '@/lib/api/mfg';

export function MfgPlanningPage() {
  const t = useTranslations('mfg');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [data, setData] = useState<Record<string, unknown> | null>(null);

  const load = useCallback(async () => {
    try {
      setData(await mfgMrp(30));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const shortages = (data?.shortages ?? []) as Record<string, unknown>[];

  return (
    <CrmPageLayout
      title={tNav('nav.erp.mfg.planning')}
      {...layoutProps}
      actions={<Button variant="outline" size="sm" onClick={() => void load()}>{tNav('common.refresh')}</Button>}
    >
      <PermissionGate permission="mfg.planning.view">
        <Card className="mb-4">
          <CardHeader><CardTitle className="text-base">{t('mrpSummary')}</CardTitle></CardHeader>
          <CardContent className="text-sm">
            <p>{t('openWorkOrders')}: {String(data?.open_work_orders ?? 0)}</p>
            <p>{t('activeBoms')}: {String(data?.active_boms ?? 0)}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('shortages')}</CardTitle></CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('productId')}</TableHead>
                  <TableHead>{t('required')}</TableHead>
                  <TableHead>{t('available')}</TableHead>
                  <TableHead>{t('shortage')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {shortages.length === 0 ? (
                  <TableRow><TableCell colSpan={4} className="text-center text-muted-foreground">{t('noShortages')}</TableCell></TableRow>
                ) : shortages.map((row, i) => (
                  <TableRow key={i}>
                    <TableCell dir="ltr">{String(row.product_id)}</TableCell>
                    <TableCell dir="ltr">{String(row.required)}</TableCell>
                    <TableCell dir="ltr">{String(row.available)}</TableCell>
                    <TableCell dir="ltr">{String(row.shortage)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </PermissionGate>
    </CrmPageLayout>
  );
}
