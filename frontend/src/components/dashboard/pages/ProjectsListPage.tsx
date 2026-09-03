'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import Link from 'next/link';
import { useLocale } from 'next-intl';

type ProjectRow = Record<string, unknown>;
type Meta = { current_page?: number; last_page?: number; total?: number };

export function ProjectsListPage() {
  const locale = useLocale();
  const [rows, setRows] = useState<ProjectRow[]>([]);
  const [meta, setMeta] = useState<Meta>({});
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [wizardOpen, setWizardOpen] = useState(false);
  const [step, setStep] = useState(1);
  const [form, setForm] = useState({ name: '', description: '', status: 'active', customer_account_id: '' });
  const [editOpen, setEditOpen] = useState(false);
  const [editRow, setEditRow] = useState<ProjectRow | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/projects', {
        params: { page, per_page: 12, search: search || undefined },
      });
      const body = res.data as { data?: unknown; meta?: Meta };
      setRows(normalizeListPayload(body));
      setMeta(body.meta ?? {});
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    void load();
  }, [load]);

  async function submitProject() {
    setError(null);
    try {
      await apiClient.post('/v1/projects/projects', {
        name: form.name,
        description: form.description || undefined,
        status: form.status,
        customer_account_id: form.customer_account_id ? Number(form.customer_account_id) : undefined,
      });
      setWizardOpen(false);
      setStep(1);
      setForm({ name: '', description: '', status: 'active', customer_account_id: '' });
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function submitEdit() {
    if (!editRow?.id) return;
    setError(null);
    try {
      await apiClient.patch(`/v1/projects/projects/${String(editRow.id)}`, {
        name: form.name,
        description: form.description || undefined,
        status: form.status,
        customer_account_id: form.customer_account_id ? Number(form.customer_account_id) : undefined,
      });
      setEditOpen(false);
      setEditRow(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function confirmDelete() {
    if (!deleteId) return;
    try {
      await apiClient.delete(`/v1/projects/projects/${deleteId}`);
      setDeleteId(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  function openEdit(p: ProjectRow) {
    setEditRow(p);
    setForm({
      name: String(p.name ?? ''),
      description: String(p.description ?? ''),
      status: String(p.status ?? 'active'),
      customer_account_id: p.customer_account_id != null ? String(p.customer_account_id) : '',
    });
    setEditOpen(true);
  }

  const lastPage = meta.last_page ?? 1;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold">پروژه‌ها</h2>
          <p className="text-xs text-muted-foreground">Grid + ویزارد دو مرحله‌ای ساخت پروژه</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Input placeholder="جستجو نام…" className="w-48" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Button type="button" variant="secondary" size="sm" onClick={() => void load()}>
            جستجو
          </Button>
          <Button type="button" size="sm" onClick={() => setWizardOpen(true)}>
            پروژه جدید
          </Button>
        </div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}

      {loading ? (
        <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {rows.map((p) => (
            <Card key={String(p.id)} className="flex flex-col">
              <CardHeader>
                <CardTitle className="text-base">{String(p.name ?? '—')}</CardTitle>
                <CardDescription className="line-clamp-2">{String(p.description ?? '')}</CardDescription>
              </CardHeader>
              <CardContent className="mt-auto flex flex-wrap gap-2">
                <span className="rounded-full bg-muted px-2 py-0.5 text-xs">{String(p.status ?? '—')}</span>
                <Link href={`/dashboard/projects/${String(p.id)}`}>
                  <Button variant="outline" size="sm">
                    جزئیات و تسک‌ها
                  </Button>
                </Link>
                <Button type="button" variant="secondary" size="sm" onClick={() => openEdit(p)}>
                  ویرایش
                </Button>
                <Button type="button" variant="destructive" size="sm" onClick={() => setDeleteId(Number(p.id))}>
                  حذف
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Pagination
        page={meta.current_page ?? page}
        pageCount={lastPage}
        total={meta.total}
        onPageChange={setPage}
      />

      <Dialog open={wizardOpen} onOpenChange={setWizardOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>پروژه جدید — مرحله {step} از ۲</DialogTitle>
          </DialogHeader>
          {step === 1 ? (
            <div className="space-y-3 py-2">
              <div>
                <label className="text-sm">نام پروژه</label>
                <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
              </div>
              <div>
                <label className="text-sm">توضیحات</label>
                <Textarea value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
              </div>
            </div>
          ) : (
            <div className="space-y-3 py-2">
              <div>
                <label className="text-sm">وضعیت</label>
                <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">فعال</SelectItem>
                    <SelectItem value="on_hold">متوقف</SelectItem>
                    <SelectItem value="completed">تکمیل</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <label className="text-sm">شناسه حساب مشتری (اختیاری)</label>
                <Input
                  type="number"
                  value={form.customer_account_id}
                  onChange={(e) => setForm((f) => ({ ...f, customer_account_id: e.target.value }))}
                  placeholder="crm_accounts.id"
                />
              </div>
            </div>
          )}
          <DialogFooter className="gap-2 sm:gap-0">
            {step === 2 ? (
              <Button type="button" variant="outline" onClick={() => setStep(1)}>
                قبلی
              </Button>
            ) : null}
            {step === 1 ? (
              <Button type="button" onClick={() => setStep(2)} disabled={!form.name.trim()}>
                بعدی
              </Button>
            ) : (
              <Button type="button" onClick={() => void submitProject()}>
                ذخیره
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>ویرایش پروژه</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div>
              <label className="text-sm">نام</label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div>
              <label className="text-sm">توضیحات</label>
              <Textarea value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
            </div>
            <div>
              <label className="text-sm">وضعیت</label>
              <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">فعال</SelectItem>
                  <SelectItem value="on_hold">متوقف</SelectItem>
                  <SelectItem value="completed">تکمیل</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="text-sm">شناسه حساب مشتری</label>
              <Input
                type="number"
                value={form.customer_account_id}
                onChange={(e) => setForm((f) => ({ ...f, customer_account_id: e.target.value }))}
              />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void submitEdit()}>
              ذخیره
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف پروژه؟</AlertDialogTitle>
            <AlertDialogDescription>این عمل قابل بازگشت نیست.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>انصراف</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>حذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
