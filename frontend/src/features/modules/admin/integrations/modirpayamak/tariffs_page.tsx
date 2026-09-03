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
  deleteModirPayamakTariff,
  getModirPayamakTariffs,
  saveModirPayamakTariff,
  type ModirPayamakTariff,
} from '@/lib/api/modirpayamak';
import { getAxiosMessage } from '@/lib/api-helpers';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';

function emptyForm() {
  return {
    line_type: '1000',
    operator: 'mci',
    rate_fa: '1936',
    rate_la: '4840',
    sort: '0',
    status: 'active',
  };
}

export function ModirpayamakTariffsPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const format = useFormatter();
  const { layoutProps, setError } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [tariffs, setTariffs] = useState<ModirPayamakTariff[]>([]);
  const [taxPercent, setTaxPercent] = useState(10);
  const [surcharge, setSurcharge] = useState(40);
  const [loading, setLoading] = useState(true);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm());

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getModirPayamakTariffs();
      setTariffs(Array.isArray(data.tariffs) ? data.tariffs : []);
      setTaxPercent(Number(data.tax_percent ?? 10));
      setSurcharge(Number(data.surcharge_rial ?? 40));
    } catch (e) {
      setTariffs([]);
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [setError]);

  useEffect(() => {
    if (configured) void load();
    else if (configured === false) setLoading(false);
  }, [configured, load]);

  const openCreate = () => {
    setEditId(null);
    setForm(emptyForm());
    setDialogOpen(true);
  };

  const openEdit = (row: ModirPayamakTariff) => {
    setEditId(row.id);
    setForm({
      line_type: row.line_type,
      operator: row.operator === 'mci' ? 'mci' : 'other',
      rate_fa: String(row.rate_fa),
      rate_la: String(row.rate_la),
      sort: String(row.sort ?? 0),
      status: row.status || 'active',
    });
    setDialogOpen(true);
  };

  const save = async () => {
    try {
      await saveModirPayamakTariff({
        id: editId ?? undefined,
        line_type: form.line_type.trim(),
        operator: form.operator,
        rate_fa: parseFloat(form.rate_fa) || 0,
        rate_la: parseFloat(form.rate_la) || 0,
        sort: parseInt(form.sort, 10) || 0,
        status: form.status,
      });
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
      await deleteModirPayamakTariff(deleteId);
      setDeleteId(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setDeleting(false);
    }
  };

  const billable = (rate: number) => rate * (1 + taxPercent / 100) + surcharge;

  return (
    <CrmPageLayout
      title={tNav('nav.erp.admin.mpTariffs')}
      {...layoutProps}
      actions={
        <Button size="sm" onClick={openCreate}>
          <Plus className="me-2 h-4 w-4" />
          {t('addTariff')}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpTariffs')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}
      <p className="mb-3 text-sm text-muted-foreground">
        {t('tariffsHint', { tax: String(taxPercent), surcharge: format.number(surcharge) })}
      </p>

      {loading ? (
        <Card>
          <CardContent className="h-40 animate-pulse bg-muted/30" />
        </Card>
      ) : tariffs.length === 0 ? (
        <PmEmptyState title={t('tariffsEmpty')} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('lineType')}</TableHead>
                  <TableHead>{t('operator')}</TableHead>
                  <TableHead>{t('rateFa')}</TableHead>
                  <TableHead>{t('rateLa')}</TableHead>
                  <TableHead>{t('billableFa')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead className="w-[100px]">{tCommon('actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {tariffs.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-mono">{row.line_type}</TableCell>
                    <TableCell>
                      {row.operator === 'mci' ? t('operatorMci') : t('operatorOther')}
                    </TableCell>
                    <TableCell dir="ltr">{format.number(Number(row.rate_fa))}</TableCell>
                    <TableCell dir="ltr">{format.number(Number(row.rate_la))}</TableCell>
                    <TableCell dir="ltr">{format.number(billable(Number(row.rate_fa)))}</TableCell>
                    <TableCell>
                      <ModirPayamakStatusBadge status={row.status} />
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(row)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="text-destructive"
                          onClick={() => setDeleteId(row.id)}
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
            <DialogTitle>{editId ? tCommon('edit') : t('addTariff')}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t('lineType')}</Label>
              <Input
                value={form.line_type}
                onChange={(e) => setForm((f) => ({ ...f, line_type: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('operator')}</Label>
              <Select value={form.operator} onValueChange={(v) => setForm((f) => ({ ...f, operator: v }))}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="mci">{t('operatorMci')}</SelectItem>
                  <SelectItem value="other">{t('operatorOther')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t('rateFa')}</Label>
              <Input
                dir="ltr"
                value={form.rate_fa}
                onChange={(e) => setForm((f) => ({ ...f, rate_fa: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('rateLa')}</Label>
              <Input
                dir="ltr"
                value={form.rate_la}
                onChange={(e) => setForm((f) => ({ ...f, rate_la: e.target.value }))}
              />
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
            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button type="button" onClick={() => void save()}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId != null}
        title={tCommon('delete')}
        description={t('confirmDeleteTariff')}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteId(null)}
        pending={deleting}
      />
    </CrmPageLayout>
  );
}
