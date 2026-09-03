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
import { dashboardHref } from '@/lib/route-resolver';
import {
  bootstrapServer,
  cleanupServer,
  deleteServerImage,
  execServerTerminal,
  fetchServer,
  fetchServerContainerLogs,
  fetchServerImages,
  fetchServerMetrics,
  fetchServerNetworks,
  fetchServerProxy,
  fetchServerResources,
  fetchDestinations,
  pullServerImage,
  reloadProxy,
  createServerNetwork,
  serverContainerAction,
  updateServer,
  validateServer,
  type PlatformServer,
  type PlatformServerImage,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

type Props = { id: string };

export function ServerDetailPage({ id }: Props) {
  const t = useTranslations('platform.servers');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const [server, setServer] = useState<PlatformServer | null>(null);
  const [resources, setResources] = useState<unknown[]>([]);
  const [images, setImages] = useState<PlatformServerImage[]>([]);
  const [networks, setNetworks] = useState<unknown[]>([]);
  const [destinations, setDestinations] = useState<Array<{ id: number; name: string; network_name: string }>>([]);
  const [newNetwork, setNewNetwork] = useState('webino');
  const [pullRef, setPullRef] = useState('');
  const [showRawImages, setShowRawImages] = useState(false);
  const [validateResult, setValidateResult] = useState<string | null>(null);
  const [bootstrapResult, setBootstrapResult] = useState<string | null>(null);
  const [metrics, setMetrics] = useState<unknown>(null);
  const [proxy, setProxy] = useState('');
  const [cmd, setCmd] = useState('uname -a');
  const [termOut, setTermOut] = useState('');
  const [containerLogs, setContainerLogs] = useState('');
  const [selectedContainer, setSelectedContainer] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      setServer(await fetchServer(id));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [id, tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function loadTab(tab: string) {
    setError(null);
    try {
      if (tab === 'resources') setResources(await fetchServerResources(id));
      if (tab === 'metrics') setMetrics(await fetchServerMetrics(id));
      if (tab === 'proxy') {
        const p = await fetchServerProxy(id);
        setProxy(p.raw ?? '');
      }
      if (tab === 'destinations') {
        setNetworks(await fetchServerNetworks(id));
        setImages(await fetchServerImages(id));
        setDestinations(await fetchDestinations(id));
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }

  async function saveGeneral() {
    if (!server) return;
    setPending(true);
    try {
      const updated = await updateServer(id, {
        name: server.name,
        ip: server.ip,
        port: server.port,
        user: server.user,
        proxy_type: server.proxy_type,
      });
      setServer(updated);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function runTerminal() {
    setPending(true);
    try {
      const r = await execServerTerminal(id, cmd);
      setTermOut(`${r.stdout ?? ''}${r.stderr ? `\n${r.stderr}` : ''}`.trim() || t('noOutput'));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function runValidate() {
    setPending(true);
    setValidateResult(null);
    try {
      const r = await validateServer(id);
      setServer(r.server);
      setValidateResult(JSON.stringify(r.result, null, 2));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function runBootstrap() {
    setPending(true);
    setBootstrapResult(null);
    try {
      const r = await bootstrapServer(id);
      setServer(r.server);
      setBootstrapResult(JSON.stringify(r.result, null, 2));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handlePullImage() {
    if (!pullRef.trim()) return;
    setPending(true);
    try {
      await pullServerImage(id, pullRef.trim());
      setPullRef('');
      await loadTab('destinations');
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleDeleteImage(ref: string) {
    if (!window.confirm(t('confirmDeleteImage', { ref }))) return;
    setPending(true);
    try {
      await deleteServerImage(id, ref);
      await loadTab('destinations');
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  function parseImageRow(img: PlatformServerImage | Record<string, unknown>): PlatformServerImage {
    if ('ref' in img && typeof img.ref === 'string') return img as PlatformServerImage;
    const row = img as Record<string, unknown>;
    return {
      ref: String(row.Repository ?? row.repository ?? row.ref ?? row.name ?? '—'),
      id: String(row.ID ?? row.Id ?? row.id ?? '—'),
      size: String(row.Size ?? row.size ?? '—'),
    };
  }

  async function runCleanup() {
    setPending(true);
    try {
      await cleanupServer(id);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout
      title={server?.name ?? t('title')}
      subtitle={server ? `${server.ip}:${server.port ?? 22}` : undefined}
      error={error}
      actions={
        <>
          <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, 'admin/platform/servers')}>{tP('back')}</Link></Button>
          <RefreshButton onClick={() => void load()} label={tP('refresh')} />
        </>
      }
    >
      {server ? (
        <Tabs defaultValue="general" onValueChange={(v) => void loadTab(v)}>
          <TabsList className="flex h-auto flex-wrap">
            <TabsTrigger value="general">{t('tabGeneral')}</TabsTrigger>
            <TabsTrigger value="proxy">{t('tabProxy')}</TabsTrigger>
            <TabsTrigger value="resources">{t('tabResources')}</TabsTrigger>
            <TabsTrigger value="terminal">{t('tabTerminal')}</TabsTrigger>
            <TabsTrigger value="destinations">{t('tabDestinations')}</TabsTrigger>
            <TabsTrigger value="metrics">{t('tabMetrics')}</TabsTrigger>
            <TabsTrigger value="cleanup">{t('tabCleanup')}</TabsTrigger>
          </TabsList>

          <TabsContent value="general">
            <Card>
              <CardContent className="grid gap-3 pt-6 md:grid-cols-2">
                <div className="grid gap-2"><Label>{t('name')}</Label><Input value={server.name} onChange={(e) => setServer({ ...server, name: e.target.value })} /></div>
                <div className="grid gap-2"><Label>{t('ip')}</Label><Input value={server.ip} onChange={(e) => setServer({ ...server, ip: e.target.value })} dir="ltr" className="font-mono" /></div>
                <div className="grid gap-2"><Label>{t('port')}</Label><Input value={String(server.port ?? 22)} onChange={(e) => setServer({ ...server, port: Number(e.target.value) })} dir="ltr" /></div>
                <div className="grid gap-2"><Label>{t('user')}</Label><Input value={server.user ?? 'root'} onChange={(e) => setServer({ ...server, user: e.target.value })} dir="ltr" /></div>
                <div className="md:col-span-2 flex flex-wrap items-center gap-2">
                  <Badge variant="secondary">{server.status ?? '—'}</Badge>
                  {server.ssh_key?.name ? <Badge variant="outline">{server.ssh_key.name}</Badge> : null}
                </div>
                <div className="md:col-span-2 flex flex-wrap gap-2">
                  <Button disabled={pending} onClick={() => void saveGeneral()}>{tP('save')}</Button>
                  <Button variant="outline" disabled={pending} onClick={() => void runValidate()}>{t('validate')}</Button>
                  <Button variant="secondary" disabled={pending} onClick={() => void runBootstrap()}>{t('bootstrap')}</Button>
                </div>
                {validateResult ? <pre className="md:col-span-2 max-h-48 overflow-auto rounded-md border bg-muted/30 p-3 text-xs font-mono whitespace-pre-wrap">{validateResult}</pre> : null}
                {bootstrapResult ? <pre className="md:col-span-2 max-h-48 overflow-auto rounded-md border bg-muted/30 p-3 text-xs font-mono whitespace-pre-wrap">{bootstrapResult}</pre> : null}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="proxy">
            <Card>
              <CardHeader><CardTitle className="text-base">{t('tabProxy')}</CardTitle></CardHeader>
              <CardContent>
                <div className="mb-3 flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" onClick={() => void loadTab('proxy')}>{tP('refresh')}</Button>
                  <Button size="sm" variant="secondary" disabled={pending} onClick={async () => {
                    setPending(true);
                    try {
                      await reloadProxy(id);
                      await loadTab('proxy');
                    } catch (e) {
                      setError(e instanceof Error ? e.message : tP('saveError'));
                    } finally {
                      setPending(false);
                    }
                  }}>{t('reloadProxy')}</Button>
                </div>
                <pre className="max-h-96 overflow-auto rounded-md border bg-muted/30 p-3 text-xs font-mono whitespace-pre-wrap">{proxy || t('noData')}</pre>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="resources">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">{t('tabResources')}</CardTitle>
                <Button size="sm" variant="outline" onClick={() => void loadTab('resources')}>{tP('refresh')}</Button>
              </CardHeader>
              <CardContent className="space-y-2">
                {resources.map((r, i) => {
                  const row = r as Record<string, unknown>;
                  const cid = String(row.ID ?? row.Id ?? row.id ?? row.Names ?? i);
                  return (
                    <div key={cid} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm">
                      <span className="font-mono text-xs">{cid}</span>
                      <div className="flex gap-2">
                        {(['start', 'stop', 'restart'] as const).map((action) => (
                          <Button key={action} size="sm" variant="outline" disabled={pending} onClick={async () => {
                            setPending(true);
                            try {
                              await serverContainerAction(id, cid, action);
                              await loadTab('resources');
                            } catch (e) {
                              setError(e instanceof Error ? e.message : tP('saveError'));
                            } finally {
                              setPending(false);
                            }
                          }}>{action}</Button>
                        ))}
                        <Button size="sm" variant="secondary" disabled={pending} onClick={async () => {
                          setSelectedContainer(cid);
                          const logs = await fetchServerContainerLogs(id, cid);
                          setContainerLogs(logs.logs ?? '');
                        }}>{t('logs')}</Button>
                      </div>
                    </div>
                  );
                })}
                {!resources.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
                {containerLogs ? <pre className="max-h-64 overflow-auto rounded-md border bg-muted/30 p-3 text-xs font-mono whitespace-pre-wrap">{containerLogs}</pre> : null}
                {selectedContainer ? <p className="text-xs text-muted-foreground">{t('logsFor', { name: selectedContainer })}</p> : null}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="terminal">
            <Card>
              <CardContent className="grid gap-3 pt-6">
                <div className="grid gap-2">
                  <Label>{t('command')}</Label>
                  <Input value={cmd} onChange={(e) => setCmd(e.target.value)} dir="ltr" className="font-mono" />
                </div>
                <Button disabled={pending || !cmd} onClick={() => void runTerminal()}>{t('run')}</Button>
                <pre className="min-h-32 rounded-md border bg-muted/30 p-3 text-xs font-mono whitespace-pre-wrap">{termOut}</pre>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="destinations">
            <div className="grid gap-4">
              <Card>
                <CardHeader><CardTitle className="text-base">{t('destinations')}</CardTitle></CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex flex-wrap gap-2">
                    <Input className="max-w-xs font-mono" dir="ltr" value={newNetwork} onChange={(e) => setNewNetwork(e.target.value)} placeholder="webino" />
                    <Button size="sm" disabled={pending || !newNetwork.trim()} onClick={async () => {
                      setPending(true);
                      try {
                        await createServerNetwork(id, newNetwork.trim());
                        await loadTab('destinations');
                      } catch (e) {
                        setError(e instanceof Error ? e.message : tP('saveError'));
                      } finally {
                        setPending(false);
                      }
                    }}>{t('createNetwork')}</Button>
                  </div>
                  <ul className="space-y-2 text-sm">
                    {destinations.map((d) => (
                      <li key={d.id} className="rounded-md border px-3 py-2 font-mono text-xs" dir="ltr">
                        {d.name} → {d.network_name}
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>
              <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                  <CardHeader><CardTitle className="text-base">{t('networks')}</CardTitle></CardHeader>
                  <CardContent><pre className="text-xs font-mono whitespace-pre-wrap">{JSON.stringify(networks, null, 2)}</pre></CardContent>
                </Card>
                <Card>
                  <CardHeader><CardTitle className="text-base">{t('images')}</CardTitle></CardHeader>
                  <CardContent className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                      <Input className="max-w-md font-mono" dir="ltr" value={pullRef} onChange={(e) => setPullRef(e.target.value)} placeholder={t('pullImagePlaceholder')} />
                      <Button size="sm" disabled={pending || !pullRef.trim()} onClick={() => void handlePullImage()}>{t('pullImage')}</Button>
                      <Button size="sm" variant="outline" onClick={() => setShowRawImages((v) => !v)}>{showRawImages ? t('hideRaw') : t('showRaw')}</Button>
                    </div>
                    <ul className="space-y-2">
                      {images.map((img, i) => {
                        const row = parseImageRow(img);
                        return (
                          <li key={`${row.id}-${i}`} className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
                            <div className="min-w-0">
                              <p className="font-mono text-xs truncate" dir="ltr">{row.ref}</p>
                              <p className="text-xs text-muted-foreground">{row.id} · {row.size}</p>
                            </div>
                            <Button size="sm" variant="destructive" disabled={pending || !row.ref || row.ref === '—'} onClick={() => void handleDeleteImage(row.ref)}>{tP('delete')}</Button>
                          </li>
                        );
                      })}
                    </ul>
                    {!images.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
                    {showRawImages ? <pre className="text-xs font-mono whitespace-pre-wrap">{JSON.stringify(images, null, 2)}</pre> : null}
                  </CardContent>
                </Card>
              </div>
            </div>
          </TabsContent>

          <TabsContent value="metrics">
            <Card>
              <CardContent className="pt-6">
                <Button size="sm" variant="outline" className="mb-3" onClick={() => void loadTab('metrics')}>{tP('refresh')}</Button>
                <pre className="text-xs font-mono whitespace-pre-wrap">{JSON.stringify(metrics, null, 2) || tP('noData')}</pre>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="cleanup">
            <Card>
              <CardContent className="grid gap-3 pt-6">
                <p className="text-sm text-muted-foreground">{t('cleanupHint')}</p>
                <Button variant="destructive" disabled={pending} onClick={() => void runCleanup()}>{t('runCleanup')}</Button>
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
