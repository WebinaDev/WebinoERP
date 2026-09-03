'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { dashboardHref } from '@/lib/route-resolver';
import { deleteResource, deployResource, fetchResources, type PlatformResource } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function ResourcesListPage() {
  const t = useTranslations('platform.resources');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const [rows, setRows] = useState<PlatformResource[]>([]);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchResources());
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleDeploy(id: number) {
    setPending(true);
    try {
      await deployResource(id);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleDelete(id: number) {
    if (!window.confirm(t('confirmDelete'))) return;
    setPending(true);
    try {
      await deleteResource(id);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      error={error}
      actions={
        <>
          <RefreshButton onClick={() => void load()} label={tP('refresh')} />
          <Button asChild size="sm"><Link href={dashboardHref(locale, 'admin/platform/resources/new')}>{t('addResource')}</Link></Button>
        </>
      }
    >
      <div className="grid gap-3">
        {rows.map((row) => (
          <Card key={row.id}>
            <CardHeader className="flex flex-row items-start justify-between pb-2">
              <div>
                <CardTitle className="text-base">
                  <Link href={dashboardHref(locale, `admin/platform/resources/${row.id}`)} className="hover:underline">{row.name}</Link>
                </CardTitle>
                <p className="font-mono text-xs text-muted-foreground">{row.fqdn ?? row.domains?.[0]?.domain ?? '—'}</p>
              </div>
              <div className="flex gap-1">
                <Badge variant="secondary">{row.type}</Badge>
                <Badge variant="outline">{row.status ?? '—'}</Badge>
              </div>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
              <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, `admin/platform/resources/${row.id}`)}>{t('manage')}</Link></Button>
              <Button size="sm" variant="secondary" disabled={pending} onClick={() => void handleDeploy(row.id)}>{t('deploy')}</Button>
              <Button size="sm" variant="destructive" disabled={pending} onClick={() => void handleDelete(row.id)}>{tP('delete')}</Button>
            </CardContent>
          </Card>
        ))}
        {!rows.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
      </div>
    </PlatformPageLayout>
  );
}
