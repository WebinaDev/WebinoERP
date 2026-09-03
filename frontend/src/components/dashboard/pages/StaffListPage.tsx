'use client';

import { useTranslations } from 'next-intl';

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
  const t = useTranslations();

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
        <CardTitle>{t('auto.StaffListPage.s_d784fd69')}</CardTitle>
        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
          <Input
            placeholder={t('auto.StaffListPage.s_76963217')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="sm:w-56"
          />
          <Button type="button" size="sm" onClick={openCreate}>
            {t('auto.StaffListPage.s_9e3d546d')}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <ResourceListCard
          title={t('auto.StaffListPage.s_11fe927c')}
          description="GET /api/v1/core/users"
          loading={loading}
          error={error}
          rows={rows}
          columns={[
            { header: t('auto.StaffListPage.s_acc84041'), cell: (r) => String(r.id ?? '—') },
            { header: t('auto.StaffListPage.s_45dd06ba'), cell: (r) => String(r.name ?? '—') },
            { header: t('auto.StaffListPage.s_f1ad423d'), cell: (r) => String(r.email ?? '—') },
            {
              header: t('auto.StaffListPage.s_8d1cc546'),
              cell: (r) => (
                <div className="flex flex-wrap gap-1">
                  <Button type="button" variant="outline" size="sm" onClick={() => openEdit(r)}>
                    {t('auto.StaffListPage.s_ac60ae7a')}
                  </Button>
                  <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    onClick={() => setDeleteId(Number(r.id))}>
                  
                    {t('auto.StaffListPage.s_2d2bbdc2')}
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
            <DialogTitle>{t('auto.StaffListPage.s_9e3d546d')}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-2 py-2">
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
            <label className="text-sm font-medium">{t('auto.StaffListPage.s_45dd06ba')}</label>
            <Input value={name} onChange={(e) => setName(e.target.value)} />
            <label className="text-sm font-medium">{t('auto.StaffListPage.s_f1ad423d')}</label>
            <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} dir="ltr" />
            <label className="text-sm font-medium">{t('auto.StaffListPage.s_9cf5bce9')}</label>
            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} dir="ltr" />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>
              {t('auto.StaffListPage.s_106dfb4e')}
            </Button>
            <Button
              type="button"
              onClick={() => void saveCreate()}
              disabled={busy || !name.trim() || !email.trim() || password.length < 8}>
            
              {t('auto.StaffListPage.s_15802062')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('auto.StaffListPage.s_f70d7961')}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-2 py-2">
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
            <label className="text-sm font-medium">{t('auto.StaffListPage.s_45dd06ba')}</label>
            <Input value={name} onChange={(e) => setName(e.target.value)} />
            <label className="text-sm font-medium">{t('auto.StaffListPage.s_f1ad423d')}</label>
            <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} dir="ltr" />
            <label className="text-sm font-medium">{t('auto.StaffListPage.s_9380940a')}</label>
            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} dir="ltr" />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
              {t('auto.StaffListPage.s_106dfb4e')}
            </Button>
            <Button type="button" onClick={() => void saveEdit()} disabled={busy}>
              {t('auto.StaffListPage.s_08545fb6')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('auto.StaffListPage.s_0aadf39a')}</AlertDialogTitle>
            <AlertDialogDescription>{t('auto.StaffListPage.s_23c56c8e')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('auto.StaffListPage.s_106dfb4e')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>{t('auto.StaffListPage.s_2d2bbdc2')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}
