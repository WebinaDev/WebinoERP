'use client';

import { useState, useCallback, useEffect } from 'react';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { getAxiosMessage } from '@/lib/api-helpers';
import { accountingWpAction } from '@/lib/accounting-wp';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
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

type CashAccount = {
  id: number;
  name: string;
  type: string;
  bank_name: string;
  account_number: string;
  sheba: string;
  card_number: string;
  is_active: boolean;
  is_default: boolean;
};

const TYPE_LABELS: Record<string, string> = {
  bank: 'بانکی',
  cash: 'صندوق',
  petty_cash: 'تنخواه',
};

const BLANK = {
  name: '',
  type: 'bank',
  bank_name: '',
  account_number: '',
  sheba: '',
  card_number: '',
  is_active: true,
  is_default: false,
};

export default function AccCashAccounts() {
  const [rows, setRows] = useState<CashAccount[]>([]);
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<CashAccount | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [form, setForm] = useState({ ...BLANK });

  const [deleteTarget, setDeleteTarget] = useState<CashAccount | null>(null);

  const fetchRows = useCallback(async (p: number) => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/accounting/cash-accounts', { params: { page: p, per_page: 50 } });
      const raw = res.data as Record<string, unknown>;
      const pg = (raw.current_page != null ? raw : (raw.data as Record<string, unknown>) ?? raw);
      setRows(normalizeListPayload(pg) as unknown as CashAccount[]);
      setPageCount(Number(pg.last_page) || 1);
      setTotal(Number(pg.total) || 0);
      setPage(Number(pg.current_page) || p);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void fetchRows(1); }, [fetchRows]);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...BLANK });
    setFormError(null);
    setFormOpen(true);
  };

  const openEdit = (row: CashAccount) => {
    setEditing(row);
    setForm({
      name: row.name ?? '',
      type: row.type ?? 'bank',
      bank_name: row.bank_name ?? '',
      account_number: row.account_number ?? '',
      sheba: row.sheba ?? '',
      card_number: row.card_number ?? '',
      is_active: Boolean(row.is_active),
      is_default: Boolean(row.is_default),
    });
    setFormError(null);
    setFormOpen(true);
  };

  const save = useCallback(async () => {
    setSaving(true);
    setFormError(null);
    try {
      const body: Record<string, unknown> = { ...form };
      if (editing?.id) body.id = editing.id;
      await accountingWpAction('cash_account_save', body);
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
      await accountingWpAction('cash_account_delete', { id: deleteTarget.id });
      setDeleteTarget(null);
      void fetchRows(page);
    } catch (e) {
      setError(getAxiosMessage(e));
      setDeleteTarget(null);
    }
  }, [deleteTarget, page, fetchRows]);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-end gap-2">
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
              <th className="px-3 py-2 text-start font-medium">نوع</th>
              <th className="px-3 py-2 text-start font-medium">نام بانک</th>
              <th className="px-3 py-2 text-start font-medium">شماره حساب</th>
              <th className="px-3 py-2 text-start font-medium">فعال</th>
              <th className="px-3 py-2 text-start font-medium">پیش‌فرض</th>
              <th className="px-3 py-2 text-start font-medium">عملیات</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(row => (
              <tr key={row.id} className="border-b">
                <td className="px-3 py-2">{row.id}</td>
                <td className="px-3 py-2">{row.name}</td>
                <td className="px-3 py-2">{TYPE_LABELS[row.type] ?? row.type}</td>
                <td className="px-3 py-2">{row.bank_name}</td>
                <td className="px-3 py-2">{row.account_number}</td>
                <td className="px-3 py-2">
                  <Badge variant={row.is_active ? 'default' : 'outline'}>{row.is_active ? 'بله' : 'خیر'}</Badge>
                </td>
                <td className="px-3 py-2">
                  <Badge variant={row.is_default ? 'default' : 'outline'}>{row.is_default ? 'بله' : 'خیر'}</Badge>
                </td>
                <td className="px-3 py-2 space-x-1 rtl:space-x-reverse">
                  <Button variant="ghost" size="sm" onClick={() => openEdit(row)}>ویرایش</Button>
                  <Button variant="ghost" size="sm" className="text-destructive" onClick={() => setDeleteTarget(row)}>حذف</Button>
                </td>
              </tr>
            ))}
            {!loading && rows.length === 0 && (
              <tr><td colSpan={8} className="px-3 py-4 text-center text-muted-foreground">موردی یافت نشد</td></tr>
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
            <DialogTitle>{editing ? 'ویرایش حساب' : 'ایجاد حساب'}</DialogTitle>
            <DialogDescription>{editing ? 'اطلاعات حساب را ویرایش کنید' : 'اطلاعات حساب جدید را وارد کنید'}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div>
              <label className="mb-1 block text-sm font-medium">نام</label>
              <Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">نوع</label>
              <Select value={form.type} onValueChange={v => setForm({ ...form, type: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="bank">بانکی</SelectItem>
                  <SelectItem value="cash">صندوق</SelectItem>
                  <SelectItem value="petty_cash">تنخواه</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">نام بانک</label>
              <Input value={form.bank_name} onChange={e => setForm({ ...form, bank_name: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">شماره حساب</label>
              <Input value={form.account_number} onChange={e => setForm({ ...form, account_number: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">شبا</label>
              <Input value={form.sheba} onChange={e => setForm({ ...form, sheba: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">شماره کارت</label>
              <Input value={form.card_number} onChange={e => setForm({ ...form, card_number: e.target.value })} />
            </div>
            <div className="flex gap-4">
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={form.is_active} onChange={e => setForm({ ...form, is_active: e.target.checked })} className="h-4 w-4 rounded border-input" />
                فعال
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={form.is_default} onChange={e => setForm({ ...form, is_default: e.target.checked })} className="h-4 w-4 rounded border-input" />
                پیش‌فرض
              </label>
            </div>
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
            <AlertDialogTitle>حذف حساب</AlertDialogTitle>
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
