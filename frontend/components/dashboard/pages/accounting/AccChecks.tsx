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
import { JalaliDatePicker } from '@/components/ui/date-picker-jalali';
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

type Check = {
  id: number;
  type: string;
  number: string;
  date: string;
  amount: number;
  person_id: number | null;
  cash_account_id: number | null;
  bank_name: string;
  status: string;
  description: string;
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'در انتظار',
  collected: 'وصول شده',
  spent: 'خرج شده',
  returned: 'برگشت شده',
};

const BLANK = {
  type: 'receivable',
  number: '',
  date: '',
  amount: '',
  person_id: '',
  cash_account_id: '',
  bank_name: '',
  status: 'pending',
  description: '',
};

export default function AccChecks() {
  const [rows, setRows] = useState<Check[]>([]);
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [filterType, setFilterType] = useState('');
  const [filterStatus, setFilterStatus] = useState('');

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Check | null>(null);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [form, setForm] = useState({ ...BLANK });

  const [deleteTarget, setDeleteTarget] = useState<Check | null>(null);

  const fetchRows = useCallback(async (p: number) => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { page: p, per_page: 25 };
      if (filterType) params.type = filterType;
      if (filterStatus) params.status = filterStatus;
      const res = await apiClient.get('/v1/accounting/checks', { params });
      const raw = res.data as Record<string, unknown>;
      const pg = (raw.current_page != null ? raw : (raw.data as Record<string, unknown>) ?? raw);
      setRows(normalizeListPayload(pg) as unknown as Check[]);
      setPageCount(Number(pg.last_page) || 1);
      setTotal(Number(pg.total) || 0);
      setPage(Number(pg.current_page) || p);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [filterType, filterStatus]);

  useEffect(() => { void fetchRows(1); }, [fetchRows]);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...BLANK });
    setFormError(null);
    setFormOpen(true);
  };

  const openEdit = (row: Check) => {
    setEditing(row);
    setForm({
      type: row.type ?? 'receivable',
      number: row.number ?? '',
      date: row.date ?? '',
      amount: row.amount != null ? String(row.amount) : '',
      person_id: row.person_id != null ? String(row.person_id) : '',
      cash_account_id: row.cash_account_id != null ? String(row.cash_account_id) : '',
      bank_name: row.bank_name ?? '',
      status: row.status ?? 'pending',
      description: row.description ?? '',
    });
    setFormError(null);
    setFormOpen(true);
  };

  const save = useCallback(async () => {
    setSaving(true);
    setFormError(null);
    try {
      const body: Record<string, unknown> = {
        type: form.type,
        number: form.number,
        date: form.date,
        amount: form.amount ? Number(form.amount) : 0,
        person_id: form.person_id ? Number(form.person_id) : null,
        cash_account_id: form.cash_account_id ? Number(form.cash_account_id) : null,
        bank_name: form.bank_name,
        status: form.status,
        description: form.description,
      };
      if (editing?.id) body.id = editing.id;
      await accountingWpAction('check_save', body);
      setFormOpen(false);
      void fetchRows(page);
    } catch (e) {
      setFormError(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  }, [editing, form, page, fetchRows]);

  const setCheckStatus = useCallback(async (id: number, status: string) => {
    try {
      await accountingWpAction('check_set_status', { id, status });
      void fetchRows(page);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }, [page, fetchRows]);

  const handleDelete = useCallback(async () => {
    if (!deleteTarget) return;
    try {
      await accountingWpAction('check_delete', { id: deleteTarget.id });
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
        <Select value={filterType || 'all'} onValueChange={v => setFilterType(v === 'all' ? '' : v)}>
          <SelectTrigger className="w-36"><SelectValue placeholder="نوع" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">همه انواع</SelectItem>
            <SelectItem value="receivable">دریافتنی</SelectItem>
            <SelectItem value="payable">پرداختنی</SelectItem>
          </SelectContent>
        </Select>
        <Select value={filterStatus || 'all'} onValueChange={v => setFilterStatus(v === 'all' ? '' : v)}>
          <SelectTrigger className="w-36"><SelectValue placeholder="وضعیت" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">همه وضعیت‌ها</SelectItem>
            <SelectItem value="pending">در انتظار</SelectItem>
            <SelectItem value="collected">وصول شده</SelectItem>
            <SelectItem value="spent">خرج شده</SelectItem>
            <SelectItem value="returned">برگشت شده</SelectItem>
          </SelectContent>
        </Select>
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
              <th className="px-3 py-2 text-start font-medium">شماره</th>
              <th className="px-3 py-2 text-start font-medium">تاریخ</th>
              <th className="px-3 py-2 text-start font-medium">مبلغ</th>
              <th className="px-3 py-2 text-start font-medium">بانک</th>
              <th className="px-3 py-2 text-start font-medium">وضعیت</th>
              <th className="px-3 py-2 text-start font-medium">عملیات</th>
            </tr>
          </thead>
          <tbody>
            {rows.map(row => (
              <tr key={row.id} className="border-b">
                <td className="px-3 py-2">{row.id}</td>
                <td className="px-3 py-2">{row.number}</td>
                <td className="px-3 py-2">{row.date}</td>
                <td className="px-3 py-2">{Number(row.amount).toLocaleString()}</td>
                <td className="px-3 py-2">{row.bank_name}</td>
                <td className="px-3 py-2">
                  <Badge variant="outline">{STATUS_LABELS[row.status] ?? row.status}</Badge>
                </td>
                <td className="px-3 py-2 space-x-1 rtl:space-x-reverse">
                  {row.status !== 'collected' && (
                    <Button variant="outline" size="sm" onClick={() => setCheckStatus(row.id, 'collected')}>وصول</Button>
                  )}
                  {row.status !== 'spent' && (
                    <Button variant="outline" size="sm" onClick={() => setCheckStatus(row.id, 'spent')}>خرج</Button>
                  )}
                  {row.status !== 'returned' && (
                    <Button variant="outline" size="sm" onClick={() => setCheckStatus(row.id, 'returned')}>برگشت</Button>
                  )}
                  <Button variant="ghost" size="sm" onClick={() => openEdit(row)}>ویرایش</Button>
                  <Button variant="ghost" size="sm" className="text-destructive" onClick={() => setDeleteTarget(row)}>حذف</Button>
                </td>
              </tr>
            ))}
            {!loading && rows.length === 0 && (
              <tr><td colSpan={7} className="px-3 py-4 text-center text-muted-foreground">موردی یافت نشد</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {pageCount > 1 && (
        <Pagination page={page} pageCount={pageCount} total={total} onPageChange={p => fetchRows(p)} />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editing ? 'ویرایش چک' : 'ایجاد چک'}</DialogTitle>
            <DialogDescription>{editing ? 'اطلاعات چک را ویرایش کنید' : 'اطلاعات چک جدید را وارد کنید'}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div>
              <label className="mb-1 block text-sm font-medium">نوع</label>
              <Select value={form.type} onValueChange={v => setForm({ ...form, type: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="receivable">دریافتنی</SelectItem>
                  <SelectItem value="payable">پرداختنی</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">شماره</label>
              <Input value={form.number} onChange={e => setForm({ ...form, number: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">تاریخ</label>
              <JalaliDatePicker value={form.date || null} onChange={v => setForm({ ...form, date: v ?? '' })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">مبلغ</label>
              <Input type="number" value={form.amount} onChange={e => setForm({ ...form, amount: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">شخص (شناسه)</label>
              <Input value={form.person_id} onChange={e => setForm({ ...form, person_id: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">حساب بانکی (شناسه)</label>
              <Input value={form.cash_account_id} onChange={e => setForm({ ...form, cash_account_id: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">نام بانک</label>
              <Input value={form.bank_name} onChange={e => setForm({ ...form, bank_name: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">وضعیت</label>
              <Select value={form.status} onValueChange={v => setForm({ ...form, status: v })}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="pending">در انتظار</SelectItem>
                  <SelectItem value="collected">وصول شده</SelectItem>
                  <SelectItem value="spent">خرج شده</SelectItem>
                  <SelectItem value="returned">برگشت شده</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium">توضیحات</label>
              <Input value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} />
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
            <AlertDialogTitle>حذف چک</AlertDialogTitle>
            <AlertDialogDescription>آیا از حذف چک شماره «{deleteTarget?.number}» اطمینان دارید؟</AlertDialogDescription>
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
