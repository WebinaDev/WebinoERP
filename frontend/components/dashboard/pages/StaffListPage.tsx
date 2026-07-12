'use client';

import { useMemo, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { useApiList } from '@/hooks/useApiList';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { ResourceListCard } from '@/components/dashboard/ResourceListCard';

export function StaffListPage() {
  const [search, setSearch] = useState('');
  const endpoint = useMemo(() => {
    const q = search.trim();
    return `/v1/core/users?per_page=50${q ? `&search=${encodeURIComponent(q)}` : ''}`;
  }, [search]);
  const { rows, error, loading, reload } = useApiList(endpoint);

  const [createOpen, setCreateOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [editId, setEditId] = useState<number | null>(null);
  const [formErr, setFormErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function openCreate() {
    setEditId(null);
    setName('');
    setEmail('');
    setPassword('');
    setFormErr(null);
    setCreateOpen(true);
  }

  function openEdit(row: Record<string, unknown>) {
    setEditId(Number(row.id));
    setName(String(row.name ?? ''));
    setEmail(String(row.email ?? ''));
    setPassword('');
    setFormErr(null);
    setEditOpen(true);
  }

  async function saveCreate() {
    setFormErr(null);
    setBusy(true);
    try {
      await apiClient.post('/v1/core/users', {
        name: name.trim(),
        email: email.trim(),
        password,
      });
      setCreateOpen(false);
      void reload();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function saveEdit() {
    if (!editId) return;
    setFormErr(null);
    setBusy(true);
    try {
      const payload: Record<string, string> = {
        name: name.trim(),
        email: email.trim(),
      };
      if (password.trim()) {
        payload.password = password;
      }
      await apiClient.patch(`/v1/core/users/${editId}`, payload);
      setEditOpen(false);
      void reload();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function confirmDelete() {
    if (!deleteId) return;
    setBusy(true);
    try {
      await apiClient.delete(`/v1/core/users/${deleteId}`);
      setDeleteId(null);
      void reload();
    } catch (e) {
      alert(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <CardTitle>کارکنان</CardTitle>
        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
          <Input
            placeholder="جستجو نام یا ایمیل…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="sm:w-56"
          />
          <Button type="button" size="sm" onClick={openCreate}>
            کاربر جدید
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <ResourceListCard
          title="لیست"
          description="GET /api/v1/core/users"
          loading={loading}
          error={error}
          rows={rows}
          columns={[
            { header: 'شناسه', cell: (r) => String(r.id ?? '—') },
            { header: 'نام', cell: (r) => String(r.name ?? '—') },
            { header: 'ایمیل', cell: (r) => String(r.email ?? '—') },
            {
              header: 'عملیات',
              cell: (r) => (
                <div className="flex flex-wrap gap-1">
                  <Button type="button" variant="outline" size="sm" onClick={() => openEdit(r)}>
                    ویرایش
                  </Button>
                  <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    onClick={() => setDeleteId(Number(r.id))}
                  >
                    حذف
                  </Button>
                </div>
              ),
            },
          ]}
        />
      </CardContent>

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>کاربر جدید</DialogTitle>
          </DialogHeader>
          <div className="grid gap-2 py-2">
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
            <label className="text-sm font-medium">نام</label>
            <Input value={name} onChange={(e) => setName(e.target.value)} />
            <label className="text-sm font-medium">ایمیل</label>
            <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} dir="ltr" />
            <label className="text-sm font-medium">رمز عبور</label>
            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} dir="ltr" />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
              انصراف
            </Button>
            <Button
              type="button"
              onClick={() => void saveCreate()}
              disabled={busy || !name.trim() || !email.trim() || password.length < 8}
            >
              ایجاد
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>ویرایش کاربر</DialogTitle>
          </DialogHeader>
          <div className="grid gap-2 py-2">
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
            <label className="text-sm font-medium">نام</label>
            <Input value={name} onChange={(e) => setName(e.target.value)} />
            <label className="text-sm font-medium">ایمیل</label>
            <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} dir="ltr" />
            <label className="text-sm font-medium">رمز جدید (اختیاری)</label>
            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} dir="ltr" />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
              انصراف
            </Button>
            <Button type="button" onClick={() => void saveEdit()} disabled={busy}>
              ذخیره
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف کاربر؟</AlertDialogTitle>
            <AlertDialogDescription>این عمل قابل بازگشت نیست.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>انصراف</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>حذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}
