'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
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
  const [tab, setTab] = useState<string>('overview');
  const [from, setFrom] = useState<string | null>(monthStartIso());
  const [to, setTo] = useState<string | null>(todayIso());
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
    const d = new Date();
    setFrom(new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10));
    setTo(new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10));
  }

  function setPresetLastMonth() {
    const d = new Date();
    setFrom(new Date(d.getFullYear(), d.getMonth() - 1, 1).toISOString().slice(0, 10));
    setTo(new Date(d.getFullYear(), d.getMonth(), 0).toISOString().slice(0, 10));
  }

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
        <div>
          <CardTitle>{t('title')}</CardTitle>
          <CardDescription>
            {t('description', { from: range?.from ?? '—', to: range?.to ?? '—' })}
          </CardDescription>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => void exportServer('csv')}>
            {t('downloadCsv')}
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={() => void exportServer('json')}>
            {t('downloadJson')}
          </Button>
          <Button type="button" variant="secondary" size="sm" onClick={() => window.print()}>
            {t('printPdf')}
          </Button>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-end gap-3">
          <div className="w-48">
            <p className="mb-1 text-xs">{t('fromDate')}</p>
            <LocaleDatePicker value={from} onChange={setFrom} />
          </div>
          <div className="w-48">
            <p className="mb-1 text-xs">{t('toDate')}</p>
            <LocaleDatePicker value={to} onChange={setTo} />
          </div>
          <Button type="button" variant="outline" size="sm" onClick={setPresetThisMonth}>
            {t('presetThisMonth')}
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={setPresetLastMonth}>
            {t('presetLastMonth')}
          </Button>
          <Button type="button" className="self-end" onClick={() => void load()} disabled={loading}>
            {loading ? tCommon('loading') : t('load')}
          </Button>
        </div>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}

        <Tabs value={tab} onValueChange={setTab}>
          <TabsList className="flex h-auto flex-wrap gap-1">
            {REPORT_TAB_IDS.map((id) => (
              <TabsTrigger key={id} value={id}>
                {id === 'sales' || id === 'team' || id === 'customers'
                  ? tPages(`tab_${id}`)
                  : t(`tabs.${id}`)}
              </TabsTrigger>
            ))}
          </TabsList>
          {REPORT_TAB_IDS.map((id) => (
            <TabsContent key={id} value={id} className="pt-4">
              {loading && tab === id ? (
                <p className="text-sm text-muted-foreground">{tCommon('loading')}</p>
              ) : payload && tab === id ? (
                <ReportsTabPanel tab={id} payload={payload} />
              ) : tab === id && !error ? (
                <p className="text-sm text-muted-foreground">{t('emptyRange')}</p>
              ) : null}
            </TabsContent>
          ))}
        </Tabs>
      </CardContent>
    </Card>
  );
}
