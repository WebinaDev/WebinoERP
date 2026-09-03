'use client';

import { useCallback, useEffect, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { sanitizeHtml } from '@/lib/sanitize-html';
import { CannedResponsePicker } from '@/features/modules/crm/components/CannedResponsePicker';

type Row = Record<string, unknown>;
type Meta = { current_page?: number; last_page?: number; total?: number };

export function TicketsListPage() {
  const t = useTranslations('crm.tickets');
  const tc = useTranslations('common');
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const ticketIdParam = searchParams.get('ticket_id');
  const actionParam = searchParams.get('action');
  const viewId = ticketIdParam ? parseInt(ticketIdParam, 10) : null;
  const wantNew = actionParam === 'new';

  const [rows, setRows] = useState<Row[]>([]);
  const [meta, setMeta] = useState<Meta>({});
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [filterStatus, setFilterStatus] = useState('');
  const [filterDept, setFilterDept] = useState('');
  const [search, setSearch] = useState('');

  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const [detail, setDetail] = useState<Row | null>(null);
  const [replies, setReplies] = useState<Row[]>([]);
  const [replyBody, setReplyBody] = useState('');
  const [convertConfirmOpen, setConvertConfirmOpen] = useState(false);

  const setQuery = useCallback(
    (next: { ticket_id?: string | null; action?: string | null }) => {
      const params = new URLSearchParams(searchParams.toString());
      if (next.ticket_id === null) params.delete('ticket_id');
      else if (next.ticket_id !== undefined) params.set('ticket_id', next.ticket_id);
      if (next.action === null) params.delete('action');
      else if (next.action !== undefined) params.set('action', next.action);
      const qs = params.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [pathname, router, searchParams],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/tickets', {
        params: {
          page,
          per_page: 15,
          status: filterStatus || undefined,
          department: filterDept || undefined,
          search: search || undefined,
        },
      });
      const payload = res.data as { data?: unknown; meta?: Meta };
      setRows(normalizeListPayload(payload));
      setMeta(payload.meta ?? {});
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [page, filterStatus, filterDept, search]);

  useEffect(() => {
    if (wantNew || (viewId && !Number.isNaN(viewId))) return;
    void load();
  }, [load, wantNew, viewId]);

  const openDetail = useCallback(
    async (id: number) => {
      setLoading(true);
      setError(null);
      setReplyBody('');
      try {
        const res = await apiClient.get(`/v1/projects/tickets/${id}`);
        const payload = res.data as { data?: Row };
        const d = payload.data ?? { id };
        setDetail(d);
        const raw = d.replies as Row[] | undefined;
        setReplies(Array.isArray(raw) ? raw : []);
      } catch (e) {
        setError(getAxiosMessage(e));
        setDetail(null);
      } finally {
        setLoading(false);
      }
    },
    [],
  );

  useEffect(() => {
    if (wantNew) {
      setDetail(null);
      setSubject('');
      setBody('');
      return;
    }
    if (viewId && !Number.isNaN(viewId)) {
      void openDetail(viewId);
      return;
    }
    setDetail(null);
  }, [wantNew, viewId, openDetail]);

  function openNew() {
    setQuery({ action: 'new', ticket_id: null });
  }

  function openTicket(r: Row) {
    setQuery({ ticket_id: String(r.id), action: null });
  }

  function backToList() {
    setQuery({ ticket_id: null, action: null });
    setDetail(null);
    void load();
  }

  async function submitTicket(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await apiClient.post('/v1/projects/tickets', { subject, body });
      setSubject('');
      setBody('');
      backToList();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSubmitting(false);
    }
  }

  async function submitReply() {
    if (!detail?.id || !replyBody.trim()) return;
    setError(null);
    setSubmitting(true);
    try {
      await apiClient.post(`/v1/projects/tickets/${detail.id}/replies`, { body: replyBody });
      setReplyBody('');
      void openDetail(Number(detail.id));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSubmitting(false);
    }
  }

  async function convertTask() {
    if (!detail?.id) return;
    setError(null);
    setSubmitting(true);
    try {
      await apiClient.post(`/v1/projects/tickets/${detail.id}/convert-task`);
      setConvertConfirmOpen(false);
      backToList();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSubmitting(false);
    }
  }

  async function updateTicket(patch: Record<string, unknown>) {
    if (!detail?.id) return;
    setError(null);
    try {
      await apiClient.patch(`/v1/projects/tickets/${detail.id}`, patch);
      void openDetail(Number(detail.id));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  const lastPage = meta.last_page ?? 1;

  if (wantNew) {
    return (
      <div className="space-y-4">
        <Card>
          <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
            <CardTitle className="text-base">{t('newTicket')}</CardTitle>
            <Button type="button" variant="outline" size="sm" onClick={backToList}>
              {t('backToList')}
            </Button>
          </CardHeader>
          <CardContent>
            {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}
            <form onSubmit={submitTicket} className="space-y-3">
              <div className="space-y-1">
                <Label>{t('subject')}</Label>
                <Input value={subject} onChange={(e) => setSubject(e.target.value)} required />
              </div>
              <div className="space-y-1">
                <Label>{t('message')}</Label>
                <Textarea value={body} onChange={(e) => setBody(e.target.value)} rows={6} />
                <p className="text-xs text-muted-foreground">{t('htmlNote')}</p>
              </div>
              <Button type="submit" disabled={submitting}>
                {t('submitTicket')}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (viewId && !Number.isNaN(viewId)) {
    return (
      <div className="space-y-4">
        <Button type="button" variant="ghost" size="sm" onClick={backToList}>
          {t('backToList')}
        </Button>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {loading && !detail ? (
          <p className="text-sm text-muted-foreground">{tc('loading')}</p>
        ) : !detail ? (
          <p className="text-sm text-muted-foreground">{t('notFound')}</p>
        ) : (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{String(detail.subject ?? '')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div
                className="prose prose-sm max-w-none rounded-md border p-3 dark:prose-invert"
                dangerouslySetInnerHTML={{ __html: sanitizeHtml(String(detail.body ?? '')) }}
              />
              <div className="flex flex-wrap gap-2">
                <Select
                  value={String(detail.status ?? 'open')}
                  onValueChange={(v) => void updateTicket({ status: v })}
                >
                  <SelectTrigger className="w-[140px]">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="open">open</SelectItem>
                    <SelectItem value="pending">pending</SelectItem>
                    <SelectItem value="closed">closed</SelectItem>
                  </SelectContent>
                </Select>
                <Select
                  value={String(detail.department ?? '') || '__none'}
                  onValueChange={(v) => void updateTicket({ department: v === '__none' ? '' : v })}
                >
                  <SelectTrigger className="w-[160px]">
                    <SelectValue placeholder={t('department')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none">—</SelectItem>
                    <SelectItem value="sales">sales</SelectItem>
                    <SelectItem value="support">support</SelectItem>
                    <SelectItem value="technical">technical</SelectItem>
                  </SelectContent>
                </Select>
                <Button type="button" variant="secondary" size="sm" onClick={() => setConvertConfirmOpen(true)}>
                  {t('convertToTask')}
                </Button>
              </div>
              <div className="space-y-2 border-t pt-3">
                <p className="font-medium">{t('replies')}</p>
                {replies.length === 0 ? (
                  <p className="text-muted-foreground">{t('noReplies')}</p>
                ) : (
                  replies.map((rep) => (
                    <div key={String(rep.id)} className="rounded-md bg-muted/50 p-2 text-xs">
                      <p className="text-muted-foreground">{String(rep.created_at ?? '')}</p>
                      <div
                        className="prose prose-sm max-w-none dark:prose-invert"
                        dangerouslySetInnerHTML={{ __html: sanitizeHtml(String(rep.body ?? '')) }}
                      />
                    </div>
                  ))
                )}
              </div>
              {String(detail.status) !== 'closed' ? (
                <div className="space-y-3 border-t pt-3">
                  <p className="font-medium">{t('newReply')}</p>
                  <CannedResponsePicker onSelect={(html) => setReplyBody((prev) => prev + html)} />
                  <div className="space-y-1">
                    <Label>{t('replyBody')}</Label>
                    <Textarea
                      placeholder={t('replyPlaceholder')}
                      value={replyBody}
                      onChange={(e) => setReplyBody(e.target.value)}
                      rows={5}
                    />
                    <p className="text-xs text-muted-foreground">{t('htmlNote')}</p>
                  </div>
                  <Button type="button" disabled={submitting || !replyBody.trim()} onClick={() => void submitReply()}>
                    {t('sendReply')}
                  </Button>
                </div>
              ) : null}
            </CardContent>
          </Card>
        )}

        <AlertDialog open={convertConfirmOpen} onOpenChange={setConvertConfirmOpen}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('convertToTask')}</AlertDialogTitle>
              <AlertDialogDescription>{t('convertConfirm')}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel disabled={submitting}>{tc('cancel')}</AlertDialogCancel>
              <AlertDialogAction disabled={submitting} onClick={() => void convertTask()}>
                {t('confirmYes')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
          <CardTitle className="text-base">{t('title')}</CardTitle>
          <Button type="button" size="sm" onClick={openNew}>
            {t('newTicket')}
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
          <div className="flex flex-wrap gap-2">
            <Select value={filterStatus || 'all'} onValueChange={(v) => setFilterStatus(v === 'all' ? '' : v)}>
              <SelectTrigger className="w-[140px]">
                <SelectValue placeholder={t('status')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('allStatuses')}</SelectItem>
                <SelectItem value="open">open</SelectItem>
                <SelectItem value="pending">pending</SelectItem>
                <SelectItem value="closed">closed</SelectItem>
              </SelectContent>
            </Select>
            <Select value={filterDept || 'all'} onValueChange={(v) => setFilterDept(v === 'all' ? '' : v)}>
              <SelectTrigger className="w-[160px]">
                <SelectValue placeholder={t('department')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('allDepartments')}</SelectItem>
                <SelectItem value="sales">sales</SelectItem>
                <SelectItem value="support">support</SelectItem>
                <SelectItem value="technical">technical</SelectItem>
              </SelectContent>
            </Select>
            <Input
              placeholder={t('searchPlaceholder')}
              className="max-w-xs"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && void load()}
            />
            <Button type="button" size="sm" variant="secondary" onClick={() => void load()}>
              {t('apply')}
            </Button>
          </div>
          <div className="overflow-x-auto rounded-md border">
            <table className="w-full min-w-[640px] text-sm">
              <thead>
                <tr className="border-b bg-muted/40 text-muted-foreground">
                  <th className="px-3 py-2 text-start">#</th>
                  <th className="px-3 py-2 text-start">{t('subject')}</th>
                  <th className="px-3 py-2 text-start">{t('status')}</th>
                  <th className="px-3 py-2 text-start">{t('priority')}</th>
                  <th className="px-3 py-2 text-start">{t('actions')}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                      {tc('loading')}
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                      {tc('noData')}
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr key={String(r.id)} className="border-b border-border/60">
                      <td className="px-3 py-2">{String(r.id)}</td>
                      <td className="px-3 py-2">
                        <button
                          type="button"
                          className="text-start font-medium text-primary hover:underline"
                          onClick={() => openTicket(r)}
                        >
                          {String(r.subject ?? '—')}
                        </button>
                      </td>
                      <td className="px-3 py-2">
                        <Badge variant="secondary">{String(r.status ?? '—')}</Badge>
                      </td>
                      <td className="px-3 py-2">{String(r.priority ?? '—')}</td>
                      <td className="px-3 py-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => openTicket(r)}>
                          {tc('view')}
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
          <Pagination page={meta.current_page ?? page} pageCount={lastPage} total={meta.total} onPageChange={setPage} />
        </CardContent>
      </Card>
    </div>
  );
}
