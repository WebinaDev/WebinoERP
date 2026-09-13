'use client';

import { useCallback, useEffect, useState } from 'react';
import { useFormatter, useTranslations } from 'next-intl';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmConfirmDialog, PmEmptyState } from '@/features/shared/pm';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  deleteModirPayamakPackage,
  getModirPayamakPackages,
  getModirPayamakTariffs,
  saveModirPayamakPackage,
  type ModirPayamakPackage,
  type ModirPayamakTariff,
} from '@/lib/api/modirpayamak';
import { getAxiosMessage } from '@/lib/api-helpers';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

export function ModirpayamakPackagesPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const format = useFormatter();
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [packages, setPackages] = useState<ModirPayamakPackage[]>([]);
  const [tariffs, setTariffs] = useState<ModirPayamakTariff[]>([]);
  const [taxPercent, setTaxPercent] = useState(10);
  const [surcharge, setSurcharge] = useState(40);
  const [tariffId, setTariffId] = useState('');
  const [tariffParts, setTariffParts] = useState('1');
  const [tariffEncoding, setTariffEncoding] = useState<'fa' | 'la'>('fa');
  const [loading, setLoading] = useState(true);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState({ name: '', amount: '0', bonus: '0', sort: '0', status: 'active' });

  const load = useCallback(async () => {
    if (!configured) {
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [pkgRes, tarRes] = await Promise.all([getModirPayamakPackages(), getModirPayamakTariffs()]);
      setPackages(pkgRes.packages ?? []);
      setTariffs(tarRes.tariffs ?? []);
      setTaxPercent(Number(tarRes.tax_percent ?? 10));
      setSurcharge(Number(tarRes.surcharge_rial ?? 40));
    } catch (e) {
      setPackages([]);
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [configured, setError]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditId(null);
    setForm({ name: '', amount: '0', bonus: '0', sort: '0', status: 'active' });
    setTariffId('');
    setTariffParts('1');
    setTariffEncoding('fa');
    setDialogOpen(true);
  };

  const applyTariffAmount = () => {
    const row = tariffs.find((x) => String(x.id) === tariffId);
    if (!row) return;
    const parts = Math.max(1, parseInt(tariffParts, 10) || 1);
    const rate = tariffEncoding === 'la' ? Number(row.rate_la) : Number(row.rate_fa);
    const rial = (rate * (1 + taxPercent / 100) + surcharge) * parts;
    const toman = Math.round((rial / 10) * 10000) / 10000;
    setForm((f) => ({ ...f, amount: String(toman) }));
  };

  const openEdit = (p: ModirPayamakPackage) => {
    setEditId(p.id);
    setForm({
      name: p.name,
      amount: String(p.amount),
      bonus: String(p.bonus),
      sort: String(p.sort),
      status: p.status,
    });
    setDialogOpen(true);
  };

  const save = async () => {
    setError(null);
    try {
      await saveModirPayamakPackage({
        id: editId ?? undefined,
        name: form.name,
        amount: parseFloat(form.amount) || 0,
        bonus: parseFloat(form.bonus) || 0,
        sort: parseInt(form.sort, 10) || 0,
        status: form.status,
      });
      setSuccess(tCommon('saved'));
      setDialogOpen(false);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const confirmDelete = async () => {
    if (deleteId == null) return;
    setDeleting(true);
    try {
      await deleteModirPayamakPackage(deleteId);
      setSuccess(tCommon('deleted'));
      setDeleteId(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setDeleting(false);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.admin.mpPackages')}
      {...layoutProps}
      actions={
        <Button size="sm" onClick={openCreate} disabled={!configured}>
          <Plus className="me-2 h-4 w-4" />
          {t('addPackage')}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpPackages')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}

      {!configured ? null : loading ? (
        <Card>
          <CardContent className="h-40 animate-pulse bg-muted/30" />
        </Card>
      ) : packages.length === 0 ? (
        <PmEmptyState title={t('packagesEmpty')} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('name')}</TableHead>
                  <TableHead>{t('amount')}</TableHead>
                  <TableHead>{t('bonus')}</TableHead>
                  <TableHead>{t('sort')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead className="w-[100px]">{tCommon('actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {packages.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell>{p.name}</TableCell>
                    <TableCell>{format.number(p.amount)}</TableCell>
                    <TableCell>{format.number(p.bonus)}</TableCell>
                    <TableCell>{p.sort}</TableCell>
                    <TableCell>
                      <ModirPayamakStatusBadge status={p.status} />
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(p)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="text-destructive"
                          onClick={() => setDeleteId(p.id)}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editId ? tCommon('edit') : t('addPackage')}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2 sm:col-span-2">
              <Label>{t('name')}</Label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div className="space-y-2 rounded-md border border-dashed p-3 sm:col-span-2">
              <Label className="mb-2 block">{t('useTariffForAmount')}</Label>
              <div className="grid gap-2 sm:grid-cols-2">
                <Select value={tariffId || undefined} onValueChange={setTariffId}>
                  <SelectTrigger>
                    <SelectValue placeholder={tNav('nav.erp.admin.mpTariffs')} />
                  </SelectTrigger>
                  <SelectContent>
                    {tariffs.map((tr) => (
                      <SelectItem key={tr.id} value={String(tr.id)}>
                        {tr.line_type} /{' '}
                        {tr.operator === 'mci' ? t('operatorMci') : t('operatorOther')} (
                        {format.number(tr.rate_fa)} / {format.number(tr.rate_la)})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select value={tariffEncoding} onValueChange={(v) => setTariffEncoding(v as 'fa' | 'la')}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="fa">{t('encodingFa')}</SelectItem>
                    <SelectItem value="la">{t('encodingLa')}</SelectItem>
                  </SelectContent>
                </Select>
                <div className="space-y-1">
                  <Label className="text-xs">{t('tariffParts')}</Label>
                  <Input value={tariffParts} onChange={(e) => setTariffParts(e.target.value)} dir="ltr" />
                </div>
                <div className="flex items-end">
                  <Button type="button" variant="secondary" className="w-full" onClick={applyTariffAmount}>
                    {t('applyTariffAmount')}
                  </Button>
                </div>
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t('amount')}</Label>
              <Input value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t('bonus')}</Label>
              <Input value={form.bonus} onChange={(e) => setForm((f) => ({ ...f, bonus: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t('sort')}</Label>
              <Input value={form.sort} onChange={(e) => setForm((f) => ({ ...f, sort: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t('status')}</Label>
              <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">{t('statusActive')}</SelectItem>
                  <SelectItem value="inactive">{t('statusInactive')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button onClick={() => void save()}>{tCommon('save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId != null}
        title={tCommon('delete')}
        description={t('confirmDeletePackage')}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteId(null)}
        pending={deleting}
      />
    </CrmPageLayout>
  );
}
