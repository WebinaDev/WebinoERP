'use client';

import { useState, useCallback, useEffect } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { accountingWpAction } from '@/lib/accounting-wp';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Pagination } from '@/components/ui/pagination';
import { JalaliDatePicker } from '@/components/ui/date-picker-jalali';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Plus, Trash2, Check } from 'lucide-react';

type LineItem = {
  product_id: string;
  description: string;
  quantity: string;
  unit_price: string;
  discount: string;
};

type Invoice = {
  id: number;
  type: string;
  number: string;
  date: string;
  person_id: number;
  person?: { name?: string };
  fiscal_year_id: number;
  fiscal_year?: { title?: string };
  status: string;
  total: number;
  notes?: string;
  items?: LineItem[];
};

type FiscalYear = { id: number; title: string };
type Product = { id: number; title?: string; name?: string; sell_price?: number | string };

const EMPTY_ITEM: LineItem = {
  product_id: '', description: '', quantity: '1', unit_price: '0', discount: '0',
};

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

export default function AccInvoices() {
  const [filterType, setFilterType] = useState('all');
  const [filterStatus, setFilterStatus] = useState('all');
  const [filterFiscalYear, setFilterFiscalYear] = useState('all');
  const [fiscalYears, setFiscalYears] = useState<FiscalYear[]>([]);
  const [products, setProducts] = useState<Product[]>([]);

  const [rows, setRows] = useState<Invoice[]>([]);
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [formType, setFormType] = useState('sale');
  const [formNumber, setFormNumber] = useState('');
  const [formDate, setFormDate] = useState<string | null>(null);
  const [formPersonId, setFormPersonId] = useState('');
  const [formFiscalYearId, setFormFiscalYearId] = useState('');
  const [formNotes, setFormNotes] = useState('');
  const [formItems, setFormItems] = useState<LineItem[]>([{ ...EMPTY_ITEM }]);
  const [saving, setSaving] = useState(false);

  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    apiClient.get('/v1/accounting/fiscal-years').then((res) => {
      setFiscalYears(normalizeListPayload(res.data) as unknown as FiscalYear[]);
    }).catch(() => {});
  }, []);

  const loadInvoices = useCallback(async (p: number) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { per_page: 25, page: p };
      if (filterType !== 'all') params.type = filterType;
      if (filterStatus !== 'all') params.status = filterStatus;
      if (filterFiscalYear !== 'all') params.fiscal_year_id = filterFiscalYear;
      const res = await apiClient.get('/v1/accounting/invoices', { params });
      const paged = parsePaginated<Invoice>(res.data);
      setRows(paged.data);
      setPage(paged.page);
      setPageCount(paged.pageCount);
      setTotal(paged.total);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [filterType, filterStatus, filterFiscalYear]);

  useEffect(() => { void loadInvoices(1); }, [loadInvoices]);

  const openCreate = useCallback(() => {
    setEditingId(null);
    setFormType('sale');
    setFormNumber('');
    setFormDate(null);
    setFormPersonId('');
    setFormFiscalYearId(fiscalYears[0]?.id ? String(fiscalYears[0].id) : '');
    setFormNotes('');
    setFormItems([{ ...EMPTY_ITEM }]);
    setDialogOpen(true);
  }, [fiscalYears]);

  const openEdit = useCallback(async (inv: Invoice) => {
    setEditingId(inv.id);
    setFormType(inv.type);
    setFormNumber(inv.number);
    setFormDate(inv.date);
    setFormPersonId(String(inv.person_id));
    setFormFiscalYearId(String(inv.fiscal_year_id));
    setFormNotes(inv.notes ?? '');
    try {
      const detail = await accountingWpAction<{ items?: Array<Record<string, unknown>> }>('invoice_get', { id: inv.id });
      if (detail.items?.length) {
        setFormItems(detail.items.map((it) => ({
          product_id: String(it.product_id ?? ''),
          description: String(it.description ?? ''),
          quantity: String(it.quantity ?? '1'),
          unit_price: String(it.unit_price ?? '0'),
          discount: String(it.discount ?? '0'),
        })));
      } else {
        setFormItems([{ ...EMPTY_ITEM }]);
      }
    } catch {
      setFormItems([{ ...EMPTY_ITEM }]);
    }
    setDialogOpen(true);
  }, []);

  const loadProducts = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/accounting/products', { params: { per_page: 500 } });
      const list = normalizeListPayload(res.data) as unknown as Product[];
      setProducts(Array.isArray(list) ? list : []);
    } catch {
      setProducts([]);
    }
  }, []);

  useEffect(() => {
    if (dialogOpen && products.length === 0) {
      void loadProducts();
    }
  }, [dialogOpen, products.length, loadProducts]);

  const updateItem = useCallback((idx: number, field: keyof LineItem, value: string) => {
    setFormItems((prev) => prev.map((it, i) => (i === idx ? { ...it, [field]: value } : it)));
  }, []);

  const addItem = useCallback(() => {
    setFormItems((prev) => [...prev, { ...EMPTY_ITEM }]);
  }, []);

  const removeItem = useCallback((idx: number) => {
    setFormItems((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx)));
  }, []);

  const computedTotal = formItems.reduce((sum, it) => {
    const qty = parseFloat(it.quantity) || 0;
    const price = parseFloat(it.unit_price) || 0;
    const disc = parseFloat(it.discount) || 0;
    return sum + qty * price - disc;
  }, 0);

  const handleSave = useCallback(async () => {
    setSaving(true);
    try {
      await accountingWpAction('invoice_save', {
        ...(editingId ? { id: editingId } : {}),
        type: formType,
        number: formNumber,
        date: formDate ?? '',
        person_id: Number(formPersonId) || 0,
        fiscal_year_id: Number(formFiscalYearId) || 0,
        status: 'draft',
        items: formItems.map((it) => ({
          product_id: Number(it.product_id) || 0,
          description: it.description,
          quantity: parseFloat(it.quantity) || 0,
          unit_price: parseFloat(it.unit_price) || 0,
          discount: parseFloat(it.discount) || 0,
        })),
        notes: formNotes,
      });
      setDialogOpen(false);
      void loadInvoices(page);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  }, [editingId, formType, formNumber, formDate, formPersonId, formFiscalYearId, formItems, formNotes, loadInvoices, page]);

  const handleConfirm = useCallback(async (id: number) => {
    try {
      await accountingWpAction('invoice_confirm', { id });
      void loadInvoices(page);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }, [loadInvoices, page]);

  const handleDelete = useCallback(async () => {
    if (!deleteId) return;
    setDeleting(true);
    try {
      await accountingWpAction('invoice_delete', { id: deleteId });
      setDeleteId(null);
      void loadInvoices(page);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setDeleting(false);
    }
  }, [deleteId, loadInvoices, page]);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold">فاکتورها</h2>
        <Button size="sm" onClick={openCreate}>
          <Plus className="ml-1 h-4 w-4" />
          فاکتور جدید
        </Button>
      </div>

      <div className="flex flex-wrap gap-3">
        <Select value={filterType} onValueChange={setFilterType}>
          <SelectTrigger className="w-36"><SelectValue placeholder="نوع" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">همه</SelectItem>
            <SelectItem value="sale">فروش</SelectItem>
            <SelectItem value="purchase">خرید</SelectItem>
          </SelectContent>
        </Select>

        <Select value={filterStatus} onValueChange={setFilterStatus}>
          <SelectTrigger className="w-36"><SelectValue placeholder="وضعیت" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">همه</SelectItem>
            <SelectItem value="draft">پیش‌نویس</SelectItem>
            <SelectItem value="confirmed">تأیید شده</SelectItem>
          </SelectContent>
        </Select>

        <Select value={filterFiscalYear} onValueChange={setFilterFiscalYear}>
          <SelectTrigger className="w-44"><SelectValue placeholder="سال مالی" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">همه سال‌ها</SelectItem>
            {fiscalYears.map((fy) => (
              <SelectItem key={fy.id} value={String(fy.id)}>{fy.title}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}
      {loading && <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>}

      <div className="overflow-x-auto rounded-md border">
        <table className="w-full min-w-[700px] text-sm">
          <thead>
            <tr className="border-b bg-muted/40">
              <th className="px-3 py-2 text-start font-medium">شناسه</th>
              <th className="px-3 py-2 text-start font-medium">شماره</th>
              <th className="px-3 py-2 text-start font-medium">تاریخ</th>
              <th className="px-3 py-2 text-start font-medium">شخص</th>
              <th className="px-3 py-2 text-start font-medium">وضعیت</th>
              <th className="px-3 py-2 text-start font-medium">مبلغ کل</th>
              <th className="px-3 py-2 text-start font-medium">عملیات</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((inv) => (
              <tr key={inv.id} className="border-b border-border/60">
                <td className="px-3 py-1.5">{inv.id}</td>
                <td className="px-3 py-1.5">{inv.number}</td>
                <td className="px-3 py-1.5">{inv.date}</td>
                <td className="px-3 py-1.5">{inv.person?.name ?? '—'}</td>
                <td className="px-3 py-1.5">
                  <Badge variant={inv.status === 'confirmed' ? 'default' : 'secondary'}>
                    {inv.status === 'confirmed' ? 'تأیید شده' : 'پیش‌نویس'}
                  </Badge>
                </td>
                <td className="px-3 py-1.5 tabular-nums">{Number(inv.total).toLocaleString('fa-IR')}</td>
                <td className="px-3 py-1.5">
                  <div className="flex gap-1">
                    <Button variant="ghost" size="sm" onClick={() => void openEdit(inv)}>ویرایش</Button>
                    {inv.status === 'draft' && (
                      <Button variant="ghost" size="sm" onClick={() => void handleConfirm(inv.id)}>
                        <Check className="ml-1 h-3.5 w-3.5" /> تأیید
                      </Button>
                    )}
                    <Button variant="ghost" size="sm" className="text-destructive" onClick={() => setDeleteId(inv.id)}>
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </td>
              </tr>
            ))}
            {!loading && rows.length === 0 && (
              <tr>
                <td colSpan={7} className="px-3 py-6 text-center text-muted-foreground">
                  فاکتوری یافت نشد.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <Pagination page={page} pageCount={pageCount} total={total} onPageChange={(p) => void loadInvoices(p)} />

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editingId ? 'ویرایش فاکتور' : 'فاکتور جدید'}</DialogTitle>
            <DialogDescription>اطلاعات فاکتور را وارد کنید.</DialogDescription>
          </DialogHeader>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <label className="text-sm font-medium">نوع</label>
              <Select value={formType} onValueChange={setFormType}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="sale">فروش</SelectItem>
                  <SelectItem value="purchase">خرید</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <label className="text-sm font-medium">شماره</label>
              <Input value={formNumber} onChange={(e) => setFormNumber(e.target.value)} placeholder="شماره فاکتور" />
            </div>
            <div className="space-y-1">
              <label className="text-sm font-medium">تاریخ</label>
              <JalaliDatePicker value={formDate} onChange={setFormDate} />
            </div>
            <div className="space-y-1">
              <label className="text-sm font-medium">شناسه شخص</label>
              <Input value={formPersonId} onChange={(e) => setFormPersonId(e.target.value)} placeholder="شناسه طرف حساب" />
            </div>
            <div className="space-y-1">
              <label className="text-sm font-medium">سال مالی</label>
              <Select value={formFiscalYearId} onValueChange={setFormFiscalYearId}>
                <SelectTrigger><SelectValue placeholder="انتخاب…" /></SelectTrigger>
                <SelectContent>
                  {fiscalYears.map((fy) => (
                    <SelectItem key={fy.id} value={String(fy.id)}>{fy.title}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <label className="text-sm font-medium">اقلام فاکتور</label>
              <Button type="button" variant="outline" size="sm" onClick={addItem}>
                <Plus className="ml-1 h-3.5 w-3.5" /> سطر جدید
              </Button>
            </div>
            {formItems.map((item, idx) => (
              <div key={idx} className="flex items-end gap-2 rounded-md border p-2">
                <div className="w-44 space-y-1">
                  <label className="text-xs text-muted-foreground">کالا</label>
                  <Select
                    value={item.product_id || 'none'}
                    onValueChange={(value) => {
                      if (value === 'none') {
                        updateItem(idx, 'product_id', '');
                        return;
                      }
                      const selected = products.find((p) => p.id === Number(value));
                      updateItem(idx, 'product_id', value);
                      if (selected?.sell_price != null && String(selected.sell_price) !== '') {
                        updateItem(idx, 'unit_price', String(selected.sell_price));
                      }
                    }}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="انتخاب کالا" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">بدون کالا</SelectItem>
                      {products.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          {p.title ?? p.name ?? `#${p.id}`}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex-1 space-y-1">
                  <label className="text-xs text-muted-foreground">شرح</label>
                  <Input value={item.description} onChange={(e) => updateItem(idx, 'description', e.target.value)} />
                </div>
                <div className="w-20 space-y-1">
                  <label className="text-xs text-muted-foreground">تعداد</label>
                  <Input value={item.quantity} onChange={(e) => updateItem(idx, 'quantity', e.target.value)} />
                </div>
                <div className="w-28 space-y-1">
                  <label className="text-xs text-muted-foreground">قیمت واحد</label>
                  <Input value={item.unit_price} onChange={(e) => updateItem(idx, 'unit_price', e.target.value)} />
                </div>
                <div className="w-24 space-y-1">
                  <label className="text-xs text-muted-foreground">تخفیف</label>
                  <Input value={item.discount} onChange={(e) => updateItem(idx, 'discount', e.target.value)} />
                </div>
                <Button variant="ghost" size="sm" className="text-destructive" onClick={() => removeItem(idx)} disabled={formItems.length <= 1}>
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
            ))}
            <p className="text-sm font-medium">
              جمع کل: <span className="tabular-nums">{computedTotal.toLocaleString('fa-IR')}</span>
            </p>
          </div>

          <div className="space-y-1">
            <label className="text-sm font-medium">یادداشت</label>
            <Textarea value={formNotes} onChange={(e) => setFormNotes(e.target.value)} rows={2} />
          </div>

          <DialogFooter>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? 'در حال ذخیره…' : 'ذخیره'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => { if (!o) setDeleteId(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف فاکتور</AlertDialogTitle>
            <AlertDialogDescription>آیا از حذف این فاکتور اطمینان دارید؟ این عمل قابل بازگشت نیست.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>انصراف</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={deleting}>
              {deleting ? 'در حال حذف…' : 'حذف'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
