'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboardHref } from '@/lib/route-resolver';
import {
  createProjectEnvironment,
  fetchProject,
  updateProject,
  fetchProjectUsage,
  fetchProjectQuota,
  updateProjectQuota,
  type PlatformProject,
  type ProjectUsageResponse,
  type ProjectQuota,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

type Props = { id: string };

export function ProjectDetailPage({ id }: Props) {
  const t = useTranslations('platform.projects');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const isFa = locale === 'fa';
  const [project, setProject] = useState<PlatformProject | null>(null);
  const [envName, setEnvName] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Usage & Quota State
  const [usage, setUsage] = useState<ProjectUsageResponse | null>(null);
  const [quota, setQuota] = useState<ProjectQuota | null>(null);
  const [savingQuota, setSavingQuota] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      const [p, u, q] = await Promise.all([
        fetchProject(id),
        fetchProjectUsage(id).catch(() => null),
        fetchProjectQuota(id).catch(() => null),
      ]);
      setProject(p);
      if (u) setUsage(u);
      if (q) setQuota(q);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [id, tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function saveProject() {
    if (!project) return;
    setPending(true);
    try {
      setProject(await updateProject(id, { name: project.name, description: project.description }));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function addEnvironment() {
    if (!envName.trim()) return;
    setPending(true);
    try {
      await createProjectEnvironment(id, envName.trim());
      setEnvName('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function saveQuota() {
    if (!quota) return;
    setSavingQuota(true);
    try {
      const res = await updateProjectQuota(id, quota);
      setQuota(res.data);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setSavingQuota(false);
    }
  }

  const diskUsedMb = (usage?.total_usage?.disk_bytes ?? 0) / (1024 * 1024);
  const diskSoftMb = quota?.disk_mb_soft ?? 0;
  const diskPct = diskSoftMb > 0 ? Math.min(100, Math.round((diskUsedMb / diskSoftMb) * 100)) : 0;

  const ramUsedMb = (usage?.total_usage?.ram_bytes ?? 0) / (1024 * 1024);
  const ramSoftMb = quota?.ram_mb_soft ?? 0;
  const ramPct = ramSoftMb > 0 ? Math.min(100, Math.round((ramUsedMb / ramSoftMb) * 100)) : 0;

  return (
    <PlatformPageLayout
      title={project?.name ?? t('title')}
      subtitle={t('detailSubtitle')}
      error={error}
      actions={
        <>
          <Button asChild size="sm" variant="outline">
            <Link href={dashboardHref(locale, 'admin/platform/projects')}>{tP('back')}</Link>
          </Button>
          <RefreshButton onClick={() => void load()} label={tP('refresh')} />
        </>
      }
    >
      {project ? (
        <Tabs defaultValue="general">
          <TabsList className="mb-4">
            <TabsTrigger value="general">{t('tabGeneral') || 'General'}</TabsTrigger>
            <TabsTrigger value="usage">{isFa ? 'مصرف و سقف منابع (Usage & Quota)' : 'Usage & Quotas'}</TabsTrigger>
          </TabsList>

          <TabsContent value="general" className="space-y-4">
            <Card>
              <CardContent className="grid gap-3 pt-6">
                <div className="grid gap-2">
                  <Label>{t('name')}</Label>
                  <Input value={project.name} onChange={(e) => setProject({ ...project, name: e.target.value })} />
                </div>
                <div className="grid gap-2">
                  <Label>{t('description')}</Label>
                  <Input
                    value={project.description ?? ''}
                    onChange={(e) => setProject({ ...project, description: e.target.value })}
                  />
                </div>
                <Button disabled={pending} onClick={() => void saveProject()}>{tP('save')}</Button>
              </CardContent>
            </Card>

            <Card>
              <CardHeader><CardTitle className="text-base">{t('environments')}</CardTitle></CardHeader>
              <CardContent className="space-y-3">
                <div className="flex flex-wrap gap-2">
                  {(project.environments ?? []).map((env) => (
                    <Badge key={env.id} variant="secondary">{env.name}</Badge>
                  ))}
                </div>
                <div className="flex flex-wrap gap-2">
                  <Input
                    value={envName}
                    onChange={(e) => setEnvName(e.target.value)}
                    placeholder={t('newEnvironment')}
                    className="max-w-xs"
                  />
                  <Button disabled={pending || !envName.trim()} onClick={() => void addEnvironment()}>
                    {t('addEnvironment')}
                  </Button>
                </div>
                <Button asChild variant="outline" size="sm">
                  <Link href={dashboardHref(locale, `admin/platform/resources?project=${project.id}`)}>
                    {t('viewResources')}
                  </Link>
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Tab 2: Usage & Quota */}
          <TabsContent value="usage" className="space-y-4">
            {/* Aggregate Usage Stats */}
            <div className="grid gap-4 md:grid-cols-4">
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-muted-foreground">
                    {isFa ? 'دیسک تجمیعی پروژه' : 'Aggregated Disk'}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold font-mono">
                    {diskUsedMb > 1024 ? `${(diskUsedMb / 1024).toFixed(2)} GB` : `${Math.round(diskUsedMb)} MB`}
                  </div>
                  <p className="text-[11px] text-muted-foreground mt-1">
                    {quota?.disk_mb_soft ? `${diskPct}% of Soft Quota (${Math.round(diskSoftMb)} MB)` : 'No limit set'}
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-muted-foreground">
                    {isFa ? 'حافظه تجمیعی پروژه' : 'Aggregated RAM'}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold font-mono">
                    {ramUsedMb > 1024 ? `${(ramUsedMb / 1024).toFixed(2)} GB` : `${Math.round(ramUsedMb)} MB`}
                  </div>
                  <p className="text-[11px] text-muted-foreground mt-1">
                    {quota?.ram_mb_soft ? `${ramPct}% of Soft Quota (${Math.round(ramSoftMb)} MB)` : 'No limit set'}
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-muted-foreground">
                    {isFa ? 'پردازنده تجمیعی' : 'Aggregated CPU'}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold font-mono">
                    {Math.round(usage?.total_usage?.cpu_usage ?? 0)}%
                  </div>
                  <p className="text-[11px] text-muted-foreground mt-1">
                    Soft: {quota?.cpu_percent_soft ?? '—'}% · Hard: {quota?.cpu_percent_hard ?? '—'}%
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-xs font-medium text-muted-foreground">
                    {isFa ? 'کل Inodeها' : 'Total Inodes'}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold font-mono">
                    {Number(usage?.total_usage?.inode_count ?? 0).toLocaleString()}
                  </div>
                  <p className="text-[11px] text-muted-foreground mt-1">
                    Soft: {quota?.inode_soft?.toLocaleString() ?? '—'}
                  </p>
                </CardContent>
              </Card>
            </div>

            {/* Resources Usage Breakdown Table */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base font-semibold">
                  {isFa ? 'تفکیک مصرف منابع (Resources Breakdown)' : 'Resource Usage Breakdown'}
                </CardTitle>
              </CardHeader>
              <CardContent>
                {!usage?.resources?.length ? (
                  <p className="py-4 text-center text-sm text-muted-foreground">
                    {isFa ? 'هیچ منبعی در این پروژه تعریف نشده است.' : 'No resources in this project.'}
                  </p>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm rtl:text-right">
                      <thead className="border-b bg-muted/40 text-xs uppercase text-muted-foreground">
                        <tr>
                          <th className="px-4 py-3">{isFa ? 'نام منبع' : 'Resource Name'}</th>
                          <th className="px-4 py-3">{isFa ? 'نوع' : 'Type'}</th>
                          <th className="px-4 py-3">{isFa ? 'دیسک مصرفی' : 'Disk Used'}</th>
                          <th className="px-4 py-3">{isFa ? 'حافظه مصرفی' : 'Memory'}</th>
                          <th className="px-4 py-3">{isFa ? 'پردازنده' : 'CPU'}</th>
                          <th className="px-4 py-3">{isFa ? 'تعداد فایل (Inode)' : 'Inodes'}</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {usage.resources.map((r) => (
                          <tr key={r.id} className="hover:bg-muted/30">
                            <td className="px-4 py-3 font-medium">
                              <Link
                                href={dashboardHref(locale, `admin/platform/resources/${r.id}`)}
                                className="text-primary hover:underline"
                              >
                                {r.name}
                              </Link>
                            </td>
                            <td className="px-4 py-3 text-xs font-mono">{r.type}</td>
                            <td className="px-4 py-3 font-mono text-xs">
                              {r.disk_bytes > 1024 * 1024 * 1024
                                ? `${(r.disk_bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`
                                : `${Math.round(r.disk_bytes / (1024 * 1024))} MB`}
                            </td>
                            <td className="px-4 py-3 font-mono text-xs">
                              {r.mem_bytes > 1024 * 1024 * 1024
                                ? `${(r.mem_bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`
                                : `${Math.round(r.mem_bytes / (1024 * 1024))} MB`}
                            </td>
                            <td className="px-4 py-3 font-mono text-xs">{Math.round(r.cpu_usage)}%</td>
                            <td className="px-4 py-3 font-mono text-xs">{r.inode_count.toLocaleString()}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Quota Settings Form */}
            {quota && (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base font-semibold">
                    {isFa ? 'تنظیمات سقف منابع پروژه (Project Quota)' : 'Project Quota Settings'}
                  </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                  <div className="grid gap-2">
                    <Label>{isFa ? 'سقف دیسک نرم (MB)' : 'Disk Soft Quota (MB)'}</Label>
                    <Input
                      type="number"
                      value={quota.disk_mb_soft ?? ''}
                      onChange={(e) => setQuota({ ...quota, disk_mb_soft: Number(e.target.value) || null })}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>{isFa ? 'سقف دیسک سخت (MB)' : 'Disk Hard Quota (MB)'}</Label>
                    <Input
                      type="number"
                      value={quota.disk_mb_hard ?? ''}
                      onChange={(e) => setQuota({ ...quota, disk_mb_hard: Number(e.target.value) || null })}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>{isFa ? 'سقف حافظه نرم (MB)' : 'RAM Soft Quota (MB)'}</Label>
                    <Input
                      type="number"
                      value={quota.ram_mb_soft ?? ''}
                      onChange={(e) => setQuota({ ...quota, ram_mb_soft: Number(e.target.value) || null })}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>{isFa ? 'سقف حافظه سخت (MB)' : 'RAM Hard Quota (MB)'}</Label>
                    <Input
                      type="number"
                      value={quota.ram_mb_hard ?? ''}
                      onChange={(e) => setQuota({ ...quota, ram_mb_hard: Number(e.target.value) || null })}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>{isFa ? 'سقف پردازنده نرم (%)' : 'CPU Soft Quota (%)'}</Label>
                    <Input
                      type="number"
                      value={quota.cpu_percent_soft ?? ''}
                      onChange={(e) => setQuota({ ...quota, cpu_percent_soft: Number(e.target.value) || null })}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>{isFa ? 'سقف پردازنده سخت (%)' : 'CPU Hard Quota (%)'}</Label>
                    <Input
                      type="number"
                      value={quota.cpu_percent_hard ?? ''}
                      onChange={(e) => setQuota({ ...quota, cpu_percent_hard: Number(e.target.value) || null })}
                    />
                  </div>
                  <div className="md:col-span-2 flex items-center justify-between pt-2">
                    <div className="flex items-center gap-2">
                      <Label>{isFa ? 'عملیات هنگام نقض:' : 'Enforce Action:'}</Label>
                      <select
                        className="rounded-md border p-1 text-sm bg-background"
                        value={quota.enforce_action}
                        onChange={(e) =>
                          setQuota({ ...quota, enforce_action: e.target.value as 'alert' | 'throttle' | 'suspend' })
                        }
                      >
                        <option value="alert">Alert (هشدار)</option>
                        <option value="throttle">Throttle (محدودسازی)</option>
                        <option value="suspend">Suspend (تعلیق)</option>
                      </select>
                    </div>
                    <Button disabled={savingQuota} onClick={() => void saveQuota()}>
                      {savingQuota ? (isFa ? 'در حال ذخیره...' : 'Saving...') : isFa ? 'ذخیره سقف پروژه' : 'Save Quota'}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            )}
          </TabsContent>
        </Tabs>
      ) : (
        <p className="text-sm text-muted-foreground">{tP('loading')}</p>
      )}
    </PlatformPageLayout>
  );
}
