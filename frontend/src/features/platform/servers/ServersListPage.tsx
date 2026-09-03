'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { dashboardHref } from '@/lib/route-resolver';
import {
  bootstrapServer,
  createServer,
  deleteServer,
  fetchServers,
  fetchSshKeys,
  validateServer,
  type PlatformServer,
  type PlatformSshKey,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function ServersListPage() {
  const t = useTranslations('platform.servers');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const [rows, setRows] = useState<PlatformServer[]>([]);
  const [keys, setKeys] = useState<PlatformSshKey[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState('');
  const [ip, setIp] = useState('');
  const [port, setPort] = useState('22');
  const [user, setUser] = useState('root');
  const [sshKeyId, setSshKeyId] = useState<number | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const [servers, sshKeys] = await Promise.all([fetchServers(), fetchSshKeys()]);
      setRows(servers);
      setKeys(sshKeys);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCreate() {
    setPending(true);
    setError(null);
    try {
      await createServer({
        name,
        ip,
        port: Number(port) || 22,
        user,
        ssh_key_id: sshKeyId,
      });
      setShowForm(false);
      setName('');
      setIp('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleValidate(id: number) {
    setPending(true);
    try {
      await validateServer(id);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleBootstrap(id: number) {
    setPending(true);
    try {
      await bootstrapServer(id);
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
      await deleteServer(id);
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
          <Button size="sm" onClick={() => setShowForm((v) => !v)}>{t('addServer')}</Button>
        </>
      }
    >
      {showForm ? (
        <Card>
          <CardHeader><CardTitle className="text-base">{t('addServer')}</CardTitle></CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            <div className="grid gap-2"><Label>{t('name')}</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
            <div className="grid gap-2"><Label>{t('ip')}</Label><Input value={ip} onChange={(e) => setIp(e.target.value)} dir="ltr" className="font-mono" /></div>
            <div className="grid gap-2"><Label>{t('port')}</Label><Input value={port} onChange={(e) => setPort(e.target.value)} dir="ltr" /></div>
            <div className="grid gap-2"><Label>{t('user')}</Label><Input value={user} onChange={(e) => setUser(e.target.value)} dir="ltr" /></div>
            <div className="grid gap-2 md:col-span-2">
              <Label>{t('sshKey')}</Label>
              <select className="border rounded-md h-10 px-3 bg-background" value={sshKeyId ?? ''} onChange={(e) => setSshKeyId(e.target.value ? Number(e.target.value) : null)}>
                <option value="">{tP('optional')}</option>
                {keys.map((k) => <option key={k.id} value={k.id}>{k.name}</option>)}
              </select>
            </div>
            <Button disabled={pending || !name || !ip} onClick={() => void handleCreate()}>{tP('save')}</Button>
          </CardContent>
        </Card>
      ) : null}

      <div className="grid gap-3">
        {rows.map((row) => (
          <Card key={row.id}>
            <CardHeader className="flex flex-row items-start justify-between pb-2">
              <div>
                <CardTitle className="text-base">
                  <Link href={dashboardHref(locale, `admin/platform/servers/${row.id}`)} className="hover:underline">{row.name}</Link>
                </CardTitle>
                <p className="font-mono text-xs text-muted-foreground">{row.ip}:{row.port ?? 22}</p>
              </div>
              <Badge variant="secondary">{row.status ?? '—'}</Badge>
            </CardHeader>
            <CardContent className="flex flex-wrap gap-2">
              <Button size="sm" variant="outline" disabled={pending} onClick={() => void handleValidate(row.id)}>{t('validate')}</Button>
              <Button size="sm" variant="outline" disabled={pending} onClick={() => void handleBootstrap(row.id)}>{t('bootstrap')}</Button>
              <Button size="sm" variant="destructive" disabled={pending} onClick={() => void handleDelete(row.id)}>{tP('delete')}</Button>
            </CardContent>
          </Card>
        ))}
        {!rows.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
      </div>
    </PlatformPageLayout>
  );
}
