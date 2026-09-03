'use client';

import { Fragment, useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import apiClient from '@/lib/api-client';
import { createInbound, listWarehouses, postInbound } from '@/lib/api/scm';
import { normalizeListPayload } from '@/lib/list-utils';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';

type Warehouse = { id: number; name: string };
type Product = { id: number; name: string };
type DocItem = { product_id: string; quantity: string; unit_price: string };
type WarehouseDoc = {
  id: number;
  warehouse?: { name?: string };
  warehouse_id?: number;
  reference?: string;
  notes?: string;
  status: string;
  created_at?: string;
  items?: Record<string, unknown>[];
};

const EMPTY: DocItem = { product_id: '', quantity: '1', unit_price: '0' };

export function InboundPage() {
  const t = useTranslations('scm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [rows, setRows] = useState<WarehouseDoc[]>([]);
  const [filterStatus, setFilterStatus] = useState('all');
  const [filterWarehouse, setFilterWarehouse] = useState('all');
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formWarehouseId, setFormWarehouseId] = useState('');
  const [formReference, setFormReference] = useState('');
  const [formNotes, setFormNotes] = useState('');
  const [formItems, setFormItems] = useState<DocItem[]>([{ ...EMPTY }]);

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
      const params: Record<string, string> = { per_page: '50' };
      if (filterStatus !== 'all') params.status = filterStatus;
      if (filterWarehouse !== 'all') params.warehouse_id = filterWarehouse;
      const res = await apiClient.get('/v1/scm/inbound', { params });
      setRows(normalizeListPayload(res.data) as unknown as WarehouseDoc[]);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [filterStatus, filterWarehouse, applyAxiosError]);

  useEffect(() => {
    void loadLookups();
  }, [loadLookups]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setFormWarehouseId(warehouses[0] ? String(warehouses[0].id) : '');
    setFormReference('');
    setFormNotes('');
    setFormItems([{ ...EMPTY }]);
    setDialogOpen(true);
  };

  const create = async () => {
    setSaving(true);
    try {
      await createInbound({
        reference: formReference || undefined,
        warehouse_id: Number(formWarehouseId),
        notes: formNotes || undefined,
        items: formItems
          .filter((it) => it.product_id)
          .map((it) => ({
            product_id: Number(it.product_id),
            quantity: parseFloat(it.quantity) || 0,
            unit_price: parseFloat(it.unit_price) || 0,
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

  const productName = (id: unknown) =>
    products.find((p) => p.id === Number(id))?.name ?? String(id ?? '—');

  return (
    <CrmPageLayout
      title={tNav('nav.erp.scm.inbound')}
      actions={<Button onClick={openCreate}><Plus className="ms-1 h-4 w-4" />{t('createInbound')}</Button>}
      {...layoutProps}
    >
      <div className="mb-4 flex flex-wrap gap-3">
        <Select value={filterStatus} onValueChange={setFilterStatus}>
          <SelectTrigger className="w-40"><SelectValue placeholder={t('status')} /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('allStatuses')}</SelectItem>
            <SelectItem value="draft">{t('draft')}</SelectItem>
            <SelectItem value="posted">{t('posted')}</SelectItem>
          </SelectContent>
        </Select>
        <Select value={filterWarehouse} onValueChange={setFilterWarehouse}>
          <SelectTrigger className="w-48"><SelectValue placeholder={t('warehouse')} /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('allWarehouses')}</SelectItem>
            {warehouses.map((w) => (
              <SelectItem key={w.id} value={String(w.id)}>{w.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="overflow-x-auto rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>ID</TableHead>
              <TableHead>{t('reference')}</TableHead>
              <TableHead>{t('warehouse')}</TableHead>
              <TableHead>{t('status')}</TableHead>
              <TableHead>{tNav('common.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((r) => (
              <Fragment key={r.id}>
                <TableRow>
                  <TableCell>{r.id}</TableCell>
                  <TableCell>{r.reference ?? '—'}</TableCell>
                  <TableCell>{r.warehouse?.name ?? String(r.warehouse_id ?? '—')}</TableCell>
                  <TableCell>
                    <Badge variant={r.status === 'posted' ? 'default' : 'outline'}>
                      {r.status === 'posted' ? t('posted') : t('draft')}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      {r.status !== 'posted' && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => void postInbound(r.id).then(() => { setSuccess(t('postInbound')); void load(); }).catch(applyAxiosError)}
                        >
                          {t('postInbound')}
                        </Button>
                      )}
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setExpandedId(expandedId === r.id ? null : r.id)}
                      >
                        {expandedId === r.id ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
                {expandedId === r.id && (
                  <TableRow>
                    <TableCell colSpan={5} className="bg-muted/20">
                      {(r.items?.length ?? 0) > 0 ? (
                        <table className="w-full text-xs">
                          <thead>
                            <tr>
                              <th className="px-2 py-1 text-start">{t('product')}</th>
                              <th className="px-2 py-1 text-start">{t('quantity')}</th>
                              <th className="px-2 py-1 text-start">{t('unitPrice')}</th>
                            </tr>
                          </thead>
                          <tbody>
                            {(r.items ?? []).map((it, i) => (
                              <tr key={i}>
                                <td className="px-2 py-0.5">{productName(it.product_id)}</td>
                                <td className="px-2 py-0.5">{String(it.quantity ?? '—')}</td>
                                <td className="px-2 py-0.5">{String(it.unit_price ?? it.unit_cost ?? '—')}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      ) : (
                        <p className="text-xs text-muted-foreground">{t('noLines')}</p>
                      )}
                    </TableCell>
                  </TableRow>
                )}
              </Fragment>
            ))}
            {rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-6 text-center text-muted-foreground">{tNav('common.noData')}</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] max-w-xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('createInbound')}</DialogTitle>
            <DialogDescription>{t('inboundHint')}</DialogDescription>
          </DialogHeader>
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
            <div className="space-y-1">
              <label className="text-sm font-medium">{t('reference')}</label>
              <Input value={formReference} onChange={(e) => setFormReference(e.target.value)} />
            </div>
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label className="text-sm font-medium">{t('lines')}</label>
                <Button type="button" variant="outline" size="sm" onClick={() => setFormItems((p) => [...p, { ...EMPTY }])}>
                  <Plus className="ms-1 h-3.5 w-3.5" />{t('addLine')}
                </Button>
              </div>
              {formItems.map((item, idx) => (
                <div key={idx} className="flex flex-wrap items-end gap-2 rounded-md border p-2">
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
                  <div className="w-24 space-y-1">
                    <label className="text-xs text-muted-foreground">{t('quantity')}</label>
                    <Input
                      value={item.quantity}
                      onChange={(e) => setFormItems((prev) => prev.map((it, i) => (i === idx ? { ...it, quantity: e.target.value } : it)))}
                    />
                  </div>
                  <div className="w-28 space-y-1">
                    <label className="text-xs text-muted-foreground">{t('unitPrice')}</label>
                    <Input
                      value={item.unit_price}
                      onChange={(e) => setFormItems((prev) => prev.map((it, i) => (i === idx ? { ...it, unit_price: e.target.value } : it)))}
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
            <div className="space-y-1">
              <label className="text-sm font-medium">{t('notes')}</label>
              <Textarea value={formNotes} onChange={(e) => setFormNotes(e.target.value)} rows={2} />
            </div>
          </div>
          <DialogFooter>
            <Button onClick={() => void create()} disabled={saving || !formWarehouseId}>
              {saving ? t('saving') : tNav('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
