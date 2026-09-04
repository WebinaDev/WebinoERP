'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboardHref } from '@/lib/route-resolver';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  fetchProvisionLogs,
  fetchProvisions,
  retryProvision,
  startProvision,
  stopProvision,
  type SiteProvision,
} from '@/lib/api/site-builder';

export function SiteProvisionsListPage() {
  const t = useTranslations('siteBuilder');
  const locale = useLocale();
  const [rows, setRows] = useState<SiteProvision[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [logsById, setLogsById] = useState<Record<number, string>>({});

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchProvisions());
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  async function runAction(id: number, action: 'retry' | 'start' | 'stop' | 'logs') {
    setError(null);
    setBusyId(id);
    try {
      if (action === 'retry') await retryProvision(id);
      else if (action === 'start') await startProvision(id);
      else if (action === 'stop') await stopProvision(id);
      else if (action === 'logs') {
        const raw = await fetchProvisionLogs(id);
        const text =
          typeof raw === 'string'
            ? raw
            : raw.logs ?? raw.error_log ?? JSON.stringify(raw);
        setLogsById((prev) => ({ ...prev, [id]: text }));
      }
      if (action !== 'logs') await load();
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="space-y-4 p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">{t('provisionsTitle')}</h1>
          <p className="text-muted-foreground text-sm">{t('provisionsSubtitle')}</p>
        </div>
        <Button asChild>
          <Link href={dashboardHref(locale, 'admin/platform/sites/new')}>{t('newProvision')}</Link>
        </Button>
      </div>
      {error ? <p className="text-destructive text-sm">{error}</p> : null}
      <div className="grid gap-3">
        {rows.map((row) => (
          <Card key={row.id}>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">{row.domain}</CardTitle>
            </CardHeader>
            <CardContent className="text-muted-foreground space-y-1 text-sm">
              <div>
                {t('status')}: {row.status}
              </div>
              <div className="font-mono">{row.slug}</div>
              {row.license?.license_key ? (
                <div className="font-mono text-xs">{row.license.license_key}</div>
              ) : null}
              {row.status === 'failed' && row.error_log ? (
                <pre className="bg-muted max-h-28 overflow-auto rounded p-2 text-xs whitespace-pre-wrap">
                  {row.error_log.slice(0, 500)}
                  {row.error_log.length > 500 ? '…' : ''}
                </pre>
              ) : null}
              {logsById[row.id] ? (
                <pre className="bg-muted max-h-40 overflow-auto rounded p-2 text-xs whitespace-pre-wrap">
                  {logsById[row.id].slice(0, 2000)}
                </pre>
              ) : null}
              <div className="flex flex-wrap gap-2 pt-2">
                {row.status === 'ready' && row.domain ? (
                  <Button asChild size="sm" variant="outline">
                    <a href={`https://${row.domain}`} target="_blank" rel="noopener noreferrer">
                      {t('openSite')}
                    </a>
                  </Button>
                ) : null}
                {row.status === 'failed' ? (
                  <Button
                    size="sm"
                    variant="secondary"
                    type="button"
                    disabled={busyId === row.id}
                    onClick={() => void runAction(row.id, 'retry')}
                  >
                    {t('retry')}
                  </Button>
                ) : null}
                <Button
                  size="sm"
                  variant="outline"
                  type="button"
                  disabled={busyId === row.id}
                  onClick={() => void runAction(row.id, 'start')}
                >
                  {t('start')}
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  type="button"
                  disabled={busyId === row.id}
                  onClick={() => void runAction(row.id, 'stop')}
                >
                  {t('stop')}
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  type="button"
                  disabled={busyId === row.id}
                  onClick={() => void runAction(row.id, 'logs')}
                >
                  {t('logs')}
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
