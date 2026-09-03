'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { execServerTerminal, fetchServers, type PlatformServer } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function TerminalPage() {
  const t = useTranslations('platform.terminal');
  const tP = useTranslations('platform');
  const [servers, setServers] = useState<PlatformServer[]>([]);
  const [serverId, setServerId] = useState<number | null>(null);
  const [cmd, setCmd] = useState('docker ps');
  const [output, setOutput] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const rows = await fetchServers();
      setServers(rows);
      if (rows[0] && !serverId) setServerId(rows[0].id);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [serverId, tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function run() {
    if (!serverId || !cmd.trim()) return;
    setPending(true);
    setError(null);
    try {
      const r = await execServerTerminal(serverId, cmd);
      setOutput(`${r.stdout ?? ''}${r.stderr ? `\n${r.stderr}` : ''}`.trim() || t('noOutput'));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout title={t('title')} subtitle={t('subtitle')} error={error} actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}>
      <Card><CardContent className="grid gap-3 pt-6">
        <div className="grid gap-2">
          <Label>{t('server')}</Label>
          <select className="border rounded-md h-10 px-3 bg-background" value={serverId ?? ''} onChange={(e) => setServerId(Number(e.target.value))}>
            {servers.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.ip})</option>)}
          </select>
        </div>
        <div className="grid gap-2">
          <Label>{t('command')}</Label>
          <Input value={cmd} onChange={(e) => setCmd(e.target.value)} dir="ltr" className="font-mono" />
        </div>
        <Button disabled={pending || !serverId} onClick={() => void run()}>{t('run')}</Button>
        <pre className="min-h-48 rounded-md border bg-muted/30 p-3 text-xs font-mono whitespace-pre-wrap">{output}</pre>
      </CardContent></Card>
    </PlatformPageLayout>
  );
}
