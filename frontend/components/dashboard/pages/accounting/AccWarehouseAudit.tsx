'use client';

import { useState, useCallback, useEffect } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Pagination } from '@/components/ui/pagination';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Plus, Trash2 } from 'lucide-react';

type Warehouse = { id: number; name: string };

type AuditItem = { product_id: string; quantity: string };

type WarehouseDoc = {
  id: number;
  warehouse?: { name?: string };
  warehouse_id?: number;
  status: string;
  created_at?: string;
};

const EMPTY_AUDIT_ITEM: AuditItem = { product_id: '', quantity: '0' };

function parsePaginated<T>(axiosData: unknown) {
  const obj = axiosData as Record<string, unknown> | null;
  if (!obj || typeof obj !== 'object') return { data: [] as T[], page: 1, pageCount: 1, total: 0 };
  let envelope = obj;
  if (obj.data && typeof obj.data === 'object' && !Array.isArray(obj.data)) {
    const inner = obj.data as Record<string, unknown>;
    if (Array.isArray(inner.data)) envelope = inner;
  }
  const list = (Array.isArray(envelope.data) ? envelope.data : []) as T[];
  const meta = (envelope.meta && typeof envelope.meta === 'object' ? envelope.meta : envelope) as Record<string, unknown>;
  return {
    data: list,
    page: Number(meta.current_page ?? 1),
    pageCount: Number(meta.last_page ?? 1),
    total: Number(meta.total ?? list.length),
  };
}

export default function AccWarehouseAudit() {
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [rows, setRows] = useState<WarehouseDoc[]>([]);
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [step, setStep] = useState<1 | 2>(1);
  const [formWarehouseId, setFormWarehouseId] = useState('');
  const [formItems, setFormItems] = useState<AuditItem[]>([{ ...EMPTY_AUDIT_ITEM }]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    apiClient.get('/v1/accounting/warehouses').then((res) => {
      setWarehouses(normalizeListPayload(res.data) as unknown as Warehouse[]);
    }).catch(() => {});
  }, []);

  const loadDocs = useCallback(async (p: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/accounting/warehouse-audit', { params: { per_page: 25, page: p } });
      const paged = parsePaginated<WarehouseDoc>(res.data);
      setRows(paged.data);
      setPage(paged.page);
      setPageCount(paged.pageCount);
      setTotal(paged.total);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void loadDocs(1); }, [loadDocs]);

  const openCreate = useCallback(() => {
    setFormWarehouseId(warehouses[0]?.id ? String(warehouses[0].id) : '');
    setFormItems([{ ...EMPTY_AUDIT_ITEM }]);
    setStep(1);
    setDialogOpen(true);
  }, [warehouses]);

  const updateItem = useCallback((idx: number, field: keyof AuditItem, value: string) => {
    setFormItems((prev) => prev.map((it, i) => (i === idx ? { ...it, [field]: value } : it)));
  }, []);

  const addItem = useCallback(() => {
    setFormItems((prev) => [...prev, { ...EMPTY_AUDIT_ITEM }]);
  }, []);

  const removeItem = useCallback((idx: number) => {
    setFormItems((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx)));
  }, []);

  const handleSubmit = useCallback(async () => {
    setSaving(true);
    setError(null);
    try {
      await apiClient.post('/v1/accounting/warehouse-ajax/warehouse_document_create', {
        warehouse_id: Number(formWarehouseId),
        type: 'audit',
        items: formItems.map((it) => ({
          product_id: Number(it.product_id) || 0,
          quantity: parseFloat(it.quantity) || 0,
        })),
        notes: '',
        status: 'draft',
      });
      setDialogOpen(false);
      void loadDocs(page);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  }, [formWarehouseId, formItems, loadDocs, page]);

  const handlePost = useCallback(async (id: number) => {
    try {
      await apiClient.post('/v1/accounting/warehouse-ajax/warehouse_document_post', { id });
      void loadDocs(page);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }, [loadDocs, page]);

  const selectedWarehouseName = warehouses.find((w) => String(w.id) === formWarehouseId)?.name ?? '—';

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold">انبارگردانی</h2>
        <Button size="sm" onClick={openCreate}>
          <Plus className="ml-1 h-4 w-4" /> انبارگردانی جدید
        </Button>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}
      {loading && <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>}

      <div className="overflow-x-auto rounded-md border">
        <table className="w-full min-w-[500px] text-sm">
          <thead>
            <tr className="border-b bg-muted/40">
              <th className="px-3 py-2 text-start font-medium">شناسه</th>
              <th className="px-3 py-2 text-start font-medium">انبار</th>
              <th className="px-3 py-2 text-start font-medium">وضعیت</th>
              <th className="px-3 py-2 text-start font-medium">تاریخ ثبت</th>
              <th className="px-3 py-2 text-start font-medium">عملیات</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((doc) => (
              <tr key={doc.id} className="border-b border-border/60">
                <td className="px-3 py-1.5">{doc.id}</td>
                <td className="px-3 py-1.5">{doc.warehouse?.name ?? '—'}</td>
                <td className="px-3 py-1.5">
                  <Badge variant={doc.status === 'posted' ? 'default' : 'secondary'}>
                    {doc.status === 'posted' ? 'تکمیل شده' : 'پیش‌نویس'}
                  </Badge>
                </td>
                <td className="px-3 py-1.5">{doc.created_at ?? '—'}</td>
                <td className="px-3 py-1.5">
                  {doc.status === 'draft' && (
                    <Button variant="outline" size="sm" onClick={() => void handlePost(doc.id)}>
                      تکمیل و ثبت
                    </Button>
                  )}
                </td>
              </tr>
            ))}
            {!loading && rows.length === 0 && (
              <tr>
                <td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">
                  سند انبارگردانی یافت نشد.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <Pagination page={page} pageCount={pageCount} total={total} onPageChange={(p) => void loadDocs(p)} />

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {step === 1 ? 'انبارگردانی — ثبت شمارش' : 'انبارگردانی — بررسی نهایی'}
            </DialogTitle>
            <DialogDescription>
              {step === 1
                ? 'انبار و اقلام شمارش‌شده را وارد کنید.'
                : 'اطلاعات واردشده را بررسی و در صورت صحت ثبت کنید.'}
            </DialogDescription>
          </DialogHeader>

          {step === 1 && (
            <div className="space-y-3">
              <div className="space-y-1">
                <label className="text-sm font-medium">انبار</label>
                <Select value={formWarehouseId} onValueChange={setFormWarehouseId}>
                  <SelectTrigger><SelectValue placeholder="انتخاب انبار…" /></SelectTrigger>
                  <SelectContent>
                    {warehouses.map((w) => (
                      <SelectItem key={w.id} value={String(w.id)}>{w.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <label className="text-sm font-medium">اقلام شمارش‌شده</label>
                  <Button type="button" variant="outline" size="sm" onClick={addItem}>
                    <Plus className="ml-1 h-3.5 w-3.5" /> سطر جدید
                  </Button>
                </div>
                {formItems.map((item, idx) => (
                  <div key={idx} className="flex items-end gap-2 rounded-md border p-2">
                    <div className="flex-1 space-y-1">
                      <label className="text-xs text-muted-foreground">شناسه کالا</label>
                      <Input value={item.product_id} onChange={(e) => updateItem(idx, 'product_id', e.target.value)} />
                    </div>
                    <div className="w-28 space-y-1">
                      <label className="text-xs text-muted-foreground">تعداد شمارش‌شده</label>
                      <Input value={item.quantity} onChange={(e) => updateItem(idx, 'quantity', e.target.value)} />
                    </div>
                    <Button variant="ghost" size="sm" className="text-destructive" onClick={() => removeItem(idx)} disabled={formItems.length <= 1}>
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
                <p><span className="font-medium">انبار:</span> {selectedWarehouseName}</p>
                <p className="mt-1"><span className="font-medium">تعداد اقلام:</span> {formItems.length}</p>
              </div>

              <div className="overflow-x-auto rounded-md border">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b bg-muted/40">
                      <th className="px-3 py-2 text-start font-medium">ردیف</th>
                      <th className="px-3 py-2 text-start font-medium">شناسه کالا</th>
                      <th className="px-3 py-2 text-start font-medium">تعداد شمارش‌شده</th>
                    </tr>
                  </thead>
                  <tbody>
                    {formItems.map((item, idx) => (
                      <tr key={idx} className="border-b border-border/60">
                        <td className="px-3 py-1.5">{idx + 1}</td>
                        <td className="px-3 py-1.5">{item.product_id || '—'}</td>
                        <td className="px-3 py-1.5 tabular-nums">{item.quantity}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          <DialogFooter className="gap-2">
            {step === 2 && (
              <Button variant="outline" onClick={() => setStep(1)}>
                بازگشت
              </Button>
            )}
            {step === 1 && (
              <Button onClick={() => setStep(2)} disabled={!formWarehouseId || formItems.every((it) => !it.product_id)}>
                مرحله بعد
              </Button>
            )}
            {step === 2 && (
              <Button onClick={handleSubmit} disabled={saving}>
                {saving ? 'در حال ذخیره…' : 'ثبت انبارگردانی'}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
