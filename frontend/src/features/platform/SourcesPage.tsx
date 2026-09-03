'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { createSource, deleteSource, fetchSources, type PlatformSource } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function SourcesPage() {
  const t = useTranslations('platform.sources');
  const tP = useTranslations('platform');
  const [rows, setRows] = useState<PlatformSource[]>([]);
  const [name, setName] = useState('');
  const [provider, setProvider] = useState('github');
  const [baseUrl, setBaseUrl] = useState('');
  const [token, setToken] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [createdHint, setCreatedHint] = useState<{ provider: string; name: string } | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchSources());
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCreate() {
    setPending(true);
    const createdName = name;
    try {
      await createSource({ name, provider, base_url: baseUrl || undefined, token: token || undefined });
      setName('');
      setToken('');
      setCreatedHint({ provider, name: createdName });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  const webhookPattern = '/api/v1/platform/webhooks/deploy/{token}';

  return (
    <PlatformPageLayout title={t('title')} subtitle={t('subtitle')} error={error} actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}>
      {createdHint ? (
        <div className="mb-4 rounded-md border border-dashed p-4 text-sm space-y-2">
          <p className="font-medium">{t('createdHint', { name: createdHint.name, provider: createdHint.provider })}</p>
          <p className="text-muted-foreground">{t('webhookNote')}</p>
          <code className="block rounded bg-muted/50 px-2 py-1 font-mono text-xs" dir="ltr">{webhookPattern}</code>
        </div>
      ) : null}
      <Card><CardContent className="grid gap-3 pt-6 md:grid-cols-2">
        <div className="grid gap-2"><Label>{t('name')}</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
        <div className="grid gap-2">
          <Label>{t('provider')}</Label>
          <select className="border rounded-md h-10 px-3 bg-background" value={provider} onChange={(e) => setProvider(e.target.value)}>
            {['github', 'gitlab', 'gitea', 'bitbucket'].map((p) => <option key={p} value={p}>{p}</option>)}
          </select>
        </div>
        <div className="grid gap-2"><Label>{t('baseUrl')}</Label><Input value={baseUrl} onChange={(e) => setBaseUrl(e.target.value)} dir="ltr" /></div>
        <div className="grid gap-2"><Label>{t('token')}</Label><Input type="password" value={token} onChange={(e) => setToken(e.target.value)} dir="ltr" /></div>
        <Button disabled={pending || !name} onClick={() => void handleCreate()}>{t('add')}</Button>
      </CardContent></Card>
      <div className="grid gap-2">
        {rows.map((row) => (
          <div key={row.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
            <div><p className="font-medium">{row.name}</p><p className="text-muted-foreground">{row.provider}</p></div>
            <Button size="sm" variant="destructive" disabled={pending} onClick={async () => { await deleteSource(row.id); await load(); }}>{tP('delete')}</Button>
          </div>
        ))}
      </div>
    </PlatformPageLayout>
  );
}
