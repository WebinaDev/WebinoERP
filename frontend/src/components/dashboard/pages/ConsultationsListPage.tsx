'use client';

import { useTranslations } from 'next-intl';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ResourceListCard } from '@/components/dashboard/ResourceListCard';

type Row = Record<string, unknown>;
type AccountOption = { id: number; name: string };

export function ConsultationsListPage() {
  const t = useTranslations();

  const [rows, setRows] = useState<Row[]>([]);
  const [accounts, setAccounts] = useState<AccountOption[]>([]);
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

  const loadAccounts = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/crm/accounts', { params: { per_page: 50 } });
      const list = normalizeListPayload(res.data);
      setAccounts(
        list
          .map((a) => ({ id: Number(a.id), name: String(a.name ?? `#${a.id}`) }))
          .filter((a) => Number.isFinite(a.id)),
      );
    } catch {
      setAccounts([]);
    }
  }, []);

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
    void loadAccounts();
  }, [load, loadAccounts]);

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
    if (!confirm(t('auto.ConsultationsListPage.s_b0722d14'))) return;
    setBusy(true);
    try {
      const res = await apiClient.post(`/v1/crm/consultations/${id}/convert-project`);
      const data = unwrapData<{ project_id?: number }>(res);
      alert(t('common.projectCreated', { id: String(data?.project_id ?? '') }));
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
        <CardTitle>{t('auto.ConsultationsListPage.s_47aa49fb')}</CardTitle>
        <Button type="button" size="sm" onClick={openCreate} disabled={busy}>
          {t('auto.ConsultationsListPage.s_bb4a3d56')}
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <ResourceListCard
          title={t('auto.ConsultationsListPage.s_11fe927c')}
          description="GET /api/v1/crm/consultations"
          loading={loading}
          error={error}
          rows={rows}
          columns={[
            { header: t('auto.ConsultationsListPage.s_acc84041'), cell: (r) => String(r.id ?? '—') },
            { header: t('auto.ConsultationsListPage.s_1a9bdb20'), cell: (r) => String(r.title ?? '—') },
            {
              header: t('auto.ConsultationsListPage.s_fdadd003'),
              cell: (r) => {
                const acc = r.account as Record<string, unknown> | undefined;
                return String(acc?.name ?? r.account_id ?? '—');
              },
            },
            { header: t('auto.ConsultationsListPage.s_55518965'), cell: (r) => String(r.status ?? '—') },
            {
              header: t('auto.ConsultationsListPage.s_8d1cc546'),
              cell: (r) => (
                <div className="flex flex-wrap gap-1">
                  <Button type="button" variant="outline" size="sm" onClick={() => openEdit(r)}>
                    {t('auto.ConsultationsListPage.s_ac60ae7a')}
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={busy}
                    onClick={() => void convertToProject(Number(r.id))}
                  >
                    {t('auto.ConsultationsListPage.s_1e2039cf')}
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
            <DialogTitle>
              {editId ? t('auto.ConsultationsListPage.s_b3112c0c') : t('auto.ConsultationsListPage.s_bb4a3d56')}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 py-2">
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
            <label className="text-sm font-medium">{t('auto.ConsultationsListPage.s_1a9bdb20')}</label>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder={t('auto.ConsultationsListPage.s_1a9bdb20')} />
            <label className="text-sm font-medium">{t('auto.ConsultationsListPage.s_abe20085')}</label>
            <Select value={accountId || '__none'} onValueChange={(v) => setAccountId(v === '__none' ? '' : v)}>
              <SelectTrigger>
                <SelectValue placeholder={t('auto.ConsultationsListPage.s_abe20085')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">—</SelectItem>
                {accounts.map((a) => (
                  <SelectItem key={a.id} value={String(a.id)}>
                    {a.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <label className="text-sm font-medium">{t('auto.ConsultationsListPage.s_55518965')}</label>
            <Input value={status} onChange={(e) => setStatus(e.target.value)} />
            <label className="text-sm font-medium">{t('auto.ConsultationsListPage.s_2c09d41c')}</label>
            <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={4} />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
              {t('auto.ConsultationsListPage.s_106dfb4e')}
            </Button>
            <Button type="button" onClick={() => void saveConsultation()} disabled={busy || !title.trim()}>
              {t('auto.ConsultationsListPage.s_08545fb6')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
