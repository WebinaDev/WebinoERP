'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboardHref } from '@/lib/route-resolver';
import { fetchProvisions, type SiteProvision } from '@/lib/api/site-builder';
import apiClient from '@/lib/api-client';

export function SiteProvisionsListPage() {
  const t = useTranslations('siteBuilder');
  const locale = useLocale();
  const [rows, setRows] = useState<SiteProvision[]>([]);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchProvisions());
    } catch (e) {
      setError(e instanceof Error ? e.message : t('loadError'));
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  async function retryProvision(id: number) {
    setError(null);
    try {
      await apiClient.post(`/site-builder/provisions/${id}/retry`);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : t('loadError'));
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
          <Link href={dashboardHref(locale, 'admin/site-builder/provisions/new')}>{t('newProvision')}</Link>
        </Button>
      </div>
      {error ? <p className="text-destructive text-sm">{error}</p> : null}
      <div className="grid gap-3">
        {rows.map((row) => (
          <Card key={row.id}>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">{row.domain}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground space-y-1">
              <div>{t('status')}: {row.status}</div>
              <div className="font-mono">{row.slug}</div>
              {row.license?.license_key ? (
                <div className="font-mono text-xs">{row.license.license_key}</div>
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
                  <Button size="sm" variant="secondary" type="button" onClick={() => void retryProvision(row.id)}>
                    {t('retry')}
                  </Button>
                ) : null}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
