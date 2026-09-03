'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { dashboardHref } from '@/lib/route-resolver';
import {
  addResourceDomain,
  cloneResource,
  deleteDomain,
  deleteResource,
  deployResource,
  ensureResourceWebhook,
  fetchProjects,
  fetchResource,
  fetchResourceDeployments,
  fetchServerContainerLogs,
  fetchServerResources,
  fetchServers,
  moveResource,
  refreshDomainSsl,
  startResource,
  stopResource,
  syncResourceEnv,
  syncResourceVolumes,
  updateResource,
  type PlatformDeployment,
  type PlatformDomain,
  type PlatformEnvVar,
  type PlatformProject,
  type PlatformResource,
  type PlatformServer,
  type PlatformVolume,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

type Props = { id: string };

export function ResourceDetailPage({ id }: Props) {
  const t = useTranslations('platform.resources');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const [resource, setResource] = useState<PlatformResource | null>(null);
  const [deployments, setDeployments] = useState<PlatformDeployment[]>([]);
  const [envVars, setEnvVars] = useState<PlatformEnvVar[]>([]);
  const [volumes, setVolumes] = useState<PlatformVolume[]>([]);
  const [newDomain, setNewDomain] = useState('');
  const [newEnvKey, setNewEnvKey] = useState('');
  const [newEnvValue, setNewEnvValue] = useState('');
  const [newVolName, setNewVolName] = useState('');
  const [newVolPath, setNewVolPath] = useState('');
  const [cloneName, setCloneName] = useState('');
  const [moveServerId, setMoveServerId] = useState<number | null>(null);
  const [moveEnvironmentId, setMoveEnvironmentId] = useState<number | null>(null);
  const [webhookUrl, setWebhookUrl] = useState('');
  const [containerLogs, setContainerLogs] = useState('');
  const [logsSource, setLogsSource] = useState<'container' | 'deployment' | null>(null);
  const [servers, setServers] = useState<PlatformServer[]>([]);
  const [projects, setProjects] = useState<PlatformProject[]>([]);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const row = await fetchResource(id);
      setResource(row);
      setEnvVars(row.envVars ?? row.env_vars ?? []);
      setVolumes(row.volumes ?? []);
      setDeployments(await fetchResourceDeployments(id));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [id, tP]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (resource) {
      setCloneName(`${resource.name}-copy`);
      setMoveServerId(resource.server_id);
      setMoveEnvironmentId(resource.environment_id);
    }
  }, [resource?.id, resource?.name, resource?.server_id, resource?.environment_id]);

  async function loadLogsTab() {
    if (!resource) return;
    setError(null);
    setContainerLogs('');
    setLogsSource(null);
    try {
      const containers = await fetchServerResources(resource.server_id);
      const slug = resource.name.toLowerCase();
      const match = containers.find((c) => {
        const row = c as Record<string, unknown>;
        const name = String(row.name ?? row.Names ?? '').toLowerCase();
        const id = String(row.id ?? row.ID ?? '');
        return name.includes(slug) || id.includes(slug);
      }) as Record<string, unknown> | undefined;
      const containerId = match ? String(match.id ?? match.ID ?? match.name ?? match.Names ?? '') : '';
      if (containerId) {
        const logs = await fetchServerContainerLogs(resource.server_id, containerId);
        setContainerLogs(logs.logs ?? '');
        setLogsSource('container');
        return;
      }
    } catch {
      // fall through to deployment logs
    }
    const latest = deployments.find((d) => d.logs || d.log);
    if (latest) {
      setContainerLogs(latest.logs ?? latest.log ?? '');
      setLogsSource('deployment');
    }
  }

  async function loadMoveOptions() {
    try {
      const [srv, prj] = await Promise.all([fetchServers(), fetchProjects()]);
      setServers(srv);
      setProjects(prj);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }

  async function loadWebhook() {
    setPending(true);
    try {
      const wh = await ensureResourceWebhook(id);
      setWebhookUrl(wh.url);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleClone() {
    if (!cloneName.trim()) return;
    setPending(true);
    try {
      const copy = await cloneResource(id, { name: cloneName.trim() });
      window.location.href = dashboardHref(locale, `admin/platform/resources/${copy.id}`);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleMove() {
    setPending(true);
    try {
      setResource(await moveResource(id, {
        server_id: moveServerId,
        environment_id: moveEnvironmentId,
      }));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function copyWebhook() {
    if (!webhookUrl) return;
    try {
      await navigator.clipboard.writeText(webhookUrl);
    } catch {
      // ignore clipboard errors
    }
  }

  async function refreshSsl(domainId: number) {
    setPending(true);
    try {
      await refreshDomainSsl(domainId);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  const environments = projects.flatMap((p) => (p.environments ?? []).map((env) => ({ ...env, projectName: p.name })));

  async function saveGeneral() {
    if (!resource) return;
    setPending(true);
    try {
      setResource(await updateResource(id, {
        name: resource.name,
        fqdn: resource.fqdn,
        git_repository: resource.git_repository,
        git_branch: resource.git_branch,
        docker_image: resource.docker_image,
        ports_exposes: resource.ports_exposes,
        settings: resource.settings ?? {},
      }));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  function patchSettings(patch: Record<string, unknown>) {
    if (!resource) return;
    setResource({
      ...resource,
      settings: { ...(resource.settings ?? {}), ...patch },
    });
  }

  async function handleDeploy() {
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

  async function handleLifecycle(action: 'start' | 'stop') {
    setPending(true);
    try {
      if (action === 'start') await startResource(id);
      else await stopResource(id);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function saveEnv() {
    setPending(true);
    try {
      const vars = envVars.map((v) => ({
        key: v.key,
        value: v.value,
        is_secret: v.is_secret,
        is_buildtime: v.is_buildtime,
        is_runtime: v.is_runtime,
        is_preview: v.is_preview,
      }));
      setEnvVars(await syncResourceEnv(id, vars));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function saveVolumes() {
    setPending(true);
    try {
      const vols = volumes.map((v) => ({
        name: v.name,
        mount_path: v.mount_path,
        host_path: v.host_path,
        is_file: v.is_file,
      }));
      setVolumes(await syncResourceVolumes(id, vols));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function addDomain() {
    if (!newDomain.trim()) return;
    setPending(true);
    try {
      await addResourceDomain(id, { domain: newDomain.trim(), force_https: true });
      setNewDomain('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function removeDomain(domainId: number) {
    setPending(true);
    try {
      await deleteDomain(domainId);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function destroyResource() {
    if (!window.confirm(t('confirmDelete'))) return;
    setPending(true);
    try {
      await deleteResource(id);
      window.location.href = dashboardHref(locale, 'admin/platform/resources');
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  const domains: PlatformDomain[] = resource?.domains ?? [];

  return (
    <PlatformPageLayout
      title={resource?.name ?? t('title')}
      subtitle={resource?.fqdn ?? undefined}
      error={error}
      actions={
        <>
          <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, 'admin/platform/resources')}>{tP('back')}</Link></Button>
          <RefreshButton onClick={() => void load()} label={tP('refresh')} />
          <Button size="sm" disabled={pending} onClick={() => void handleDeploy()}>{t('deploy')}</Button>
        </>
      }
    >
      {resource ? (
        <Tabs defaultValue="general" onValueChange={(v) => {
          if (v === 'logs') void loadLogsTab();
          if (v === 'move') void loadMoveOptions();
          if (v === 'webhook' && !webhookUrl) void loadWebhook();
          if (v === 'advanced' && !webhookUrl) void loadWebhook();
        }}>
          <TabsList className="flex h-auto flex-wrap">
            <TabsTrigger value="general">{t('tabGeneral')}</TabsTrigger>
            <TabsTrigger value="logs">{t('tabLogs')}</TabsTrigger>
            <TabsTrigger value="clone">{t('tabClone')}</TabsTrigger>
            <TabsTrigger value="move">{t('tabMove')}</TabsTrigger>
            <TabsTrigger value="webhook">{t('tabWebhook')}</TabsTrigger>
            <TabsTrigger value="advanced">{t('tabAdvanced')}</TabsTrigger>
            <TabsTrigger value="env">{t('tabEnv')}</TabsTrigger>
            <TabsTrigger value="storage">{t('tabStorage')}</TabsTrigger>
            <TabsTrigger value="deployments">{t('tabDeployments')}</TabsTrigger>
            <TabsTrigger value="domains">{t('tabDomains')}</TabsTrigger>
            <TabsTrigger value="danger">{t('tabDanger')}</TabsTrigger>
          </TabsList>

          <TabsContent value="general">
            <Card>
              <CardContent className="grid gap-3 pt-6 md:grid-cols-2">
                <div className="grid gap-2"><Label>{t('name')}</Label><Input value={resource.name} onChange={(e) => setResource({ ...resource, name: e.target.value })} /></div>
                <div className="grid gap-2"><Label>{t('fqdn')}</Label><Input value={resource.fqdn ?? ''} onChange={(e) => setResource({ ...resource, fqdn: e.target.value })} dir="ltr" className="font-mono" /></div>
                <div className="md:col-span-2 flex gap-2">
                  <Badge variant="secondary">{resource.type}</Badge>
                  <Badge variant="outline">{resource.status ?? '—'}</Badge>
                  {resource.build_pack ? <Badge variant="outline">{resource.build_pack}</Badge> : null}
                </div>
                <div className="md:col-span-2 flex flex-wrap gap-2">
                  <Button disabled={pending} onClick={() => void saveGeneral()}>{tP('save')}</Button>
                  <Button variant="outline" disabled={pending} onClick={() => void handleLifecycle('start')}>{t('start')}</Button>
                  <Button variant="outline" disabled={pending} onClick={() => void handleLifecycle('stop')}>{t('stop')}</Button>
                </div>
                {resource.type === 'database' ? (
                  <div className="md:col-span-2 grid gap-3 rounded-md border p-4">
                    <p className="text-sm font-medium">{t('databaseInfo')}</p>
                    <div className="grid gap-2">
                      <Label>{t('databaseType')}</Label>
                      <Input value={resource.database_type ?? '—'} readOnly dir="ltr" className="font-mono bg-muted/30" />
                    </div>
                    <div className="grid gap-2">
                      <Label>{t('connectionUrl')}</Label>
                      <Input value={String(resource.settings?.connection_url ?? '—')} readOnly dir="ltr" className="font-mono bg-muted/30" />
                    </div>
                    <Button asChild variant="outline" size="sm" className="w-fit">
                      <Link href={dashboardHref(locale, 'admin/platform/backups')}>{t('backupShortcut')}</Link>
                    </Button>
                  </div>
                ) : null}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="logs">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">{t('tabLogs')}</CardTitle>
                <Button size="sm" variant="outline" onClick={() => void loadLogsTab()}>{tP('refresh')}</Button>
              </CardHeader>
              <CardContent className="space-y-2">
                {logsSource ? <p className="text-xs text-muted-foreground">{logsSource === 'container' ? t('logsFromContainer') : t('logsFromDeployment')}</p> : null}
                <pre className="max-h-96 overflow-auto rounded-md border bg-muted/30 p-3 text-xs font-mono whitespace-pre-wrap">{containerLogs || t('noLogs')}</pre>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="clone">
            <Card>
              <CardContent className="grid gap-3 pt-6 md:max-w-md">
                <div className="grid gap-2">
                  <Label>{t('cloneName')}</Label>
                  <Input value={cloneName} onChange={(e) => setCloneName(e.target.value)} />
                </div>
                <Button disabled={pending || !cloneName.trim()} onClick={() => void handleClone()}>{t('cloneResource')}</Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="move">
            <Card>
              <CardContent className="grid gap-3 pt-6 md:grid-cols-2">
                <div className="grid gap-2">
                  <Label>{t('server')}</Label>
                  <select className="border rounded-md h-10 px-3 bg-background" value={moveServerId ?? ''} onChange={(e) => setMoveServerId(e.target.value ? Number(e.target.value) : null)}>
                    <option value="">{tP('select')}</option>
                    {servers.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.ip})</option>)}
                  </select>
                </div>
                <div className="grid gap-2">
                  <Label>{t('environment')}</Label>
                  <select className="border rounded-md h-10 px-3 bg-background" value={moveEnvironmentId ?? ''} onChange={(e) => setMoveEnvironmentId(e.target.value ? Number(e.target.value) : null)}>
                    <option value="">{tP('select')}</option>
                    {environments.map((env) => <option key={env.id} value={env.id}>{env.projectName} / {env.name}</option>)}
                  </select>
                </div>
                <Button disabled={pending} onClick={() => void handleMove()}>{t('moveResource')}</Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="webhook">
            <Card>
              <CardContent className="grid gap-3 pt-6 md:max-w-2xl">
                <p className="text-sm text-muted-foreground">{t('webhookHint')}</p>
                <div className="grid gap-2">
                  <Label>{t('deployWebhook')}</Label>
                  <div className="flex flex-wrap gap-2">
                    <Input value={webhookUrl} readOnly dir="ltr" className="font-mono flex-1 bg-muted/30" />
                    <Button variant="outline" disabled={!webhookUrl} onClick={() => void copyWebhook()}>{t('copyWebhook')}</Button>
                    <Button variant="secondary" disabled={pending} onClick={() => void loadWebhook()}>{t('regenerateWebhook')}</Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="advanced">
            <Card>
              <CardContent className="grid gap-3 pt-6">
                <div className="grid gap-2"><Label>{t('gitRepository')}</Label><Input value={resource.git_repository ?? ''} onChange={(e) => setResource({ ...resource, git_repository: e.target.value })} dir="ltr" className="font-mono" /></div>
                <div className="grid gap-2"><Label>{t('gitBranch')}</Label><Input value={resource.git_branch ?? ''} onChange={(e) => setResource({ ...resource, git_branch: e.target.value })} dir="ltr" /></div>
                <div className="grid gap-2"><Label>{t('dockerImage')}</Label><Input value={resource.docker_image ?? ''} onChange={(e) => setResource({ ...resource, docker_image: e.target.value })} dir="ltr" className="font-mono" /></div>
                <div className="grid gap-2"><Label>{t('ports')}</Label><Input value={String(resource.ports_exposes ?? '')} onChange={(e) => setResource({ ...resource, ports_exposes: Number(e.target.value) || null })} dir="ltr" /></div>
                <div className="grid gap-2"><Label>{t('healthcheck')}</Label><Input value={String(resource.settings?.healthcheck_path ?? '/up')} onChange={(e) => patchSettings({ healthcheck_path: e.target.value })} dir="ltr" className="font-mono" /></div>
                <div className="grid gap-2"><Label>{t('rollingUpdate')}</Label><Input value={String(resource.settings?.rolling_update ?? 'false')} onChange={(e) => patchSettings({ rolling_update: e.target.value })} dir="ltr" /></div>
                <div className="grid gap-2"><Label>{t('cpuLimit')}</Label><Input value={String(resource.settings?.cpu_limit ?? '')} onChange={(e) => patchSettings({ cpu_limit: e.target.value })} dir="ltr" /></div>
                <div className="grid gap-2"><Label>{t('memoryLimit')}</Label><Input value={String(resource.settings?.memory_limit ?? '')} onChange={(e) => patchSettings({ memory_limit: e.target.value })} dir="ltr" /></div>
                <div className="grid gap-2"><Label>{t('redirects')}</Label><Input value={String(resource.settings?.redirects ?? '')} onChange={(e) => patchSettings({ redirects: e.target.value })} dir="ltr" placeholder="old.example → new.example" /></div>
                <div className="grid gap-2"><Label>{t('scheduleCron')}</Label><Input value={String(resource.settings?.schedule_cron ?? '')} onChange={(e) => patchSettings({ schedule_cron: e.target.value })} dir="ltr" placeholder="0 * * * *" /></div>
                <div className="grid gap-2">
                  <Label>{t('deployWebhook')}</Label>
                  <div className="flex flex-wrap gap-2">
                    <Input value={webhookUrl} readOnly dir="ltr" className="font-mono flex-1 bg-muted/30" />
                    <Button variant="outline" disabled={!webhookUrl} onClick={() => void copyWebhook()}>{t('copyWebhook')}</Button>
                  </div>
                  <p className="text-xs text-muted-foreground">{t('webhookManaged')}</p>
                </div>
                <div className="grid gap-2"><Label>{t('previewUrlTemplate')}</Label><Input value={String(resource.settings?.preview_url_template ?? 'https://pr-{PR_NUMBER}.{DOMAIN}')} onChange={(e) => patchSettings({ preview_url_template: e.target.value })} dir="ltr" className="font-mono" /></div>
                <Button disabled={pending} onClick={() => void saveGeneral()}>{tP('save')}</Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="env">
            <Card>
              <CardContent className="grid gap-3 pt-6">
                {envVars.map((v, i) => (
                  <div key={`${v.key}-${i}`} className="grid gap-2 rounded-md border p-3 md:grid-cols-3">
                    <Input value={v.key} onChange={(e) => setEnvVars((prev) => prev.map((x, j) => j === i ? { ...x, key: e.target.value } : x))} dir="ltr" />
                    <Input value={v.value ?? ''} onChange={(e) => setEnvVars((prev) => prev.map((x, j) => j === i ? { ...x, value: e.target.value } : x))} dir="ltr" />
                    <div className="flex items-center gap-2 text-xs">
                      <Switch checked={!!v.is_secret} onCheckedChange={(on) => setEnvVars((prev) => prev.map((x, j) => j === i ? { ...x, is_secret: on } : x))} />
                      <span>{t('secret')}</span>
                    </div>
                  </div>
                ))}
                <div className="flex flex-wrap gap-2">
                  <Input placeholder={t('envKey')} value={newEnvKey} onChange={(e) => setNewEnvKey(e.target.value)} className="max-w-xs" dir="ltr" />
                  <Input placeholder={t('envValue')} value={newEnvValue} onChange={(e) => setNewEnvValue(e.target.value)} className="max-w-xs" dir="ltr" />
                  <Button variant="outline" onClick={() => {
                    if (!newEnvKey) return;
                    setEnvVars((prev) => [...prev, { id: 0, resource_id: Number(id), key: newEnvKey, value: newEnvValue }]);
                    setNewEnvKey('');
                    setNewEnvValue('');
                  }}>{t('addEnv')}</Button>
                </div>
                <Button disabled={pending} onClick={() => void saveEnv()}>{tP('save')}</Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="storage">
            <Card>
              <CardContent className="grid gap-3 pt-6">
                {volumes.map((v, i) => (
                  <div key={`${v.name}-${i}`} className="grid gap-2 rounded-md border p-3 md:grid-cols-2">
                    <Input value={v.name} onChange={(e) => setVolumes((prev) => prev.map((x, j) => j === i ? { ...x, name: e.target.value } : x))} />
                    <Input value={v.mount_path} onChange={(e) => setVolumes((prev) => prev.map((x, j) => j === i ? { ...x, mount_path: e.target.value } : x))} dir="ltr" />
                  </div>
                ))}
                <div className="flex flex-wrap gap-2">
                  <Input placeholder={t('volumeName')} value={newVolName} onChange={(e) => setNewVolName(e.target.value)} className="max-w-xs" />
                  <Input placeholder={t('mountPath')} value={newVolPath} onChange={(e) => setNewVolPath(e.target.value)} className="max-w-xs" dir="ltr" />
                  <Button variant="outline" onClick={() => {
                    if (!newVolName || !newVolPath) return;
                    setVolumes((prev) => [...prev, { id: 0, resource_id: Number(id), name: newVolName, mount_path: newVolPath }]);
                    setNewVolName('');
                    setNewVolPath('');
                  }}>{t('addVolume')}</Button>
                </div>
                <Button disabled={pending} onClick={() => void saveVolumes()}>{tP('save')}</Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="deployments">
            <Card>
              <CardHeader><CardTitle className="text-base">{t('tabDeployments')}</CardTitle></CardHeader>
              <CardContent className="space-y-2">
                {deployments.map((d) => (
                  <div key={d.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
                    <span>#{d.id}</span>
                    <Badge variant="secondary">{d.status}</Badge>
                    <span className="font-mono text-xs">{d.started_at ?? '—'}</span>
                  </div>
                ))}
                {!deployments.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="domains">
            <Card>
              <CardContent className="grid gap-3 pt-6">
                {domains.map((d) => (
                  <div key={d.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
                    <span className="font-mono">{d.domain}</span>
                    <div className="flex items-center gap-2">
                      <Badge variant="outline">{d.ssl_status ?? '—'}</Badge>
                      <Button size="sm" variant="outline" disabled={pending} onClick={() => void refreshSsl(d.id)}>{t('refreshSsl')}</Button>
                      <Button size="sm" variant="destructive" disabled={pending} onClick={() => void removeDomain(d.id)}>{tP('delete')}</Button>
                    </div>
                  </div>
                ))}
                <div className="flex flex-wrap gap-2">
                  <Input value={newDomain} onChange={(e) => setNewDomain(e.target.value)} placeholder="app.example.com" dir="ltr" className="font-mono max-w-sm" />
                  <Button disabled={pending || !newDomain.trim()} onClick={() => void addDomain()}>{t('addDomain')}</Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="danger">
            <Card>
              <CardContent className="grid gap-3 pt-6">
                <p className="text-sm text-muted-foreground">{t('dangerHint')}</p>
                <Button variant="destructive" disabled={pending} onClick={() => void destroyResource()}>{t('destroyResource')}</Button>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      ) : (
        <p className="text-sm text-muted-foreground">{tP('loading')}</p>
      )}
    </PlatformPageLayout>
  );
}
