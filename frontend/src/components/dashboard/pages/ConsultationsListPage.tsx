'use client';

import { useTranslations } from 'next-intl';
import { useLocale } from '@/hooks/use-locale';
import { Badge } from '@/components/ui/badge';

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
type StatusOption = { id: number; name: string; color?: string | null };

export function ConsultationsListPage() {
  const t = useTranslations('crm.consultations');
  const tCommon = useTranslations('common');
  const { formatDigits, isRtl } = useLocale();

  function statusLabel(value: unknown): string {
    const raw = String(value ?? '').trim();
    const key = raw.toLowerCase();
    const map: Record<string, string> = {
      new: t('statusNew'),
      open: t('statusOpen'),
      pending: t('statusPending'),
      done: t('statusDone'),
      completed: t('statusDone'),
      converted: t('statusConverted'),
      cancelled: t('statusCancelled'),
      canceled: t('statusCancelled'),
    };
    if (!raw) return tCommon('none');
    return map[key] ?? raw;
  }

  const [rows, setRows] = useState<Row[]>([]);
  const [accounts, setAccounts] = useState<AccountOption[]>([]);
  const [statuses, setStatuses] = useState<StatusOption[]>([]);
  const [statusFilter, setStatusFilter] = useState('');
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

  const loadStatuses = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/crm/consultation-statuses');
      const list = normalizeListPayload(res.data);
      setStatuses(
        list
          .map((row) => ({
            id: Number(row.id),
            name: String(row.name ?? ''),
            color: row.color != null ? String(row.color) : null,
          }))
          .filter((row) => row.name),
      );
    } catch {
      setStatuses([]);
    }
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/crm/consultations', {
        params: statusFilter ? { filter: { status: statusFilter } } : undefined,
      });
      const data = unwrapData<Row[]>(res);
      setRows(Array.isArray(data) ? data : []);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    void load();
    void loadAccounts();
    void loadStatuses();
  }, [load, loadAccounts, loadStatuses]);

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
        await apiClient.patch(`/v1/crm/consultations/${editId}`, payload);
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
    if (!confirm(t('convertConfirm'))) return;
    setBusy(true);
    try {
      const res = await apiClient.post(`/v1/crm/consultations/${id}/convert-project`);
      const data = unwrapData<{ project_id?: number }>(res);
      alert(tCommon('projectCreated', { id: formatDigits(data?.project_id ?? '') }));
      void load();
    } catch (e) {
      alert(getAxiosMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card dir={isRtl ? 'rtl' : 'ltr'}>
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle>{t('title')}</CardTitle>
        <Button type="button" size="sm" onClick={openCreate} disabled={busy}>
          {t('new')}
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-2">
          <label className="text-sm font-medium">{t('status')}</label>
          <Select value={statusFilter || '__all'} onValueChange={(value) => setStatusFilter(value === '__all' ? '' : value)}>
            <SelectTrigger className="w-[220px]">
              <SelectValue placeholder={t('allStatuses')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__all">{t('allStatuses')}</SelectItem>
              {statuses.map((item) => (
                <SelectItem key={item.id} value={item.name}>
                  {statusLabel(item.name)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <ResourceListCard
          title={t('listTitle')}
          description={t('description')}
          emptyText={t('empty')}
          loading={loading}
          error={error}
          rows={rows}
          columns={[
            { header: t('id'), cell: (r) => <span className="tabular-nums">{formatDigits(r.id as string | number)}</span> },
            { header: t('colTitle'), cell: (r) => String(r.title ?? tCommon('none')) },
            {
              header: t('account'),
              cell: (r) => {
                const acc = r.account as Record<string, unknown> | undefined;
                if (acc?.name) return String(acc.name);
                if (r.account_id) return formatDigits(r.account_id as string | number);
                return t('noAccount');
              },
            },
            {
              header: t('status'),
              cell: (r) => <Badge variant="secondary">{statusLabel(r.status)}</Badge>,
            },
            {
              header: t('actions'),
              cell: (r) => (
                <div className="flex flex-wrap gap-1">
                  <Button type="button" variant="outline" size="sm" onClick={() => openEdit(r)}>
                    {tCommon('edit')}
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={busy}
                    onClick={() => void convertToProject(Number(r.id))}
                  >
                    {t('convert')}
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
              {editId ? t('edit') : t('new')}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 py-2">
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
            <label className="text-sm font-medium">{t('colTitle')}</label>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder={t('colTitle')} />
            <label className="text-sm font-medium">{t('accountOptional')}</label>
            <Select value={accountId || '__none'} onValueChange={(v) => setAccountId(v === '__none' ? '' : v)}>
              <SelectTrigger>
                <SelectValue placeholder={t('accountOptional')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">{t('noAccount')}</SelectItem>
                {accounts.map((a) => (
                  <SelectItem key={a.id} value={String(a.id)}>
                    {a.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <label className="text-sm font-medium">{t('status')}</label>
            <Select value={status || '__none'} onValueChange={(value) => setStatus(value === '__none' ? '' : value)}>
              <SelectTrigger>
                <SelectValue placeholder={t('status')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">{tCommon('none')}</SelectItem>
                {statuses.map((item) => (
                  <SelectItem key={item.id} value={item.name}>
                    {statusLabel(item.name)}
                  </SelectItem>
                ))}
                {status && !statuses.some((item) => item.name === status) ? (
                  <SelectItem value={status}>{statusLabel(status)}</SelectItem>
                ) : null}
              </SelectContent>
            </Select>
            <label className="text-sm font-medium">{t('notes')}</label>
            <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={4} />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button type="button" onClick={() => void saveConsultation()} disabled={busy || !title.trim()}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
