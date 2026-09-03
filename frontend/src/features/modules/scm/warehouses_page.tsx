'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { listWarehouses, saveWarehouse, deleteWarehouse } from '@/lib/api/scm';
import { normalizeListPayload } from '@/lib/list-utils';

type Warehouse = {
  id: number;
  name: string;
  address?: string | null;
  is_default?: boolean;
  is_active?: boolean;
};

const BLANK = { name: '', address: '', is_default: false, is_active: true };

export function WarehousesPage() {
  const t = useTranslations('scm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Warehouse[]>([]);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Warehouse | null>(null);
  const [form, setForm] = useState({ ...BLANK });
  const [saving, setSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Warehouse | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await listWarehouses({ per_page: 100 });
      setRows(normalizeListPayload(res as { data?: unknown }) as unknown as Warehouse[]);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...BLANK });
    setFormOpen(true);
  };

  const openEdit = (row: Warehouse) => {
    setEditing(row);
    setForm({
      name: row.name ?? '',
      address: row.address ?? '',
      is_default: Boolean(row.is_default),
      is_active: row.is_active !== false,
    });
    setFormOpen(true);
  };

  const save = async () => {
    if (!form.name.trim()) return;
    setSaving(true);
    try {
      await saveWarehouse(editing?.id ?? null, form);
      setFormOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await deleteWarehouse(deleteTarget.id);
      setDeleteTarget(null);
      setSuccess(tNav('common.deleted'));
      void load();
    } catch (err) {
      applyAxiosError(err);
      setDeleteTarget(null);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.scm.warehouses')}
      actions={<Button onClick={openCreate}>{tNav('common.add')}</Button>}
      {...layoutProps}
    >
      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('name')}</TableHead>
              <TableHead>{t('address')}</TableHead>
              <TableHead>{t('default')}</TableHead>
              <TableHead>{t('active')}</TableHead>
              <TableHead>{tNav('common.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((r) => (
              <TableRow key={r.id}>
                <TableCell>{r.name}</TableCell>
                <TableCell className="max-w-[220px] truncate">{r.address ?? '—'}</TableCell>
                <TableCell>
                  <Badge variant={r.is_default ? 'default' : 'outline'}>
                    {r.is_default ? t('yes') : t('no')}
                  </Badge>
                </TableCell>
                <TableCell>
                  <Badge variant={r.is_active !== false ? 'default' : 'outline'}>
                    {r.is_active !== false ? t('active') : t('inactive')}
                  </Badge>
                </TableCell>
                <TableCell className="flex gap-1">
                  <Button size="sm" variant="ghost" onClick={() => openEdit(r)}>{tNav('common.edit')}</Button>
                  <Button size="sm" variant="ghost" className="text-destructive" onClick={() => setDeleteTarget(r)}>
                    {tNav('common.delete')}
                  </Button>
                </TableCell>
              </TableRow>
            ))}
            {rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-6 text-center text-muted-foreground">{tNav('common.noData')}</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? t('editWarehouse') : t('newWarehouse')}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3">
            <div>
              <label className="mb-1 block text-sm font-medium">{t('name')}</label>
              <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">{t('address')}</label>
              <Input value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
            </div>
            <div className="flex gap-4">
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.is_default}
                  onChange={(e) => setForm({ ...form, is_default: e.target.checked })}
                  className="h-4 w-4 rounded border-input"
                />
                {t('default')}
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                  className="h-4 w-4 rounded border-input"
                />
                {t('active')}
              </label>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void save()} disabled={saving || !form.name.trim()}>
              {saving ? t('saving') : tNav('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={(o) => { if (!o) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{tNav('common.delete')}</AlertDialogTitle>
            <AlertDialogDescription>
              {tNav('common.confirmDeleteNamed', { name: deleteTarget?.name })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tNav('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void handleDelete()}>{tNav('common.delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </CrmPageLayout>
  );
}
