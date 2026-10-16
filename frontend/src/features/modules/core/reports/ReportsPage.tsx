'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { BarChart3, CalendarDays, FileJson, FileSpreadsheet, Printer, RefreshCw } from 'lucide-react';
import { useLocale } from '@/hooks/use-locale';
import { jalaliMonthIsoRange } from '@/lib/locale/calendar-date';
import { ReportsTabPanel } from './ReportsTabPanel';
import {
  REPORT_TAB_IDS,
  clientCsvFromPayload,
  downloadBlob,
  type ReportsPayload,
} from './types';

function monthStartIso(): string {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
}

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

export function ReportsPage() {
  const t = useTranslations('reports');
  const tPages = useTranslations('pages.reports');
  const tCommon = useTranslations('common');
  const { formatDate, isRtl, locale } = useLocale();
  const initial = locale === 'fa' ? jalaliMonthIsoRange(0) : { from: monthStartIso(), to: todayIso() };
  const [tab, setTab] = useState<string>('overview');
  const [from, setFrom] = useState<string | null>(initial.from);
  const [to, setTo] = useState<string | null>(locale === 'fa' && initial.to > todayIso() ? todayIso() : initial.to);
  const [payload, setPayload] = useState<ReportsPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/core/reports', {
        params: {
          tab,
          from: from ?? undefined,
          to: to ?? undefined,
          date_from: from ?? undefined,
          date_to: to ?? undefined,
        },
      });
      setPayload(unwrapData<ReportsPayload>(res));
    } catch (e) {
      setError(getAxiosMessage(e));
      setPayload(null);
    } finally {
      setLoading(false);
    }
  }, [tab, from, to]);

  useEffect(() => {
    void load();
  }, [load]);

  const range = payload?.range ?? { from: from ?? undefined, to: to ?? undefined };

  async function exportServer(format: 'csv' | 'json') {
    try {
      const res = await apiClient.get('/v1/core/reports/export.csv', {
        params: {
          tab,
          format,
          from: from ?? undefined,
          to: to ?? undefined,
          date_from: from ?? undefined,
          date_to: to ?? undefined,
        },
        responseType: 'blob',
      });
      downloadBlob(res.data as Blob, `reports-${tab}.${format}`);
    } catch (e) {
      if (format === 'csv' && payload) {
        const csv = clientCsvFromPayload(tab, payload);
        downloadBlob(new Blob([csv], { type: 'text/csv;charset=utf-8' }), `reports-${tab}.csv`);
        return;
      }
      if (format === 'json' && payload) {
        downloadBlob(
          new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' }),
          `reports-${tab}.json`,
        );
        return;
      }
      setError(getAxiosMessage(e));
    }
  }

  function setPresetThisMonth() {
    if (locale === 'fa') {
      const range = jalaliMonthIsoRange(0);
      setFrom(range.from);
      setTo(range.to);
      return;
    }
    const d = new Date();
    setFrom(new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10));
    setTo(new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10));
  }

  function setPresetLastMonth() {
    if (locale === 'fa') {
      const range = jalaliMonthIsoRange(-1);
      setFrom(range.from);
      setTo(range.to);
      return;
    }
    const d = new Date();
    setFrom(new Date(d.getFullYear(), d.getMonth() - 1, 1).toISOString().slice(0, 10));
    setTo(new Date(d.getFullYear(), d.getMonth(), 0).toISOString().slice(0, 10));
  }

  return (
    <div className="wd-page-shell wd-reports min-w-0 max-w-full space-y-4 overflow-x-hidden text-start sm:space-y-6" dir={isRtl ? 'rtl' : 'ltr'}>
      <div className="flex flex-col gap-3 rounded-2xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div className="flex min-w-0 items-start gap-3">
          <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-[#0054ff]/10 text-[#0054ff]">
            <BarChart3 className="size-5" />
          </span>
          <div className="min-w-0">
            <p className="text-xs leading-6 text-muted-foreground sm:text-sm">
              {t('description', {
                from: range?.from ? formatDate(range.from) : tCommon('none'),
                to: range?.to ? formatDate(range.to) : tCommon('none'),
              })}
            </p>
          </div>
        </div>
        <div className="grid grid-cols-3 gap-2 sm:flex sm:shrink-0">
          <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={() => void exportServer('csv')}>
            <FileSpreadsheet className="size-4" />
            <span className="truncate">{t('downloadCsv')}</span>
          </Button>
          <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={() => void exportServer('json')}>
            <FileJson className="size-4" />
            <span className="truncate">{t('downloadJson')}</span>
          </Button>
          <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={() => window.print()}>
            <Printer className="size-4" />
            <span className="truncate">{t('printPdf')}</span>
          </Button>
        </div>
      </div>

      <Card className="min-w-0 overflow-hidden rounded-2xl">
        <CardContent className="space-y-4 p-3 sm:p-5">
          <div className="grid grid-cols-2 gap-3 rounded-xl bg-muted/40 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end lg:grid-cols-[minmax(0,14rem)_minmax(0,14rem)_1fr_auto]">
            <label className="min-w-0 space-y-1">
              <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                <CalendarDays className="size-3.5" />
                {t('fromDate')}
              </span>
              <LocaleDatePicker value={from} onChange={setFrom} className="w-full" />
            </label>
            <label className="min-w-0 space-y-1">
              <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                <CalendarDays className="size-3.5" />
                {t('toDate')}
              </span>
              <LocaleDatePicker value={to} onChange={setTo} className="w-full" />
            </label>
            <div className="col-span-2 grid grid-cols-2 gap-2 md:col-span-1 md:flex lg:justify-start">
              <Button type="button" variant="outline" size="sm" className="h-9" onClick={setPresetThisMonth}>
                {t('presetThisMonth')}
              </Button>
              <Button type="button" variant="outline" size="sm" className="h-9" onClick={setPresetLastMonth}>
                {t('presetLastMonth')}
              </Button>
            </div>
            <Button
              type="button"
              className="col-span-2 h-9 gap-1.5 bg-[#0054ff] text-white hover:bg-[#0054ff]/90 md:col-span-3 lg:col-span-1"
              onClick={() => void load()}
              disabled={loading}
            >
              <RefreshCw className={`size-4 ${loading ? 'animate-spin' : ''}`} />
              {loading ? tCommon('loading') : t('load')}
            </Button>
          </div>

          {error ? <p className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">{error}</p> : null}

          <Tabs value={tab} onValueChange={setTab} dir={isRtl ? 'rtl' : 'ltr'} className="min-w-0">
            <div className="-mx-3 overflow-x-auto px-3 pb-1 [scrollbar-width:none] sm:mx-0 sm:px-0 [&::-webkit-scrollbar]:hidden">
              <TabsList className="inline-flex h-auto w-max min-w-full flex-nowrap justify-start gap-1 rounded-xl p-1 md:w-full md:flex-wrap">
                {REPORT_TAB_IDS.map((id) => (
                  <TabsTrigger
                    key={id}
                    value={id}
                    className="shrink-0 rounded-lg px-3 py-1.5 text-xs data-[state=active]:bg-[#0054ff] data-[state=active]:text-white sm:text-sm md:flex-none"
                  >
                    {id === 'sales' || id === 'team' || id === 'customers'
                      ? tPages(`tab_${id}`)
                      : t(`tabs.${id}`)}
                  </TabsTrigger>
                ))}
              </TabsList>
            </div>
            {REPORT_TAB_IDS.map((id) => (
              <TabsContent key={id} value={id} className="min-w-0 pt-4">
                {loading && tab === id ? (
                  <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, i) => (
                      <div key={i} className="h-20 animate-pulse rounded-xl bg-muted" />
                    ))}
                  </div>
                ) : payload && tab === id ? (
                  <ReportsTabPanel tab={id} payload={payload} />
                ) : tab === id && !error ? (
                  <p className="py-8 text-center text-sm text-muted-foreground">{t('emptyRange')}</p>
                ) : null}
              </TabsContent>
            ))}
          </Tabs>
        </CardContent>
      </Card>
    </div>
  );
}
