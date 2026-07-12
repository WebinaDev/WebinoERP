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
import { createBom, deleteBom, listBoms } from '@/lib/api/mfg';
import { normalizeListPayload } from '@/lib/list-utils';

export function MfgBomsPage() {
  const t = useTranslations('mfg');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [productId, setProductId] = useState('');
  const [componentId, setComponentId] = useState('');
  const [qty, setQty] = useState('1');

  const load = useCallback(async () => {
    try {
      const res = await listBoms();
      setRows(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const add = async () => {
    if (!productId || !componentId) return;
    try {
      await createBom({
        product_id: Number(productId),
        status: 'active',
        version: '1.0',
        lines: [{ component_product_id: Number(componentId), quantity: Number(qty || 1) }],
      });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.mfg.boms')} {...layoutProps}>
      <PermissionGate permission="mfg.boms.manage">
        <Card className="mb-4">
          <CardContent className="grid gap-3 pt-6 sm:grid-cols-4">
            <div><Label>{t('productId')}</Label><Input dir="ltr" value={productId} onChange={(e) => setProductId(e.target.value)} /></div>
            <div><Label>{t('componentId')}</Label><Input dir="ltr" value={componentId} onChange={(e) => setComponentId(e.target.value)} /></div>
            <div><Label>{t('quantity')}</Label><Input dir="ltr" value={qty} onChange={(e) => setQty(e.target.value)} /></div>
            <div className="flex items-end"><Button onClick={() => void add()}>{tNav('common.add')}</Button></div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>{t('productId')}</TableHead>
                  <TableHead>{t('version')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell>{String(r.id)}</TableCell>
                    <TableCell dir="ltr">{String(r.product_id)}</TableCell>
                    <TableCell>{String(r.version ?? '')}</TableCell>
                    <TableCell>{String(r.status ?? '')}</TableCell>
                    <TableCell>
                      <Button size="sm" variant="ghost" onClick={() => void deleteBom(Number(r.id)).then(load)}>
                        {tNav('common.delete')}
                      </Button>
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
