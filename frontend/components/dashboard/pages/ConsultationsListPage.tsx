'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { ResourceListCard } from '@/components/dashboard/ResourceListCard';

type Row = Record<string, unknown>;

export function ConsultationsListPage() {
  const [rows, setRows] = useState<Row[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [title, setTitle] = useState('');
  const [accountId, setAccountId] = useState('');
  const [status, setStatus] = useState('');
  const [notes, setNotes] = useState('');
  const [formErr, setFormErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/crm/consultations');
      const data = unwrapData<Row[]>(res);
      setRows(Array.isArray(data) ? data : []);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function openCreate() {
    setEditId(null);
    setTitle('');
    setAccountId('');
    setStatus('');
    setNotes('');
    setFormErr(null);
    setDialogOpen(true);
  }

  function openEdit(row: Row) {
    setEditId(Number(row.id));
    setTitle(String(row.title ?? ''));
    setAccountId(row.account_id != null ? String(row.account_id) : '');
    setStatus(String(row.status ?? ''));
    setNotes(String(row.notes ?? ''));
    setFormErr(null);
    setDialogOpen(true);
  }

  async function saveConsultation() {
    setFormErr(null);
    setBusy(true);
    try {
      const payload: Record<string, unknown> = {
        title: title.trim(),
        notes: notes.trim() || null,
        status: status.trim() || null,
      };
      if (accountId.trim()) {
        payload.account_id = Number(accountId);
      }
      if (editId) {
        payload.id = editId;
        await apiClient.put('/v1/crm/consultations', payload);
      } else {
        await apiClient.post('/v1/crm/consultations', payload);
      }
      setDialogOpen(false);
      void load();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function convertToProject(id: number) {
    if (!confirm('این مشاوره به پروژه تبدیل شود؟')) return;
    setBusy(true);
    try {
      const res = await apiClient.post(`/v1/crm/consultations/${id}/convert-project`);
      const data = unwrapData<{ project_id?: number }>(res);
      alert(`پروژه ایجاد شد: #${String(data?.project_id ?? '')}`);
      void load();
    } catch (e) {
      alert(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle>مشاوره‌ها</CardTitle>
        <Button type="button" size="sm" onClick={openCreate} disabled={busy}>
          مشاوره جدید
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <ResourceListCard
          title="لیست"
          description="GET /api/v1/crm/consultations"
          loading={loading}
          error={error}
          rows={rows}
          columns={[
            { header: 'شناسه', cell: (r) => String(r.id ?? '—') },
            { header: 'عنوان', cell: (r) => String(r.title ?? '—') },
            {
              header: 'حساب',
              cell: (r) => {
                const acc = r.account as Record<string, unknown> | undefined;
                return String(acc?.name ?? r.account_id ?? '—');
              },
            },
            { header: 'وضعیت', cell: (r) => String(r.status ?? '—') },
            {
              header: 'عملیات',
              cell: (r) => (
                <div className="flex flex-wrap gap-1">
                  <Button type="button" variant="outline" size="sm" onClick={() => openEdit(r)}>
                    ویرایش
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={busy}
                    onClick={() => void convertToProject(Number(r.id))}
                  >
                    تبدیل به پروژه
                  </Button>
                </div>
              ),
            },
          ]}
        />
      </CardContent>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{editId ? 'ویرایش مشاوره' : 'مشاوره جدید'}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 py-2">
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
            <label className="text-sm font-medium">عنوان</label>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="عنوان" />
            <label className="text-sm font-medium">شناسه حساب (اختیاری)</label>
            <Input
              value={accountId}
              onChange={(e) => setAccountId(e.target.value)}
              placeholder="crm_accounts.id"
              dir="ltr"
            />
            <label className="text-sm font-medium">وضعیت</label>
            <Input value={status} onChange={(e) => setStatus(e.target.value)} />
            <label className="text-sm font-medium">یادداشت</label>
            <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={4} />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
              انصراف
            </Button>
            <Button type="button" onClick={() => void saveConsultation()} disabled={busy || !title.trim()}>
              ذخیره
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
