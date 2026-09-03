'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage, unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { Badge } from '@/components/ui/badge';

type Row = Record<string, unknown>;
type Meta = { current_page?: number; last_page?: number; total?: number };
type IdName = { id: number; name?: string; email?: string };

export function InvoicesListPage() {
  const [rows, setRows] = useState<Row[]>([]);
  const [meta, setMeta] = useState<Meta>({});
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const [filterStatus, setFilterStatus] = useState('');
  const [customerUserId, setCustomerUserId] = useState('');
  const [projectId, setProjectId] = useState('');

  const [users, setUsers] = useState<IdName[]>([]);
  const [projects, setProjects] = useState<IdName[]>([]);

  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [items, setItems] = useState<{ desc: string; qty: string; price: string }[]>([{ desc: '', qty: '1', price: '0' }]);
  const [formErr, setFormErr] = useState<string | null>(null);
  const [formStatus, setFormStatus] = useState('draft');
  const [formCustomer, setFormCustomer] = useState('');
  const [formProject, setFormProject] = useState('');
  const [notes, setNotes] = useState('');

  const total = items.reduce((s, it) => s + Number(it.qty || 0) * Number(it.price || 0), 0);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/invoices', {
        params: {
          page,
          per_page: 15,
          status: filterStatus || undefined,
          customer_user_id: customerUserId || undefined,
          project_id: projectId || undefined,
        },
      });
      const pageData = unwrapData<Record<string, unknown>>(res);
      setRows(normalizeListPayload(pageData));
      setMeta({
        current_page: typeof pageData.current_page === 'number' ? pageData.current_page : undefined,
        last_page: typeof pageData.last_page === 'number' ? pageData.last_page : undefined,
        total: typeof pageData.total === 'number' ? pageData.total : undefined,
      });
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [page, filterStatus, customerUserId, projectId]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void (async () => {
      try {
        const [uRes, pRes] = await Promise.all([
          apiClient.get('/v1/core/users', { params: { per_page: 200 } }),
          apiClient.get('/v1/projects/projects', { params: { per_page: 200 } }),
        ]);
        const uData = unwrapData<Record<string, unknown>>(uRes);
        const pData = unwrapData<Record<string, unknown>>(pRes);
        setUsers(normalizeListPayload(uData) as IdName[]);
        setProjects(normalizeListPayload(pData) as IdName[]);
      } catch {
        setUsers([]);
        setProjects([]);
      }
    })();
  }, []);

  function openCreate() {
    setEditingId(null);
    setItems([{ desc: '', qty: '1', price: '0' }]);
    setFormStatus('draft');
    setFormCustomer('');
    setFormProject('');
    setNotes('');
    setFormErr(null);
    setOpen(true);
  }

  function openEdit(r: Row) {
    const id = Number(r.id);
    setEditingId(Number.isFinite(id) ? id : null);
    const rawItems = r.items as { description?: string; quantity?: number; unit_price?: number }[] | undefined;
    if (Array.isArray(rawItems) && rawItems.length) {
      setItems(
        rawItems.map((it) => ({
          desc: String(it.description ?? ''),
          qty: String(it.quantity ?? 1),
          price: String(it.unit_price ?? 0),
        })),
      );
    } else {
      setItems([{ desc: '', qty: '1', price: String(r.total ?? '0') }]);
    }
    setFormStatus(String(r.status ?? 'draft'));
    setFormCustomer(r.customer_user_id ? String(r.customer_user_id) : '');
    setFormProject(r.project_id ? String(r.project_id) : '');
    setNotes(String(r.notes ?? ''));
    setFormErr(null);
    setOpen(true);
  }

  async function save() {
    setFormErr(null);
    try {
      const payload: Record<string, unknown> = {
        items: items.map((i) => ({
          description: i.desc,
          quantity: Number(i.qty),
          unit_price: Number(i.price),
        })),
        total,
        status: formStatus,
        notes: notes || null,
        customer_user_id: formCustomer ? Number(formCustomer) : null,
        project_id: formProject ? Number(formProject) : null,
      };
      if (editingId) {
        payload.id = editingId;
        await apiClient.put('/v1/projects/invoices', payload);
      } else {
        await apiClient.post('/v1/projects/invoices', payload);
      }
      setOpen(false);
      await load();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    }
  }

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle>پیش‌فاکتورها (CRM)</CardTitle>
        <Button type="button" size="sm" onClick={() => openCreate()}>
          فاکتور جدید
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap gap-2">
          <Select value={filterStatus || 'all'} onValueChange={(v) => setFilterStatus(v === 'all' ? '' : v)}>
            <SelectTrigger className="w-[140px]">
              <SelectValue placeholder="وضعیت" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">همه</SelectItem>
              <SelectItem value="draft">draft</SelectItem>
              <SelectItem value="sent">sent</SelectItem>
              <SelectItem value="paid">paid</SelectItem>
              <SelectItem value="cancelled">cancelled</SelectItem>
            </SelectContent>
          </Select>
          <Select value={customerUserId || 'all'} onValueChange={(v) => setCustomerUserId(v === 'all' ? '' : v)}>
            <SelectTrigger className="w-[200px]">
              <SelectValue placeholder="مشتری (کاربر)" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">همه مشتریان</SelectItem>
              {users.map((u) => (
                <SelectItem key={u.id} value={String(u.id)}>
                  {u.name ?? u.email ?? u.id}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select value={projectId || 'all'} onValueChange={(v) => setProjectId(v === 'all' ? '' : v)}>
            <SelectTrigger className="w-[200px]">
              <SelectValue placeholder="پروژه" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">همه پروژه‌ها</SelectItem>
              {projects.map((p) => (
                <SelectItem key={p.id} value={String(p.id)}>
                  {String(p.name ?? p.id)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button type="button" size="sm" variant="secondary" onClick={() => void load()}>
            اعمال فیلتر
          </Button>
        </div>

        {error ? <p className="text-destructive">{error}</p> : null}
        <div className="overflow-x-auto rounded border">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                <th className="px-2 py-2 text-start">#</th>
                <th className="px-2 py-2 text-start">شماره</th>
                <th className="px-2 py-2 text-start">وضعیت</th>
                <th className="px-2 py-2 text-start">جمع</th>
                <th className="px-2 py-2 text-start"> </th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={5} className="py-6 text-center">
                    …
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={String(r.id)} className="border-b">
                    <td className="px-2 py-2">{String(r.id)}</td>
                    <td className="px-2 py-2">{String(r.number ?? '—')}</td>
                    <td className="px-2 py-2">
                      <Badge variant="outline">{String(r.status ?? '—')}</Badge>
                    </td>
                    <td className="px-2 py-2">{String(r.total ?? '—')}</td>
                    <td className="px-2 py-2">
                      <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(r)}>
                        ویرایش
                      </Button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {(meta.last_page ?? 1) > 1 ? (
          <Pagination
            page={meta.current_page ?? page}
            pageCount={meta.last_page ?? 1}
            total={meta.total}
            onPageChange={setPage}
          />
        ) : null}
      </CardContent>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingId ? 'ویرایش فاکتور' : 'فاکتور با اقلام'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <div className="grid gap-2 sm:grid-cols-2">
              <Select value={formStatus} onValueChange={setFormStatus}>
                <SelectTrigger>
                  <SelectValue placeholder="وضعیت" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="draft">draft</SelectItem>
                  <SelectItem value="sent">sent</SelectItem>
                  <SelectItem value="paid">paid</SelectItem>
                  <SelectItem value="cancelled">cancelled</SelectItem>
                </SelectContent>
              </Select>
              <Select value={formCustomer || '__none'} onValueChange={(v) => setFormCustomer(v === '__none' ? '' : v)}>
                <SelectTrigger>
                  <SelectValue placeholder="مشتری (کاربر)" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none">—</SelectItem>
                  {users.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.name ?? u.email ?? u.id}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <Select value={formProject || '__none'} onValueChange={(v) => setFormProject(v === '__none' ? '' : v)}>
              <SelectTrigger>
                <SelectValue placeholder="پروژه" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">—</SelectItem>
                {projects.map((p) => (
                  <SelectItem key={p.id} value={String(p.id)}>
                    {String(p.name ?? p.id)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {items.map((it, idx) => (
              <div key={idx} className="grid grid-cols-3 gap-2">
                <Input
                  placeholder="شرح"
                  value={it.desc}
                  onChange={(e) => {
                    const n = [...items];
                    n[idx] = { ...it, desc: e.target.value };
                    setItems(n);
                  }}
                />
                <Input
                  placeholder="تعداد"
                  value={it.qty}
                  onChange={(e) => {
                    const n = [...items];
                    n[idx] = { ...it, qty: e.target.value };
                    setItems(n);
                  }}
                />
                <Input
                  placeholder="قیمت"
                  value={it.price}
                  onChange={(e) => {
                    const n = [...items];
                    n[idx] = { ...it, price: e.target.value };
                    setItems(n);
                  }}
                />
              </div>
            ))}
            <Button type="button" variant="outline" size="sm" onClick={() => setItems([...items, { desc: '', qty: '1', price: '0' }])}>
              + ردیف
            </Button>
            <Input placeholder="یادداشت" value={notes} onChange={(e) => setNotes(e.target.value)} />
            <p className="font-medium">جمع: {total}</p>
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void save()}>
              ذخیره
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
