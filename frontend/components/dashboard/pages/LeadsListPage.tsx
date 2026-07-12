'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { Textarea } from '@/components/ui/textarea';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { toast } from 'sonner';
import { PageEmptyState, PageErrorState, PageLoadingState } from '@/features/shared/ui/PageStates';

type StatusRow = { id: number; name: string; color?: string };
type LeadRow = Record<string, unknown>;
type DuplicateRow = { id: number; topic?: string; email?: string; confidence: number; reasons?: string[] };
type Meta = { current_page?: number; last_page?: number; total?: number };

export function LeadsListPage() {
  const locale = useLocale();
  const t = useTranslations('crm.leads');
  const tc = useTranslations('common');

  const [rows, setRows] = useState<LeadRow[]>([]);
  const [statuses, setStatuses] = useState<StatusRow[]>([]);
  const [assignees, setAssignees] = useState<{ id: number; name: string }[]>([]);
  const [meta, setMeta] = useState<Meta>({});
  const [page, setPage] = useState(1);
  const [filterStatus, setFilterStatus] = useState<string>('');
  const [search, setSearch] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<Set<number>>(new Set());

  const [addOpen, setAddOpen] = useState(false);
  const [detail, setDetail] = useState<LeadRow | null>(null);
  const [assignOpen, setAssignOpen] = useState(false);
  const [assignUserId, setAssignUserId] = useState<string>('');
  const [convertOpen, setConvertOpen] = useState(false);
  const [convertDeal, setConvertDeal] = useState(false);
  const [duplicatesOpen, setDuplicatesOpen] = useState(false);
  const [duplicates, setDuplicates] = useState<DuplicateRow[]>([]);
  const [bulkAssignOpen, setBulkAssignOpen] = useState(false);
  const [bulkAssignUserId, setBulkAssignUserId] = useState('');

  const [form, setForm] = useState({
    topic: '',
    first_name: '',
    last_name: '',
    mobile: '',
    status_id: '',
    description: '',
  });

  const loadStatuses = useCallback(async () => {
    const res = await apiClient.get('/v1/crm/statuses');
    const body = res.data as { data?: StatusRow[] };
    setStatuses(Array.isArray(body.data) ? body.data : []);
  }, []);

  const loadAssignees = useCallback(async () => {
    const res = await apiClient.get('/v1/crm/leads/assignees');
    const body = res.data as { data?: { id: number; name: string }[] };
    setAssignees(Array.isArray(body.data) ? body.data : []);
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/crm/leads', {
        params: {
          page,
          per_page: 15,
          search: search || undefined,
          'filter[status_id]': filterStatus || undefined,
        },
      });
      const body = res.data as { data?: unknown; meta?: Meta };
      setRows(normalizeListPayload(body));
      setMeta(body.meta ?? {});
      setSelected(new Set());
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [page, search, filterStatus]);

  useEffect(() => {
    void loadStatuses();
    void loadAssignees();
  }, [loadStatuses, loadAssignees]);

  useEffect(() => {
    void load();
  }, [load]);

  function toggleRow(id: number, checked: boolean) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (checked) next.add(id);
      else next.delete(id);
      return next;
    });
  }

  async function submitLead(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await apiClient.post('/v1/crm/leads', {
        topic: form.topic,
        first_name: form.first_name,
        last_name: form.last_name,
        mobile: form.mobile,
        status_id: Number(form.status_id),
        description: form.description || undefined,
      });
      setAddOpen(false);
      setForm({ topic: '', first_name: '', last_name: '', mobile: '', status_id: '', description: '' });
      toast.success(tc('saved'));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function submitAssign() {
    if (!detail?.id || !assignUserId) return;
    setError(null);
    try {
      await apiClient.patch(`/v1/crm/leads/${detail.id}/assign`, {
        assigned_to: assignUserId === '__none' ? null : Number(assignUserId),
      });
      setAssignOpen(false);
      toast.success(tc('saved'));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function submitConvert() {
    if (!detail?.id) return;
    try {
      await apiClient.post(`/v1/crm/leads/${detail.id}/convert`, {
        create_contact: true,
        create_deal: convertDeal,
      });
      setConvertOpen(false);
      toast.success(t('convertSuccess'));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function openDuplicates(lead: LeadRow) {
    setDetail(lead);
    setError(null);
    try {
      const res = await apiClient.get(`/v1/crm/leads/${lead.id}/duplicates`);
      const body = res.data as { data?: DuplicateRow[] };
      setDuplicates(Array.isArray(body.data) ? body.data : []);
      setDuplicatesOpen(true);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function mergeDuplicate(dup: DuplicateRow) {
    if (!detail?.id) return;
    try {
      await apiClient.post('/v1/crm/leads/merge', {
        primary_id: detail.id,
        duplicate_id: dup.id,
      });
      setDuplicatesOpen(false);
      toast.success(t('mergeSuccess'));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function submitBulkAssign() {
    if (selected.size === 0 || !bulkAssignUserId) {
      toast.error(t('selectRows'));
      return;
    }
    try {
      await apiClient.post('/v1/crm/leads/bulk-assign', {
        ids: Array.from(selected),
        assigned_to: Number(bulkAssignUserId),
      });
      setBulkAssignOpen(false);
      toast.success(t('bulkAssignSuccess'));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function bulkDelete() {
    if (selected.size === 0) {
      toast.error(t('selectRows'));
      return;
    }
    try {
      await apiClient.post('/v1/crm/leads/bulk-delete', { ids: Array.from(selected) });
      toast.success(t('bulkDeleteSuccess'));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  const lastPage = meta.last_page ?? 1;
  const total = meta.total;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
          <CardTitle className="text-base">{t('title')}</CardTitle>
          <div className="flex flex-wrap items-center gap-2">
            <Input
              placeholder={t('searchPlaceholder')}
              className="w-44"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && void load()}
            />
            <Select value={filterStatus || 'all'} onValueChange={(v) => setFilterStatus(v === 'all' ? '' : v)}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder={t('status')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('allStatuses')}</SelectItem>
                {statuses.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>
                    {s.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button type="button" size="sm" variant="secondary" onClick={() => void load()}>
              {t('apply')}
            </Button>
            {selected.size > 0 ? (
              <>
                <Button type="button" size="sm" variant="outline" onClick={() => setBulkAssignOpen(true)}>
                  {t('bulkAssign')} ({selected.size})
                </Button>
                <Button type="button" size="sm" variant="destructive" onClick={() => void bulkDelete()}>
                  {t('bulkDelete')}
                </Button>
              </>
            ) : null}
            <Button type="button" size="sm" onClick={() => setAddOpen(true)}>
              {t('newLead')}
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {error ? <PageErrorState message={error} onRetry={() => void load()} /> : null}
          {loading ? (
            <PageLoadingState />
          ) : rows.length === 0 ? (
            <PageEmptyState actionLabel={t('newLead')} onAction={() => setAddOpen(true)} />
          ) : (
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full min-w-[720px] text-sm">
                <thead>
                  <tr className="border-b bg-muted/40 text-muted-foreground">
                    <th className="w-10 px-2 py-2" aria-label={tc('select')} />
                    <th className="px-3 py-2 text-start">{t('topic')}</th>
                    <th className="px-3 py-2 text-start">{t('firstName')}</th>
                    <th className="px-3 py-2 text-start">{t('mobile')}</th>
                    <th className="px-3 py-2 text-start">{t('status')}</th>
                    <th className="px-3 py-2 text-start">{t('score')}</th>
                    <th className="px-3 py-2 text-start">{t('actions')}</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r) => {
                    const id = Number(r.id);
                    const st = r.status as { name?: string; color?: string } | undefined;
                    return (
                      <tr key={String(r.id)} className="border-b border-border/60">
                        <td className="px-2 py-2">
                          <Checkbox
                            checked={selected.has(id)}
                            onCheckedChange={(c) => toggleRow(id, c === true)}
                            aria-label={`${t('topic')} ${String(r.topic ?? id)}`}
                          />
                        </td>
                        <td className="px-3 py-2">{String(r.topic ?? '—')}</td>
                        <td className="px-3 py-2">
                          {String(r.first_name ?? '')} {String(r.last_name ?? '')}
                        </td>
                        <td className="px-3 py-2" dir="ltr">
                          {String(r.mobile ?? '—')}
                        </td>
                        <td className="px-3 py-2">
                          {st?.name ? (
                            <Badge variant="secondary" style={st.color ? { borderColor: st.color } : undefined}>
                              {st.name}
                            </Badge>
                          ) : (
                            '—'
                          )}
                        </td>
                        <td className="px-3 py-2">{String(r.lead_score ?? r.score ?? '—')}</td>
                        <td className="px-3 py-2">
                          <div className="flex flex-wrap gap-1">
                            <Button type="button" variant="outline" size="sm" onClick={() => setDetail(r)}>
                              {t('details')}
                            </Button>
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() => {
                                setDetail(r);
                                const aid = r.assigned_to;
                                setAssignUserId(aid != null && aid !== '' ? String(aid) : '__none');
                                setAssignOpen(true);
                              }}
                            >
                              {t('assign')}
                            </Button>
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() => {
                                setDetail(r);
                                setConvertDeal(false);
                                setConvertOpen(true);
                              }}
                            >
                              {t('convert')}
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={() => void openDuplicates(r)}>
                              {t('duplicates')}
                            </Button>
                            <Link href={`/dashboard/contracts?lead=${String(r.id)}`} className="inline-flex">
                              <Button type="button" variant="ghost" size="sm">
                                {t('contract')}
                              </Button>
                            </Link>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
          {!loading && rows.length > 0 ? (
            <Pagination page={meta.current_page ?? page} pageCount={lastPage} total={total} onPageChange={setPage} />
          ) : null}
        </CardContent>
      </Card>

      <Dialog open={addOpen} onOpenChange={setAddOpen}>
        <DialogContent className="max-w-lg">
          <form onSubmit={submitLead}>
            <DialogHeader>
              <DialogTitle>{t('addLead')}</DialogTitle>
              <DialogDescription>{t('addLeadHint')}</DialogDescription>
            </DialogHeader>
            <div className="grid gap-3 py-4">
              <div className="space-y-1">
                <label className="text-sm">{t('topic')}</label>
                <Input value={form.topic} onChange={(e) => setForm((f) => ({ ...f, topic: e.target.value }))} required />
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div className="space-y-1">
                  <label className="text-sm">{t('firstName')}</label>
                  <Input
                    value={form.first_name}
                    onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))}
                    required
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-sm">{t('lastName')}</label>
                  <Input
                    value={form.last_name}
                    onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))}
                    required
                  />
                </div>
              </div>
              <div className="space-y-1">
                <label className="text-sm">{t('mobile')}</label>
                <Input value={form.mobile} onChange={(e) => setForm((f) => ({ ...f, mobile: e.target.value }))} required />
              </div>
              <div className="space-y-1">
                <label className="text-sm">{t('status')}</label>
                <Select value={form.status_id} onValueChange={(v) => setForm((f) => ({ ...f, status_id: v }))}>
                  <SelectTrigger>
                    <SelectValue placeholder={t('selectStatus')} />
                  </SelectTrigger>
                  <SelectContent>
                    {statuses.map((s) => (
                      <SelectItem key={s.id} value={String(s.id)}>
                        {s.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <label className="text-sm">{t('description')}</label>
                <Textarea value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
              </div>
            </div>
            <DialogFooter>
              <Button type="submit">{t('save')}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={!!detail && !assignOpen && !convertOpen && !duplicatesOpen} onOpenChange={(o) => !o && setDetail(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('detailTitle')}</DialogTitle>
          </DialogHeader>
          {detail ? (
            <div className="space-y-2 text-sm">
              <p>
                <strong>{t('topic')}:</strong> {String(detail.topic)}
              </p>
              <p>
                <strong>{t('firstName')}:</strong> {String(detail.first_name ?? '')} {String(detail.last_name ?? '')}
              </p>
              <p>
                <strong>{t('mobile')}:</strong> {String(detail.mobile)}
              </p>
              <p>
                <strong>{t('email')}:</strong> {String(detail.email ?? '—')}
              </p>
              <p>
                <strong>{t('source')}:</strong> {String(detail.source ?? '—')}
              </p>
              <p>
                <strong>{t('company')}:</strong> {String(detail.company ?? '—')}
              </p>
              <p>
                <strong>{t('description')}:</strong> {String(detail.description ?? '—')}
              </p>
              <p>
                <strong>{t('createdAt')}:</strong> {String(detail.created_at ?? '—')}
              </p>
              <p>
                <strong>{t('assignee')}:</strong>{' '}
                {(() => {
                  const at = detail.assigned_to;
                  const u = assignees.find((x) => String(x.id) === String(at));
                  return u?.name ?? (at != null ? String(at) : '—');
                })()}
              </p>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={assignOpen} onOpenChange={setAssignOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('assignTitle')}</DialogTitle>
          </DialogHeader>
          <Select value={assignUserId} onValueChange={setAssignUserId}>
            <SelectTrigger>
              <SelectValue placeholder={t('user')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__none">{t('noAssignee')}</SelectItem>
              {assignees.map((u) => (
                <SelectItem key={u.id} value={String(u.id)}>
                  {u.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <DialogFooter>
            <Button type="button" onClick={() => void submitAssign()}>
              {t('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={convertOpen} onOpenChange={setConvertOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('convertTitle')}</DialogTitle>
            <DialogDescription>{t('convertHint')}</DialogDescription>
          </DialogHeader>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={convertDeal} onCheckedChange={(c) => setConvertDeal(c === true)} />
            {t('createDeal')}
          </label>
          <DialogFooter>
            <Button type="button" onClick={() => void submitConvert()}>
              {t('convert')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={duplicatesOpen} onOpenChange={setDuplicatesOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('duplicatesTitle')}</DialogTitle>
          </DialogHeader>
          <div className="max-h-80 space-y-2 overflow-y-auto">
            {duplicates.length === 0 ? (
              <p className="text-sm text-muted-foreground">{tc('noData')}</p>
            ) : (
              duplicates.map((d) => (
                <div key={d.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
                  <div>
                    <p className="font-medium">{d.topic ?? `#${d.id}`}</p>
                    <p className="text-muted-foreground">
                      {t('confidence')}: {Math.round(d.confidence * 100)}%
                    </p>
                  </div>
                  <Button type="button" size="sm" variant="outline" onClick={() => void mergeDuplicate(d)}>
                    {t('merge')}
                  </Button>
                </div>
              ))
            )}
          </div>
        </DialogContent>
      </Dialog>

      <Dialog open={bulkAssignOpen} onOpenChange={setBulkAssignOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('bulkAssign')}</DialogTitle>
          </DialogHeader>
          <Select value={bulkAssignUserId} onValueChange={setBulkAssignUserId}>
            <SelectTrigger>
              <SelectValue placeholder={t('user')} />
            </SelectTrigger>
            <SelectContent>
              {assignees.map((u) => (
                <SelectItem key={u.id} value={String(u.id)}>
                  {u.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <DialogFooter>
            <Button type="button" onClick={() => void submitBulkAssign()}>
              {t('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
