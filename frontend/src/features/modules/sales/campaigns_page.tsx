'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmConfirmDialog, PmEmptyState, PmFilterBar } from '@/features/shared/pm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

type Campaign = Record<string, unknown> & {
  id: number;
  name?: string;
  description?: string | null;
  status?: string;
  channel?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
};

type FormState = {
  name: string;
  description: string;
  channel: string;
  status: string;
  starts_at: string;
  ends_at: string;
};

const emptyForm = (): FormState => ({
  name: '',
  description: '',
  channel: 'web',
  status: 'draft',
  starts_at: '',
  ends_at: '',
});

const CHANNELS = ['web', 'email', 'sms', 'social', 'other'] as const;
const STATUSES = ['draft', 'active', 'paused', 'ended'] as const;

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
  draft: 'secondary',
  active: 'default',
  paused: 'outline',
  ended: 'destructive',
};

export function CampaignsPage() {
  const t = useTranslations('sales.campaigns');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, setError, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Campaign[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [channelFilter, setChannelFilter] = useState('');
  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm());
  const [submitting, setSubmitting] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Campaign | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/sales/campaigns', { params: { per_page: 100 } });
      let list = normalizeListPayload(res.data) as Campaign[];
      if (search.trim()) {
        const q = search.trim().toLowerCase();
        list = list.filter(
          (c) =>
            String(c.name ?? '')
              .toLowerCase()
              .includes(q) ||
            String(c.description ?? '')
              .toLowerCase()
              .includes(q),
        );
      }
      if (statusFilter) list = list.filter((c) => c.status === statusFilter);
      if (channelFilter) list = list.filter((c) => c.channel === channelFilter);
      setRows(list);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, search, statusFilter, channelFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  const openNew = () => {
    setEditingId(null);
    setForm(emptyForm());
    setOpen(true);
  };

  const openEdit = (c: Campaign) => {
    setEditingId(c.id);
    setForm({
      name: String(c.name ?? ''),
      description: String(c.description ?? ''),
      channel: String(c.channel ?? 'web'),
      status: String(c.status ?? 'draft'),
      starts_at: String(c.starts_at ?? '').slice(0, 10),
      ends_at: String(c.ends_at ?? '').slice(0, 10),
    });
    setOpen(true);
  };

  const save = async () => {
    if (!form.name.trim()) {
      setError(t('nameRequired'));
      return;
    }
    if (form.starts_at && form.ends_at && form.ends_at < form.starts_at) {
      setError(t('dateInvalid'));
      return;
    }
    setSubmitting(true);
    try {
      const payload = {
        name: form.name.trim(),
        description: form.description.trim() || null,
        channel: form.channel,
        status: form.status,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
      };
      if (editingId) {
        await apiClient.patch(`/v1/sales/campaigns/${editingId}`, payload);
      } else {
        await apiClient.post('/v1/sales/campaigns', payload);
      }
      setOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    setSubmitting(true);
    try {
      await apiClient.delete(`/v1/sales/campaigns/${deleteTarget.id}`);
      setDeleteTarget(null);
      setSuccess(tNav('common.deleted'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.sales.campaigns')}
      actions={
        <Button onClick={openNew}>
          <Plus className="size-4 me-2" />
          {t('newCampaign')}
        </Button>
      }
      {...layoutProps}
    >
      <PmFilterBar onApply={() => void load()} applyLabel={tNav('common.apply')}>
        <Input
          className="max-w-[220px]"
          placeholder={tNav('common.search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <Select value={statusFilter || 'all'} onValueChange={(v) => setStatusFilter(v === 'all' ? '' : v)}>
          <SelectTrigger className="w-[140px]">
            <SelectValue placeholder={t('status')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{tNav('common.all')}</SelectItem>
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {t(`status_${s}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={channelFilter || 'all'} onValueChange={(v) => setChannelFilter(v === 'all' ? '' : v)}>
          <SelectTrigger className="w-[140px]">
            <SelectValue placeholder={t('channel')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{tNav('common.all')}</SelectItem>
            {CHANNELS.map((ch) => (
              <SelectItem key={ch} value={ch}>
                {t(`channel_${ch}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </PmFilterBar>

      <Card>
        <CardContent className="pt-6">
          {loading ? (
            <div className="flex justify-center py-10">
              <Loader2 className="size-6 animate-spin text-muted-foreground" />
            </div>
          ) : rows.length === 0 ? (
            <PmEmptyState title={t('empty')} />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('name')}</TableHead>
                  <TableHead>{t('channel')}</TableHead>
                  <TableHead>{t('status')}</TableHead>
                  <TableHead>{t('startDate')}</TableHead>
                  <TableHead>{t('endDate')}</TableHead>
                  <TableHead className="text-end">{tNav('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">{String(c.name ?? '')}</TableCell>
                    <TableCell>{c.channel ? t(`channel_${String(c.channel)}`) : '—'}</TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANT[String(c.status)] ?? 'secondary'}>
                        {c.status ? t(`status_${String(c.status)}`) : '—'}
                      </Badge>
                    </TableCell>
                    <TableCell>{String(c.starts_at ?? '').slice(0, 10) || '—'}</TableCell>
                    <TableCell>{String(c.ends_at ?? '').slice(0, 10) || '—'}</TableCell>
                    <TableCell className="text-end">
                      <div className="flex justify-end gap-2">
                        <Button size="sm" variant="outline" onClick={() => openEdit(c)}>
                          <Pencil className="size-4" />
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          className="text-destructive"
                          onClick={() => setDeleteTarget(c)}
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingId ? t('editCampaign') : t('newCampaign')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>{t('name')} *</Label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t('content')}</Label>
              <Textarea
                rows={4}
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>{t('channel')}</Label>
                <Select value={form.channel} onValueChange={(v) => setForm((f) => ({ ...f, channel: v }))}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {CHANNELS.map((ch) => (
                      <SelectItem key={ch} value={ch}>
                        {t(`channel_${ch}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t('status')}</Label>
                <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {STATUSES.map((s) => (
                      <SelectItem key={s} value={s}>
                        {t(`status_${s}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>{t('startDate')}</Label>
                <LocaleDatePicker
                  value={form.starts_at}
                  onChange={(v) => setForm((f) => ({ ...f, starts_at: v }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t('endDate')}</Label>
                <LocaleDatePicker
                  value={form.ends_at}
                  onChange={(v) => setForm((f) => ({ ...f, ends_at: v }))}
                />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>
              {tNav('common.cancel')}
            </Button>
            <Button onClick={() => void save()} disabled={submitting}>
              {submitting ? <Loader2 className="size-4 animate-spin me-2" /> : null}
              {tNav('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteTarget !== null}
        title={tNav('common.delete')}
        description={t('confirmDelete')}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteTarget(null)}
        pending={submitting}
      />
    </CrmPageLayout>
  );
}
