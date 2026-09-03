'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { Badge } from '@/components/ui/badge';
import { sanitizeHtml } from '@/lib/sanitize-html';

type Row = Record<string, unknown>;
type Meta = { current_page?: number; last_page?: number; total?: number };

export function TicketsListPage() {
  const [rows, setRows] = useState<Row[]>([]);
  const [meta, setMeta] = useState<Meta>({});
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [filterStatus, setFilterStatus] = useState('');
  const [filterDept, setFilterDept] = useState('');
  const [search, setSearch] = useState('');

  const [newOpen, setNewOpen] = useState(false);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');

  const [detail, setDetail] = useState<Row | null>(null);
  const [replies, setReplies] = useState<Row[]>([]);
  const [replyBody, setReplyBody] = useState('');

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
      const body = res.data as { data?: unknown; meta?: Meta };
      setRows(normalizeListPayload(body));
      setMeta(body.meta ?? {});
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [page, filterStatus, filterDept, search]);

  useEffect(() => {
    void load();
  }, [load]);

  async function openDetail(r: Row) {
    setDetail(r);
    setReplyBody('');
    try {
      const res = await apiClient.get(`/v1/projects/tickets/${String(r.id)}`);
      const body = res.data as { data?: Row };
      const d = body.data ?? r;
      setDetail(d);
      const raw = d.replies as Row[] | undefined;
      setReplies(Array.isArray(raw) ? raw : []);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function submitTicket(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await apiClient.post('/v1/projects/tickets', { subject, body });
      setNewOpen(false);
      setSubject('');
      setBody('');
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function submitReply() {
    if (!detail?.id) {
      return;
    }
    setError(null);
    try {
      await apiClient.post(`/v1/projects/tickets/${detail.id}/replies`, { body: replyBody });
      setReplyBody('');
      void openDetail(detail);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function convertTask() {
    if (!detail?.id) {
      return;
    }
    setError(null);
    try {
      await apiClient.post(`/v1/projects/tickets/${detail.id}/convert-task`);
      setDetail(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function updateTicket(patch: Record<string, unknown>) {
    if (!detail?.id) {
      return;
    }
    setError(null);
    try {
      await apiClient.patch(`/v1/projects/tickets/${detail.id}`, patch);
      void openDetail({ ...detail, ...patch });
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  const lastPage = meta.last_page ?? 1;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
          <CardTitle className="text-base">تیکت‌ها</CardTitle>
          <Button type="button" size="sm" onClick={() => setNewOpen(true)}>
            تیکت جدید
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
          <div className="flex flex-wrap gap-2">
            <Select value={filterStatus || 'all'} onValueChange={(v) => setFilterStatus(v === 'all' ? '' : v)}>
              <SelectTrigger className="w-[140px]">
                <SelectValue placeholder="وضعیت" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">همه</SelectItem>
                <SelectItem value="open">open</SelectItem>
                <SelectItem value="pending">pending</SelectItem>
                <SelectItem value="closed">closed</SelectItem>
              </SelectContent>
            </Select>
            <Select value={filterDept || 'all'} onValueChange={(v) => setFilterDept(v === 'all' ? '' : v)}>
              <SelectTrigger className="w-[160px]">
                <SelectValue placeholder="دپارتمان" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">همه دپارتمان‌ها</SelectItem>
                <SelectItem value="sales">sales</SelectItem>
                <SelectItem value="support">support</SelectItem>
                <SelectItem value="technical">technical</SelectItem>
              </SelectContent>
            </Select>
            <Input
              placeholder="جستجو موضوع/متن…"
              className="max-w-xs"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && void load()}
            />
            <Button type="button" size="sm" variant="secondary" onClick={() => void load()}>
              اعمال
            </Button>
          </div>
          <div className="overflow-x-auto rounded-md border">
            <table className="w-full min-w-[640px] text-sm">
              <thead>
                <tr className="border-b bg-muted/40 text-muted-foreground">
                  <th className="px-3 py-2 text-start">#</th>
                  <th className="px-3 py-2 text-start">موضوع</th>
                  <th className="px-3 py-2 text-start">وضعیت</th>
                  <th className="px-3 py-2 text-start">اولویت</th>
                  <th className="px-3 py-2 text-start">عملیات</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                      بارگذاری…
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr key={String(r.id)} className="border-b border-border/60">
                      <td className="px-3 py-2">{String(r.id)}</td>
                      <td className="px-3 py-2">{String(r.subject ?? '—')}</td>
                      <td className="px-3 py-2">
                        <Badge variant="secondary">{String(r.status ?? '—')}</Badge>
                      </td>
                      <td className="px-3 py-2">{String(r.priority ?? '—')}</td>
                      <td className="px-3 py-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => void openDetail(r)}>
                          مشاهده
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

      <Dialog open={newOpen} onOpenChange={setNewOpen}>
        <DialogContent>
          <form onSubmit={submitTicket}>
            <DialogHeader>
              <DialogTitle>تیکت جدید</DialogTitle>
            </DialogHeader>
            <div className="space-y-3 py-2">
              <div>
                <label className="text-sm">موضوع</label>
                <Input value={subject} onChange={(e) => setSubject(e.target.value)} required />
              </div>
              <div>
                <label className="text-sm">متن</label>
                <Textarea value={body} onChange={(e) => setBody(e.target.value)} rows={4} />
              </div>
            </div>
            <DialogFooter>
              <Button type="submit">ارسال</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={!!detail} onOpenChange={(o) => !o && setDetail(null)}>
        <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{String(detail?.subject ?? '')}</DialogTitle>
          </DialogHeader>
          {detail ? (
            <div className="space-y-3 text-sm">
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
                    <SelectValue placeholder="دپارتمان" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none">—</SelectItem>
                    <SelectItem value="sales">sales</SelectItem>
                    <SelectItem value="support">support</SelectItem>
                    <SelectItem value="technical">technical</SelectItem>
                  </SelectContent>
                </Select>
                <Button type="button" variant="secondary" size="sm" onClick={() => void convertTask()}>
                  تبدیل به تسک
                </Button>
              </div>
              <div className="space-y-2 border-t pt-3">
                <p className="font-medium">پاسخ‌ها</p>
                {replies.map((rep) => (
                  <div key={String(rep.id)} className="rounded-md bg-muted/50 p-2 text-xs">
                    <p className="text-muted-foreground">{String(rep.created_at ?? '')}</p>
                    <div
                      className="prose prose-sm max-w-none dark:prose-invert"
                      dangerouslySetInnerHTML={{ __html: sanitizeHtml(String(rep.body ?? '')) }}
                    />
                  </div>
                ))}
              </div>
              <Textarea placeholder="پاسخ شما…" value={replyBody} onChange={(e) => setReplyBody(e.target.value)} rows={3} />
              <Button type="button" onClick={() => void submitReply()}>
                ارسال پاسخ
              </Button>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  );
}
