'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Card, CardContent } from '@/components/ui/card';
import { deleteModirPayamakPackage, getModirPayamakPackages, saveModirPayamakPackage } from '@/lib/api/modirpayamak';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakPackagesPage() {
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ id: 0, name: '', amount: 0, bonus: 0, sort: 0, status: 'active' });

  const load = useCallback(async () => {
    try {
      const res = await getModirPayamakPackages();
      setRows(Array.isArray(res) ? res : []);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    if (configured) void load();
  }, [configured, load]);

  const save = async () => {
    try {
      await saveModirPayamakPackage(form);
      setOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpPackages')} actions={<Button onClick={() => setOpen(true)}>{tNav('common.add')}</Button>} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpPackages')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : (
        <Card>
          <CardContent className="pt-6">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Bonus</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell>{String(r.name ?? '')}</TableCell>
                    <TableCell>{String(r.amount ?? '')}</TableCell>
                    <TableCell>{String(r.bonus ?? '')}</TableCell>
                    <TableCell>
                      <Button variant="ghost" size="sm" onClick={() => void deleteModirPayamakPackage(Number(r.id)).then(load)}>{tNav('common.delete')}</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Package</DialogTitle></DialogHeader>
          <Input placeholder="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Input placeholder="amount" value={form.amount} onChange={(e) => setForm({ ...form, amount: Number(e.target.value) })} />
          <DialogFooter><Button onClick={() => void save()}>{tNav('common.save')}</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
