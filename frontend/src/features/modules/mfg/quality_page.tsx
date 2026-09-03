'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PermissionGate } from '@/components/auth/PermissionGate';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { completeInspection, createInspection, listInspections } from '@/lib/api/mfg';
import { normalizeListPayload } from '@/lib/list-utils';

export function MfgQualityPage() {
  const t = useTranslations('mfg');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [workOrderId, setWorkOrderId] = useState('');
  const [criterion, setCriterion] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await listInspections();
      setRows(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const add = async () => {
    if (!workOrderId || !criterion) return;
    try {
      await createInspection({
        work_order_id: Number(workOrderId),
        type: 'final',
        check_items: [{ criterion, spec_min: 0, spec_max: 9999, measured_value: '1' }],
      });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.mfg.quality')} {...layoutProps}>
      <PermissionGate permission="mfg.quality.manage">
        <Card className="mb-4">
          <CardContent className="grid gap-3 pt-6 sm:grid-cols-3">
            <div><Label>{t('workOrderId')}</Label><Input dir="ltr" value={workOrderId} onChange={(e) => setWorkOrderId(e.target.value)} /></div>
            <div><Label>{t('criterion')}</Label><Input value={criterion} onChange={(e) => setCriterion(e.target.value)} /></div>
            <div className="flex items-end"><Button onClick={() => void add()}>{tNav('common.add')}</Button></div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>{t('workOrderId')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{t('result')}</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell>{String(r.id)}</TableCell>
                    <TableCell dir="ltr">{String(r.work_order_id)}</TableCell>
                    <TableCell>{String(r.status)}</TableCell>
                    <TableCell>{String(r.result ?? '—')}</TableCell>
                    <TableCell>
                      {r.status === 'open' ? (
                        <Button size="sm" variant="outline" onClick={() => void completeInspection(Number(r.id)).then(load)}>
                          {t('completeInspection')}
                        </Button>
                      ) : null}
                    </TableCell>
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
