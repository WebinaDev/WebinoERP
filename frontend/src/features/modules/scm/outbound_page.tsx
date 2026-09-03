'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { createOutbound, postOutbound } from '@/lib/api/scm';
import { normalizeListPayload } from '@/lib/list-utils';

export function OutboundPage() {
  const t = useTranslations('scm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [reference, setReference] = useState('');
  const [warehouseId, setWarehouseId] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/scm/outbound');
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.scm.outbound')} actions={<Button onClick={() => void createOutbound({ reference, warehouse_id: Number(warehouseId), items: [] }).then(() => void load())}>{t('createOutbound')}</Button>} {...layoutProps}>
      <Card>
        <CardContent className="flex flex-wrap gap-2 pt-6">
          <Input placeholder={t('reference')} value={reference} onChange={(e) => setReference(e.target.value)} />
          <Input placeholder={t('warehouse')} value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)} />
        </CardContent>
      </Card>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>{t('reference')}</TableHead><TableHead>Status</TableHead><TableHead /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.reference ?? '')}</TableCell>
                  <TableCell>{String(r.status ?? '')}</TableCell>
                  <TableCell>
                    {String(r.status) !== 'posted' ? (
                      <Button size="sm" onClick={() => void postOutbound(r.id as number).then(() => { setSuccess(t('postOutbound')); void load(); })}>{t('postOutbound')}</Button>
                    ) : null}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
