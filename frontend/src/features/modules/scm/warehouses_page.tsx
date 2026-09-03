'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { listWarehouses, saveWarehouse, deleteWarehouse } from '@/lib/api/scm';
import { normalizeListPayload } from '@/lib/list-utils';

export function WarehousesPage() {
  const t = useTranslations('scm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [name, setName] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await listWarehouses();
      setRows(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout
      title={tNav('nav.erp.scm.warehouses')}
      actions={<Button onClick={() => void saveWarehouse(null, { name }).then(() => { setName(''); setSuccess(tNav('common.saved')); void load(); })}>{tNav('common.add')}</Button>}
      {...layoutProps}
    >
      <Card>
        <CardContent className="flex gap-2 pt-6">
          <Input placeholder={t('warehouse')} value={name} onChange={(e) => setName(e.target.value)} />
        </CardContent>
      </Card>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Code</TableHead><TableHead /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.name ?? '')}</TableCell>
                  <TableCell>{String(r.code ?? '')}</TableCell>
                  <TableCell><Button size="sm" variant="ghost" onClick={() => void deleteWarehouse(Number(r.id)).then(load)}>{tNav('common.delete')}</Button></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
