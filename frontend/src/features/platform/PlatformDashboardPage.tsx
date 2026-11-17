'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useLocale as useNextIntlLocale, useTranslations } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale-next';
import { fetchPlatformDashboard, type PlatformDashboard } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';
import { PlatformDomainControlHub } from '@/features/platform/PlatformDomainControlHub';
import { Globe, Server, Layers, Cpu, Mail } from 'lucide-react';

export function PlatformDashboardPage() {
  const t = useTranslations('platform');
  const locale = useNextIntlLocale();
  const { formatDate, formatNumber } = useLocale();
  const [data, setData] = useState<PlatformDashboard | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [mainTab, setMainTab] = useState<'control_plane' | 'domain_email'>('domain_email');

  const load = useCallback(async () => {
    setError(null);
    try {
      setData(await fetchPlatformDashboard());
    } catch (e) {
      setError(e instanceof Error ? e.message : t('loadError'));
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <PlatformPageLayout
      title={t('dashboardTitle')}
      subtitle={t('dashboardSubtitle')}
      error={error}
      actions={
        <div className="flex items-center gap-2">
          <Tabs value={mainTab} onValueChange={(v) => setMainTab(v as any)} className="h-9">
            <TabsList className="h-9">
              <TabsTrigger value="domain_email" className="text-xs flex items-center gap-1.5">
                <Globe className="h-3.5 w-3.5 text-primary" />
                دامنه‌ها، ایمیل و DNS
              </TabsTrigger>
              <TabsTrigger value="control_plane" className="text-xs flex items-center gap-1.5">
                <Server className="h-3.5 w-3.5" />
                سرورها و استقرارها
              </TabsTrigger>
            </TabsList>
          </Tabs>
          <RefreshButton onClick={() => void load()} label={t('refresh')} />
        </div>
      }
    >
      {mainTab === 'domain_email' ? (
        <PlatformDomainControlHub />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-4 md:grid-cols-4">
            {[
              { label: t('stats.servers'), value: data?.servers ?? 0, icon: Server },
              { label: t('stats.projects'), value: data?.projects ?? 0, icon: Layers },
              { label: t('stats.resources'), value: data?.resources ?? 0, icon: Cpu },
              { label: t('stats.running'), value: data?.running ?? 0, icon: Globe },
            ].map((s) => (
              <Card key={s.label}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-muted-foreground flex items-center justify-between">
                    {s.label}
                    <s.icon className="h-4 w-4 text-muted-foreground" />
                  </CardTitle>
                </CardHeader>
                <CardContent><p className="text-3xl font-semibold">{formatNumber(s.value)}</p></CardContent>
              </Card>
            ))}
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">{t('recentServers')}</CardTitle>
                <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, 'admin/platform/servers')}>{t('viewAll')}</Link></Button>
              </CardHeader>
              <CardContent className="space-y-2">
                {(data?.servers_list ?? []).map((s) => (
                  <Link key={s.id} href={dashboardHref(locale, `admin/platform/servers/${s.id}`)} className="flex items-center justify-between rounded-md border p-3 text-sm hover:bg-muted/50">
                    <span className="font-medium">{s.name}</span>
                    <Badge variant="secondary">{s.status ?? '—'}</Badge>
                  </Link>
                ))}
                {!data?.servers_list?.length ? <p className="text-sm text-muted-foreground">{t('noData')}</p> : null}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">{t('recentProjects')}</CardTitle>
                <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, 'admin/platform/projects')}>{t('viewAll')}</Link></Button>
              </CardHeader>
              <CardContent className="space-y-2">
                {(data?.projects_list ?? []).map((p) => (
                  <Link key={p.id} href={dashboardHref(locale, `admin/platform/projects/${p.id}`)} className="block rounded-md border p-3 text-sm hover:bg-muted/50">
                    <span className="font-medium">{p.name}</span>
                  </Link>
                ))}
                {!data?.projects_list?.length ? <p className="text-sm text-muted-foreground">{t('noData')}</p> : null}
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader><CardTitle className="text-base">{t('recentDeployments')}</CardTitle></CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-muted-foreground">
                    <th className="py-2 text-start">ID</th>
                    <th className="py-2 text-start">{t('resource')}</th>
                    <th className="py-2 text-start">{t('status')}</th>
                    <th className="py-2 text-start">{t('startedAt')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.recent_deployments ?? []).map((d) => (
                    <tr key={d.id} className="border-b border-border/60">
                      <td className="py-2">{d.id}</td>
                      <td className="py-2">{d.resource_id}</td>
                      <td className="py-2"><Badge variant="secondary">{d.status}</Badge></td>
                      <td className="py-2 font-mono text-xs">
                        {d.started_at ? formatDate(d.started_at, { includeTime: true }) || d.started_at : '—'}
                      </td>
                    </tr>
                  ))}
                  {!data?.recent_deployments?.length ? (
                    <tr><td colSpan={4} className="py-4 text-center text-muted-foreground">{t('noData')}</td></tr>
                  ) : null}
                </tbody>
              </table>
            </CardContent>
          </Card>
        </div>
      )}
    </PlatformPageLayout>
  );
}
