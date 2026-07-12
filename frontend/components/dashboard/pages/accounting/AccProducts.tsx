'use client';

import { useState, useCallback, useEffect } from 'react';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { getAxiosMessage } from '@/lib/api-helpers';
import { accountingWpAction } from '@/lib/accounting-wp';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle,
  AlertDialogDescription, AlertDialogFooter, AlertDialogAction, AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';

type Product = {
  id: number;
  name: string;
  barcode: string;
  buy_price: number;
  sell_price: number;
  unit_id: number | null;
  category_id: number | null;
  inventory_controlled: boolean;
};

type OptionItem = { id: number; name: string };

const BLANK = {
  name: '',
  unit_id: '',
  barcode: '',
  category_id: '',
  buy_price: '',
  sell_price: '',
  inventory_controlled: false,
};

export default function AccProducts() {
  const [rows, setRows] = useState<Product[]>([]);
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [searchInput, setSearchInput] = useState('');
  const [query, setQuery] = useState('');

  const [units, setUnits] = useState<OptionItem[]>([]);
  const [categories, setCategories] = useState<OptionItem[]>([]);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [form, setForm] = useState({ ...BLANK });

  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null);

  const fetchRows = useCallback(async (p: number) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page: p, per_page: 25 };
      if (query) params.q = query;
      const res = await apiClient.get('/v1/accounting/products', { params });
      const raw = res.data as Record<string, unknown>;
      const pg = (raw.current_page != null ? raw : (raw.data as Record<string, unknown>) ?? raw);
      setRows(normalizeListPayload(pg) as unknown as Product[]);
      setPageCount(Number(pg.last_page) || 1);
      setTotal(Number(pg.total) || 0);
      setPage(Number(pg.current_page) || p);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [query]);

  useEffect(() => { void fetchRows(1); }, [fetchRows]);

  useEffect(() => {
    accountingWpAction<OptionItem[]>('units_list').then(setUnits).catch(() => {});
    accountingWpAction<OptionItem[]>('product_categories').then(setCategories).catch(() => {});
  }, []);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...BLANK });
    setFormError(null);
    setFormOpen(true);
  };

  const openEdit = (row: Product) => {
    setEditing(row);
    setForm({
      name: row.name ?? '',
      unit_id: row.unit_id ? String(row.unit_id) : '',
      barcode: row.barcode ?? '',
      category_id: row.category_id ? String(row.category_id) : '',
      buy_price: row.buy_price != null ? String(row.buy_price) : '',
      sell_price: row.sell_price != null ? String(row.sell_price) : '',
      inventory_controlled: Boolean(row.inventory_controlled),
    });
    setFormError(null);
    setFormOpen(true);
  };

  const save = useCallback(async () => {
    setSaving(true);
    setFormError(null);
    try {
      const body: Record<string, unknown> = {
        name: form.name,
        unit_id: form.unit_id ? Number(form.unit_id) : null,
        barcode: form.barcode,
        category_id: form.category_id ? Number(form.category_id) : null,
        buy_price: form.buy_price ? Number(form.buy_price) : 0,
        sell_price: form.sell_price ? Number(form.sell_price) : 0,
        inventory_controlled: form.inventory_controlled,
      };
      if (editing?.id) body.id = editing.id;
      await accountingWpAction('product_save', body);
      setFormOpen(false);
      void fetchRows(page);
    } catch (e) {
      setFormError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  }, [editing, form, page, fetchRows]);

  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    try {
      await accountingWpAction('product_delete', { id: deleteTarget.id });
      setDeleteTarget(null);
      void fetchRows(page);
    } catch (e) {
      setError(getAxiosMessage(e));
      setDeleteTarget(null);
    }
  }, [deleteTarget, page, fetchRows]);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <Input
          className="max-w-xs"
          placeholder="جستجوی کالا..."
          value={searchInput}
          onChange={e => setSearchInput(e.target.value)}
          onKeyDown={e => e.key === 'Enter' && setQuery(searchInput)}
        />
        <Button variant="outline" size="sm" onClick={() => setQuery(searchInput)}>جستجو</Button>
        <div className="flex-1" />
        <Button size="sm" onClick={openCreate}>+ ایجاد</Button>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}
      {loading && <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>}

      <div className="overflow-x-auto rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/40">
              <th className="px-3 py-2 text-start font-medium">شناسه</th>
              <th className="px-3 py-2 text-start font-medium">نام</th>
              <th className="px-3 py-2 text-start font-medium">بارکد</th>
              <th className="px-3 py-2 text-start font-medium">قیمت خرید</th>
              <th className="px-3 py-2 text-start font-medium">قیمت فروش</th>
              <th className="px-3 py-2 text-start font-medium">عملیات</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(row => (
              <tr key={row.id} className="border-b">
                <td className="px-3 py-2">{row.id}</td>
                <td className="px-3 py-2">{row.name}</td>
                <td className="px-3 py-2">{row.barcode}</td>
                <td className="px-3 py-2">{Number(row.buy_price).toLocaleString()}</td>
                <td className="px-3 py-2">{Number(row.sell_price).toLocaleString()}</td>
                <td className="px-3 py-2 space-x-1 rtl:space-x-reverse">
                  <Button variant="ghost" size="sm" onClick={() => openEdit(row)}>ویرایش</Button>
                  <Button variant="ghost" size="sm" className="text-destructive" onClick={() => setDeleteTarget(row)}>حذف</Button>
                </td>
              </tr>
            ))}
            {!loading && rows.length === 0 && (
              <tr><td colSpan={6} className="px-3 py-4 text-center text-muted-foreground">موردی یافت نشد</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {pageCount > 1 && (
        <Pagination page={page} pageCount={pageCount} total={total} onPageChange={p => fetchRows(p)} />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'ویرایش کالا' : 'ایجاد کالا'}</DialogTitle>
            <DialogDescription>{editing ? 'اطلاعات کالا را ویرایش کنید' : 'اطلاعات کالای جدید را وارد کنید'}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div>
              <label className="mb-1 block text-sm font-medium">نام</label>
              <Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">واحد</label>
              <Select value={form.unit_id || undefined} onValueChange={v => setForm({ ...form, unit_id: v })}>
                <SelectTrigger><SelectValue placeholder="انتخاب واحد" /></SelectTrigger>
                <SelectContent>
                  {units.map(u => (
                    <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">بارکد</label>
              <Input value={form.barcode} onChange={e => setForm({ ...form, barcode: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">دسته‌بندی</label>
              <Select value={form.category_id || undefined} onValueChange={v => setForm({ ...form, category_id: v })}>
                <SelectTrigger><SelectValue placeholder="انتخاب دسته‌بندی" /></SelectTrigger>
                <SelectContent>
                  {categories.map(c => (
                    <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="mb-1 block text-sm font-medium">قیمت خرید</label>
                <Input type="number" value={form.buy_price} onChange={e => setForm({ ...form, buy_price: e.target.value })} />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium">قیمت فروش</label>
                <Input type="number" value={form.sell_price} onChange={e => setForm({ ...form, sell_price: e.target.value })} />
              </div>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.inventory_controlled}
                onChange={e => setForm({ ...form, inventory_controlled: e.target.checked })}
                className="h-4 w-4 rounded border-input"
              />
              کنترل موجودی
            </label>
            {formError && <p className="text-sm text-destructive">{formError}</p>}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>انصراف</Button>
            <Button onClick={save} disabled={saving}>{saving ? 'در حال ذخیره…' : 'ذخیره'}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={open => { if (!open) setDeleteTarget(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف کالا</AlertDialogTitle>
            <AlertDialogDescription>آیا از حذف «{deleteTarget?.name}» اطمینان دارید؟</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>انصراف</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete}>حذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
