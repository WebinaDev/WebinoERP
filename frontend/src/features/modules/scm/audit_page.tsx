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
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import apiClient from '@/lib/api-client';
import { completeAudit, createAudit, listWarehouses, postAudit } from '@/lib/api/scm';
import { normalizeListPayload } from '@/lib/list-utils';
import { Plus, Trash2 } from 'lucide-react';

type Warehouse = { id: number; name: string };
type Product = { id: number; name: string };
type AuditItem = { product_id: string; counted: string };
type WarehouseDoc = {
  id: number;
  warehouse?: { name?: string };
  warehouse_id?: number;
  status: string;
  created_at?: string;
  items?: Record<string, unknown>[];
};

const EMPTY: AuditItem = { product_id: '', counted: '0' };

export function AuditPage() {
  const t = useTranslations('scm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [rows, setRows] = useState<WarehouseDoc[]>([]);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [step, setStep] = useState<1 | 2>(1);
  const [saving, setSaving] = useState(false);
  const [formWarehouseId, setFormWarehouseId] = useState('');
  const [formItems, setFormItems] = useState<AuditItem[]>([{ ...EMPTY }]);

  const loadLookups = useCallback(async () => {
    try {
      const [wRes, pRes] = await Promise.all([
        listWarehouses({ per_page: 200 }),
        apiClient.get('/v1/accounting/products', { params: { per_page: 500 } }),
      ]);
      setWarehouses(normalizeListPayload(wRes as { data?: unknown }) as unknown as Warehouse[]);
      setProducts(normalizeListPayload(pRes.data) as unknown as Product[]);
    } catch {
      /* ignore */
    }
  }, []);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/scm/audit', { params: { per_page: 50 } });
      setRows(normalizeListPayload(res.data) as unknown as WarehouseDoc[]);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => { void loadLookups(); }, [loadLookups]);
  useEffect(() => { void load(); }, [load]);

  const openCreate = () => {
    setFormWarehouseId(warehouses[0] ? String(warehouses[0].id) : '');
    setFormItems([{ ...EMPTY }]);
    setStep(1);
    setDialogOpen(true);
  };

  const handleSubmit = async () => {
    setSaving(true);
    try {
      await createAudit({
        warehouse_id: Number(formWarehouseId),
        items: formItems
          .filter((it) => it.product_id)
          .map((it) => ({
            product_id: Number(it.product_id),
            quantity: parseFloat(it.counted) || 0,
            counted: parseFloat(it.counted) || 0,
          })),
      });
      setDialogOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSaving(false);
    }
  };

  const selectedWarehouseName = warehouses.find((w) => String(w.id) === formWarehouseId)?.name ?? '—';

  const statusLabel = (status: string) => {
    if (status === 'posted') return t('posted');
    if (status === 'completed') return t('completed');
    return t('draft');
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.scm.audit')}
      actions={<Button onClick={openCreate}><Plus className="ms-1 h-4 w-4" />{t('createAudit')}</Button>}
      {...layoutProps}
    >
      <p className="mb-4 text-sm text-muted-foreground">{t('auditFlowHint')}</p>

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>ID</TableHead>
              <TableHead>{t('warehouse')}</TableHead>
              <TableHead>{t('status')}</TableHead>
              <TableHead>{tNav('common.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((r) => (
              <TableRow key={r.id}>
                <TableCell>{r.id}</TableCell>
                <TableCell>{r.warehouse?.name ?? String(r.warehouse_id ?? '—')}</TableCell>
                <TableCell>
                  <Badge variant={r.status === 'posted' ? 'default' : 'outline'}>
                    {statusLabel(r.status)}
                  </Badge>
                </TableCell>
                <TableCell className="flex flex-wrap gap-2">
                  {r.status === 'draft' && (
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => void completeAudit(r.id).then(() => { setSuccess(t('completeAudit')); void load(); }).catch(applyAxiosError)}
                    >
                      {t('completeAudit')}
                    </Button>
                  )}
                  {(r.status === 'draft' || r.status === 'completed') && (
                    <Button
                      size="sm"
                      onClick={() => void postAudit(r.id).then(() => { setSuccess(t('postAudit')); void load(); }).catch(applyAxiosError)}
                    >
                      {t('postAudit')}
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
            {rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={4} className="py-6 text-center text-muted-foreground">{tNav('common.noData')}</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] max-w-xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{step === 1 ? t('createAudit') : t('confirmAudit')}</DialogTitle>
            <DialogDescription>
              {step === 1 ? t('auditStep1') : t('auditStep2')}
            </DialogDescription>
          </DialogHeader>

          {step === 1 && (
            <div className="space-y-3">
              <div className="space-y-1">
                <label className="text-sm font-medium">{t('warehouse')}</label>
                <Select value={formWarehouseId || undefined} onValueChange={setFormWarehouseId}>
                  <SelectTrigger><SelectValue placeholder={t('warehouse')} /></SelectTrigger>
                  <SelectContent>
                    {warehouses.map((w) => (
                      <SelectItem key={w.id} value={String(w.id)}>{w.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <label className="text-sm font-medium">{t('countedQty')}</label>
                  <Button type="button" variant="outline" size="sm" onClick={() => setFormItems((p) => [...p, { ...EMPTY }])}>
                    <Plus className="ms-1 h-3.5 w-3.5" />{t('addLine')}
                  </Button>
                </div>
                {formItems.map((item, idx) => (
                  <div key={idx} className="flex items-end gap-2 rounded-md border p-2">
                    <div className="min-w-[160px] flex-1 space-y-1">
                      <label className="text-xs text-muted-foreground">{t('product')}</label>
                      <Select
                        value={item.product_id || undefined}
                        onValueChange={(v) => setFormItems((prev) => prev.map((it, i) => (i === idx ? { ...it, product_id: v } : it)))}
                      >
                        <SelectTrigger><SelectValue placeholder={t('product')} /></SelectTrigger>
                        <SelectContent>
                          {products.map((p) => (
                            <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="w-28 space-y-1">
                      <label className="text-xs text-muted-foreground">{t('countedQty')}</label>
                      <Input
                        value={item.counted}
                        onChange={(e) => setFormItems((prev) => prev.map((it, i) => (i === idx ? { ...it, counted: e.target.value } : it)))}
                      />
                    </div>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-destructive"
                      disabled={formItems.length <= 1}
                      onClick={() => setFormItems((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx)))}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="space-y-3">
              <div className="rounded-md border p-3 text-sm">
                <p><span className="font-medium">{t('warehouse')}:</span> {selectedWarehouseName}</p>
                <p className="mt-1"><span className="font-medium">{t('lines')}:</span> {formItems.filter((i) => i.product_id).length}</p>
              </div>
              <div className="overflow-x-auto rounded-md border">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b bg-muted/40">
                      <th className="px-3 py-2 text-start">{t('product')}</th>
                      <th className="px-3 py-2 text-start">{t('countedQty')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {formItems.filter((i) => i.product_id).map((item, idx) => (
                      <tr key={idx} className="border-b border-border/60">
                        <td className="px-3 py-1.5">
                          {products.find((p) => String(p.id) === item.product_id)?.name ?? item.product_id}
                        </td>
                        <td className="px-3 py-1.5 tabular-nums">{item.counted}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="text-xs text-muted-foreground">{t('auditNoJournal')}</p>
            </div>
          )}

          <DialogFooter className="gap-2">
            {step === 2 && (
              <Button variant="outline" onClick={() => setStep(1)}>{tNav('common.back')}</Button>
            )}
            {step === 1 && (
              <Button
                onClick={() => setStep(2)}
                disabled={!formWarehouseId || formItems.every((it) => !it.product_id)}
              >
                {tNav('common.next')}
              </Button>
            )}
            {step === 2 && (
              <Button onClick={() => void handleSubmit()} disabled={saving}>
                {saving ? t('saving') : t('registerAudit')}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
