'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { createSshKey, deleteSshKey, fetchSshKeys, type PlatformSshKey } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function KeysPage() {
  const t = useTranslations('platform.keys');
  const tP = useTranslations('platform');
  const [rows, setRows] = useState<PlatformSshKey[]>([]);
  const [name, setName] = useState('');
  const [privateKey, setPrivateKey] = useState('');
  const [publicKey, setPublicKey] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchSshKeys());
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCreate() {
    setPending(true);
    try {
      await createSshKey({ name, private_key: privateKey, public_key: publicKey || undefined });
      setName('');
      setPrivateKey('');
      setPublicKey('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout title={t('title')} subtitle={t('subtitle')} error={error} actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}>
      <Card><CardContent className="grid gap-3 pt-6">
        <div className="grid gap-2"><Label>{t('name')}</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
        <div className="grid gap-2"><Label>{t('privateKey')}</Label><textarea className="min-h-24 w-full rounded-md border bg-background p-2 font-mono text-xs" value={privateKey} onChange={(e) => setPrivateKey(e.target.value)} /></div>
        <div className="grid gap-2"><Label>{t('publicKey')}</Label><textarea className="min-h-16 w-full rounded-md border bg-background p-2 font-mono text-xs" value={publicKey} onChange={(e) => setPublicKey(e.target.value)} /></div>
        <Button disabled={pending || !name || !privateKey} onClick={() => void handleCreate()}>{t('add')}</Button>
      </CardContent></Card>
      <div className="grid gap-2">
        {rows.map((row) => (
          <div key={row.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
            <div><p className="font-medium">{row.name}</p><p className="font-mono text-xs text-muted-foreground">{row.fingerprint ?? '—'}</p></div>
            <Button size="sm" variant="destructive" disabled={pending} onClick={async () => { await deleteSshKey(row.id); await load(); }}>{tP('delete')}</Button>
          </div>
        ))}
      </div>
    </PlatformPageLayout>
  );
}
