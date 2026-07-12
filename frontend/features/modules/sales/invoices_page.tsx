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

export function InvoicesPage() {
  const t = useTranslations('sales.invoices');
  const tHrm = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ number: '', customer_name: '', total: '0', status: 'draft' });

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/sales/invoices');
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
      await apiClient.post('/v1/sales/invoices', { ...form, total: Number(form.total) });
      setOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const pdf = async (id: number) => {
    try {
      await apiClient.post(`/v1/sales/invoices/${id}/pdf`);
      setSuccess('PDF generated');
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const email = async (id: number) => {
    try {
      await apiClient.post(`/v1/sales/invoices/${id}/email`, { email: '' });
      setSuccess('Email queued');
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.sales.invoices')} actions={<Button onClick={() => setOpen(true)}>{tNav('common.add')}</Button>} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>#</TableHead><TableHead>{t('customer')}</TableHead><TableHead>{t('total')}</TableHead><TableHead>{tHrm('status')}</TableHead><TableHead /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.number ?? r.id)}</TableCell>
                  <TableCell>{String(r.customer_name ?? '')}</TableCell>
                  <TableCell>{String(r.total ?? '')}</TableCell>
                  <TableCell>{String(r.status ?? '')}</TableCell>
                  <TableCell className="flex gap-2">
                    <Button size="sm" variant="outline" onClick={() => void pdf(Number(r.id))}>PDF</Button>
                    <Button size="sm" variant="outline" onClick={() => void email(Number(r.id))}>Email</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newInvoice')}</DialogTitle></DialogHeader>
          <Input placeholder="#" value={form.number} onChange={(e) => setForm({ ...form, number: e.target.value })} />
          <Input placeholder={t('customer')} value={form.customer_name} onChange={(e) => setForm({ ...form, customer_name: e.target.value })} />
          <DialogFooter><Button onClick={() => void save()}>{tNav('common.save')}</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
