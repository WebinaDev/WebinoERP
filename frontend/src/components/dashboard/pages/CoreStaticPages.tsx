'use client';

import { useTranslations } from 'next-intl';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { normalizeListPayload } from '@/lib/list-utils';
import { SettingsPageView } from '@/components/dashboard/pages/settings-view';

export { SettingsPageView };

export { ProfilePage as ProfilePageView, ReportsPage as ReportsPageView } from '@/features/modules/core/core_pages';
export { LicensesPageView } from '@/features/modules/core/licenses/LicensesPage';

export function LogsPageView() {
  const t = useTranslations();

  const [tab, setTab] = useState<'events' | 'system' | 'user' | 'bale'>('events');
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const endpoint =
    tab === 'system'
      ? '/v1/core/logs/system'
      : tab === 'user'
        ? '/v1/core/logs/user'
        : tab === 'bale'
          ? '/v1/core/logs'
          : '/v1/core/logs';

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { limit: 80 };
      if (tab === 'bale') {
        params.type = 'bale';
      }
      const res = await apiClient.get(endpoint, { params });
      const data = unwrapData<unknown>(res);
      const list = Array.isArray(data) ? data : normalizeListPayload(data);
      setRows(list);
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [endpoint, tab]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setPage(1);
  }, [tab]);

  const pageSize = 15;
  const slice = rows.slice((page - 1) * pageSize, page * pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));

  const cols =
    slice[0] && typeof slice[0] === 'object'
      ? Object.keys(slice[0] as object).filter((k) => k !== 'context')
      : ['id', 'level', 'message', 'created_at'];

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('auto.CoreStaticPages.s_11a3752a')}</CardTitle>
        <CardDescription>{t('auto.CoreStaticPages.s_ec15de8f')}</CardDescription>
      </CardHeader>
      <CardContent>
        <Tabs value={tab} onValueChange={(v) => setTab(v as typeof tab)}>
          <TabsList>
            <TabsTrigger value="events">{t('auto.CoreStaticPages.s_8d1d1dfc')}</TabsTrigger>
            <TabsTrigger value="system">{t('auto.CoreStaticPages.s_483ec0ff')}</TabsTrigger>
            <TabsTrigger value="user">{t('auto.CoreStaticPages.s_32398f04')}</TabsTrigger>
            <TabsTrigger value="bale">{t('auto.CoreStaticPages.s_afcb410b')}</TabsTrigger>
          </TabsList>
        </Tabs>
        <p className="mt-2 text-xs text-muted-foreground" dir="ltr">
          GET {endpoint}
          {tab === 'bale' ? '?type=bale' : ''}
        </p>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <div className="mt-3 overflow-x-auto rounded-md border">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b bg-muted/40">
                {cols.map((c) => (
                  <th key={c} className="px-2 py-2 text-start font-medium">
                    {c}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={cols.length} className="py-6 text-center text-muted-foreground">
                    …
                  </td>
                </tr>
              ) : (
                slice.map((r, i) => (
                  <tr key={i} className="border-b border-border/60">
                    {cols.map((c) => (
                      <td key={c} className="max-w-[200px] truncate px-2 py-1" dir="ltr">
                        {formatCell((r as Record<string, unknown>)[c])}
                      </td>
                    ))}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <div className="mt-3 flex items-center justify-between gap-2 text-sm">
          <span className="text-muted-foreground">
            {t('common.rowsPage', { rows: rows.length, page, pageCount })}
          </span>
          <div className="flex gap-1">
            <Button type="button" size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              {t('auto.CoreStaticPages.s_1a592f6b')}
            </Button>
            <Button type="button" size="sm" variant="outline" disabled={page >= pageCount} onClick={() => setPage((p) => p + 1)}>
              {t('auto.CoreStaticPages.s_54ee927e')}
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function formatCell(v: unknown): string {
  if (v === null || v === undefined) {
    return '';
  }
  if (typeof v === 'object') {
    return JSON.stringify(v).slice(0, 120);
  }
  return String(v);
}

type VisitorPayload = {
  total_visits?: number;
  unique_visitors?: number;
  period_days?: number;
  bounce_rate?: number;
  visits_by_day?: { day?: string; visits?: number }[];
  top_pages?: { path?: string; visits?: number }[];
  recent_visits?: Record<string, unknown>[];
  browsers?: { name: string; count: number }[];
  os?: { name: string; count: number }[];
  devices?: { name: string; count: number }[];
};

export function VisitorStatsPageView() {
  const t = useTranslations();

  const [days, setDays] = useState(14);
  const [data, setData] = useState<VisitorPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/core/visitor-stats', { params: { days } });
      setData(unwrapData<VisitorPayload>(res));
      setError(null);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [days]);

  useEffect(() => {
    void load();
  }, [load]);

  const byDay = data?.visits_by_day ?? [];
  const maxV = Math.max(1, ...byDay.map((d) => Number(d.visits ?? 0)));

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('auto.CoreStaticPages.s_af3ad14d')}</CardTitle>
        <CardDescription>{t('auto.CoreStaticPages.s_dfc493da')}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-end gap-3">
          <div>
            <p className="text-muted-foreground mb-1 text-xs">{t('auto.CoreStaticPages.s_f3514c21')}</p>
            <Input
              type="number"
              min={1}
              max={90}
              className="w-24"
              value={days}
              onChange={(e) => setDays(Number(e.target.value) || 7)}
              dir="ltr"
            />
          </div>
          <Button type="button" onClick={() => void load()} disabled={loading}>
            {t('auto.CoreStaticPages.s_72513b9f')}
          </Button>
        </div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-muted-foreground text-sm">{t('auto.CoreStaticPages.s_c4a2d62a')}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {loading ? '…' : data?.total_visits ?? '—'}
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-muted-foreground text-sm">{t('auto.CoreStaticPages.s_13097bf2')}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {loading ? '…' : data?.unique_visitors ?? '—'}
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-muted-foreground text-sm">{t('auto.CoreStaticPages.s_68b6c85d')}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {loading ? '…' : data?.bounce_rate != null ? `${data.bounce_rate}%` : '—'}
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-muted-foreground text-sm">{t('auto.CoreStaticPages.s_053abf8d')}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {t('common.periodDays', { days: loading ? '…' : data?.period_days ?? '—' })}
            </CardContent>
          </Card>
        </div>

        <div>
          <p className="mb-2 text-sm font-medium">{t('auto.CoreStaticPages.s_e3e433b8')}</p>
          <div className="flex h-36 items-end gap-1 overflow-x-auto">
            {byDay.map((d, i) => (
              <div key={String(d.day ?? i)} className="flex min-w-[20px] flex-1 flex-col items-center gap-1">
                <div
                  className="bg-primary/80 w-full max-w-[24px] rounded-t"
                  style={{ height: `${Math.max(4, (Number(d.visits ?? 0) / maxV) * 120)}px` }}
                />
                <span className="text-muted-foreground max-w-[48px] truncate text-[10px]" dir="ltr">
                  {String(d.day ?? '').slice(5)}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <div>
            <p className="mb-2 text-sm font-medium">{t('auto.CoreStaticPages.s_3759241c')}</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-muted/40 border-b">
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_a2a1682c')}</th>
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_61746c26')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.top_pages ?? []).map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="max-w-xs truncate px-2 py-1" dir="ltr">
                        {String(r.path ?? '—')}
                      </td>
                      <td className="px-2 py-1">{String(r.visits ?? '')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div>
            <p className="mb-2 text-sm font-medium">{t('auto.CoreStaticPages.s_c63f6f4e')}</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-muted/40 border-b">
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_0890e940')}</th>
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_a2a1682c')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.recent_visits ?? []).map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="px-2 py-1" dir="ltr">
                        {String(r.visited_at ?? r.created_at ?? '—')}
                      </td>
                      <td className="max-w-[200px] truncate px-2 py-1" dir="ltr">
                        {String(r.path ?? '—')}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="grid gap-4 lg:grid-cols-3">
          <div>
            <p className="mb-2 text-sm font-medium">{t('auto.CoreStaticPages.s_c89f27ef')}</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-muted/40 border-b">
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_16258912')}</th>
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_687f8df3')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.browsers ?? []).map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="px-2 py-1">{String(r.name)}</td>
                      <td className="px-2 py-1">{String(r.count)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div>
            <p className="mb-2 text-sm font-medium">{t('auto.CoreStaticPages.s_248ebe85')}</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-muted/40 border-b">
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_248ebe85')}</th>
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_687f8df3')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.os ?? []).map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="px-2 py-1">{String(r.name)}</td>
                      <td className="px-2 py-1">{String(r.count)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div>
            <p className="mb-2 text-sm font-medium">{t('auto.CoreStaticPages.s_4a31c9d7')}</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead>
                  <tr className="bg-muted/40 border-b">
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_7df5687d')}</th>
                    <th className="px-2 py-2 text-start">{t('auto.CoreStaticPages.s_687f8df3')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.devices ?? []).map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="px-2 py-1">{String(r.name)}</td>
                      <td className="px-2 py-1">{String(r.count)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {error ? <p className="text-destructive text-sm">{error}</p> : null}
      </CardContent>
    </Card>
  );
}
