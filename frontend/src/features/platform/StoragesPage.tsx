'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { createStorage, deleteStorage, fetchStorages, type PlatformStorage } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function StoragesPage() {
  const t = useTranslations('platform.storages');
  const tP = useTranslations('platform');
  const [rows, setRows] = useState<PlatformStorage[]>([]);
  const [name, setName] = useState('');
  const [endpoint, setEndpoint] = useState('');
  const [bucket, setBucket] = useState('');
  const [region, setRegion] = useState('');
  const [accessKey, setAccessKey] = useState('');
  const [secretKey, setSecretKey] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchStorages());
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
      await createStorage({ name, driver: 's3', endpoint, bucket, region, access_key: accessKey, secret_key: secretKey });
      setName('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout title={t('title')} subtitle={t('subtitle')} error={error} actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}>
      <Card><CardContent className="grid gap-3 pt-6 md:grid-cols-2">
        <div className="grid gap-2"><Label>{t('name')}</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
        <div className="grid gap-2"><Label>{t('endpoint')}</Label><Input value={endpoint} onChange={(e) => setEndpoint(e.target.value)} dir="ltr" /></div>
        <div className="grid gap-2"><Label>{t('bucket')}</Label><Input value={bucket} onChange={(e) => setBucket(e.target.value)} dir="ltr" /></div>
        <div className="grid gap-2"><Label>{t('region')}</Label><Input value={region} onChange={(e) => setRegion(e.target.value)} dir="ltr" /></div>
        <div className="grid gap-2"><Label>{t('accessKey')}</Label><Input value={accessKey} onChange={(e) => setAccessKey(e.target.value)} dir="ltr" /></div>
        <div className="grid gap-2"><Label>{t('secretKey')}</Label><Input type="password" value={secretKey} onChange={(e) => setSecretKey(e.target.value)} dir="ltr" /></div>
        <Button disabled={pending || !name} onClick={() => void handleCreate()}>{t('add')}</Button>
      </CardContent></Card>
      <div className="grid gap-2">
        {rows.map((row) => (
          <div key={row.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
            <div><p className="font-medium">{row.name}</p><p className="font-mono text-xs text-muted-foreground">{row.bucket ?? row.endpoint ?? '—'}</p></div>
            <Button size="sm" variant="destructive" disabled={pending} onClick={async () => { await deleteStorage(row.id); await load(); }}>{tP('delete')}</Button>
          </div>
        ))}
      </div>
    </PlatformPageLayout>
  );
}
