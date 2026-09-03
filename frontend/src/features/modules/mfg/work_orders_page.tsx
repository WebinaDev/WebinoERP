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
import { createWorkOrder, listWorkOrders, workOrderAction } from '@/lib/api/mfg';
import { normalizeListPayload } from '@/lib/list-utils';

export function MfgWorkOrdersPage() {
  const t = useTranslations('mfg');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [productId, setProductId] = useState('');
  const [bomId, setBomId] = useState('');
  const [qty, setQty] = useState('1');

  const load = useCallback(async () => {
    try {
      const res = await listWorkOrders();
      setRows(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const create = async () => {
    if (!productId) return;
    try {
      await createWorkOrder({
        product_id: Number(productId),
        bom_id: bomId ? Number(bomId) : undefined,
        qty_planned: Number(qty || 1),
      });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const act = async (id: number, action: 'release' | 'start' | 'complete' | 'cancel') => {
    try {
      await workOrderAction(id, action, action === 'complete' ? { qty_produced: undefined } : undefined);
      setSuccess(t('actionDone', { action }));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.mfg.workOrders')} {...layoutProps}>
      <PermissionGate permission="mfg.work_orders.manage">
        <Card className="mb-4">
          <CardContent className="grid gap-3 pt-6 sm:grid-cols-4">
            <div><Label>{t('productId')}</Label><Input dir="ltr" value={productId} onChange={(e) => setProductId(e.target.value)} /></div>
            <div><Label>{t('bomId')}</Label><Input dir="ltr" value={bomId} onChange={(e) => setBomId(e.target.value)} /></div>
            <div><Label>{t('quantity')}</Label><Input dir="ltr" value={qty} onChange={(e) => setQty(e.target.value)} /></div>
            <div className="flex items-end"><Button onClick={() => void create()}>{tNav('common.add')}</Button></div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>{t('productId')}</TableHead>
                  <TableHead>{t('quantity')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{tNav('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell>{String(r.id)}</TableCell>
                    <TableCell dir="ltr">{String(r.product_id)}</TableCell>
                    <TableCell dir="ltr">{String(r.qty_planned)}</TableCell>
                    <TableCell>{String(r.status)}</TableCell>
                    <TableCell className="flex flex-wrap gap-1">
                      {r.status === 'draft' ? <Button size="sm" variant="outline" onClick={() => void act(Number(r.id), 'release')}>{t('release')}</Button> : null}
                      {r.status === 'released' ? <Button size="sm" variant="outline" onClick={() => void act(Number(r.id), 'start')}>{t('start')}</Button> : null}
                      {r.status === 'in_progress' ? <Button size="sm" variant="outline" onClick={() => void act(Number(r.id), 'complete')}>{t('complete')}</Button> : null}
                      {r.status !== 'completed' && r.status !== 'cancelled' ? (
                        <Button size="sm" variant="ghost" onClick={() => void act(Number(r.id), 'cancel')}>{t('cancel')}</Button>
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
