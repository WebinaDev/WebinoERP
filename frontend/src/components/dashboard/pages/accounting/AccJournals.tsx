'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { accountingWpAction } from '@/lib/accounting-wp';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';

type Journal = {
  id: number;
  document_no: string;
  document_date: string;
  description: string;
  status: string;
};

type FiscalYear = { id: number; title: string };

export default function AccJournals() {
  const [journals, setJournals] = useState<Journal[]>([]);
  const [fys, setFys] = useState<FiscalYear[]>([]);
  const [fyId, setFyId] = useState('all');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [postingId, setPostingId] = useState<number | null>(null);

  useEffect(() => {
    apiClient
      .get('/v1/accounting/fiscal-years', { params: { per_page: 100 } })
      .then((r) => setFys(normalizeListPayload(r.data) as unknown as FiscalYear[]))
      .catch(() => {});
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, unknown> = { page, per_page: 20 };
      if (fyId !== 'all') params.fiscal_year_id = fyId;
      const res = await apiClient.get('/v1/accounting/journals', { params });
      const body = res.data as { data?: Journal[]; last_page?: number };
      setJournals(body.data ?? []);
      setLastPage(body.last_page ?? 1);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [fyId, page]);

  useEffect(() => {
    void load();
  }, [load]);

  const handlePost = async (id: number) => {
    setPostingId(id);
    try {
      await accountingWpAction('journal_post', { id });
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setPostingId(null);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold">اسناد حسابداری</h2>
        <div className="flex items-center gap-2">
          <Select
            value={fyId}
            onValueChange={(v) => { setFyId(v); setPage(1); }}
          >
            <SelectTrigger className="w-48">
              <SelectValue placeholder="سال مالی" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">همه سال‌ها</SelectItem>
              {fys.map((f) => (
                <SelectItem key={f.id} value={String(f.id)}>{f.title}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading}>
            بروزرسانی
          </Button>
        </div>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}
      {loading && <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>}

      {!loading && journals.length > 0 && (
        <>
          <div className="overflow-x-auto rounded-md border">
            <table className="w-full min-w-[700px] text-sm">
              <thead>
                <tr className="border-b bg-muted/40">
                  <th className="px-2 py-2 text-start font-medium">شناسه</th>
                  <th className="px-2 py-2 text-start font-medium">شماره سند</th>
                  <th className="px-2 py-2 text-start font-medium">تاریخ</th>
                  <th className="px-2 py-2 text-start font-medium">شرح</th>
                  <th className="px-2 py-2 text-start font-medium">وضعیت</th>
                  <th className="px-2 py-2 text-start font-medium">عملیات</th>
                </tr>
              </thead>
              <tbody>
                {journals.map((j) => (
                  <tr key={j.id} className="border-b border-border/60">
                    <td className="px-2 py-1.5 tabular-nums">{j.id}</td>
                    <td className="px-2 py-1.5 tabular-nums">{j.document_no}</td>
                    <td className="px-2 py-1.5">{j.document_date}</td>
                    <td className="max-w-[260px] truncate px-2 py-1.5">{j.description}</td>
                    <td className="px-2 py-1.5">
                      <Badge variant={j.status === 'posted' ? 'default' : 'outline'}>
                        {j.status === 'posted' ? 'ثبت شده' : 'پیش‌نویس'}
                      </Badge>
                    </td>
                    <td className="px-2 py-1.5">
                      {j.status === 'draft' && (
                        <Button
                          size="sm"
                          variant="outline"
                          disabled={postingId === j.id}
                          onClick={() => void handlePost(j.id)}
                        >
                          {postingId === j.id ? 'در حال ثبت…' : 'ثبت سند'}
                        </Button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex items-center justify-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              onClick={() => setPage((p) => p - 1)}
            >
              قبلی
            </Button>
            <span className="text-sm tabular-nums text-muted-foreground">
              صفحه {page} از {lastPage}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= lastPage}
              onClick={() => setPage((p) => p + 1)}
            >
              بعدی
            </Button>
          </div>
        </>
      )}

      {!loading && !error && journals.length === 0 && (
        <p className="text-sm text-muted-foreground">سندی یافت نشد</p>
      )}
    </div>
  );
}
