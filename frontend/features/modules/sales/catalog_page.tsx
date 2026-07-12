'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';

export function CatalogPage() {
  const t = useTranslations('sales.catalog');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: '', sku: '', price: '0' });

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/sales/catalog');
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async () => {
    try {
      await apiClient.post('/v1/sales/catalog', { ...form, price: Number(form.price) });
      setOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.sales.catalog')} actions={<Button onClick={() => setOpen(true)}>{tNav('common.add')}</Button>} {...layoutProps}>
      <Card><CardContent className="pt-6">
        <Table>
          <TableHeader><TableRow><TableHead>{t('name')}</TableHead><TableHead>SKU</TableHead><TableHead>{t('price')}</TableHead></TableRow></TableHeader>
          <TableBody>{rows.map((r) => <TableRow key={String(r.id)}><TableCell>{String(r.name ?? '')}</TableCell><TableCell>{String(r.sku ?? '')}</TableCell><TableCell>{String(r.price ?? '')}</TableCell></TableRow>)}</TableBody>
        </Table>
      </CardContent></Card>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newItem')}</DialogTitle></DialogHeader>
          <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Input value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} />
          <DialogFooter><Button onClick={() => void save()}>{tNav('common.save')}</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
