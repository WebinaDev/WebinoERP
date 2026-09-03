'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  createNotification,
  deleteNotification,
  fetchNotifications,
  testNotification,
  type PlatformNotificationChannel,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function NotificationsPage() {
  const t = useTranslations('platform.notifications');
  const tP = useTranslations('platform');
  const [rows, setRows] = useState<PlatformNotificationChannel[]>([]);
  const [name, setName] = useState('');
  const [type, setType] = useState('discord');
  const [webhookUrl, setWebhookUrl] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchNotifications());
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
      const config = type === 'telegram'
        ? { bot_token: webhookUrl.split(':')[0] ?? '', chat_id: webhookUrl.split(':')[1] ?? webhookUrl }
        : { webhook_url: webhookUrl };
      await createNotification({ name, type, config, enabled: true });
      setName('');
      setWebhookUrl('');
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
        <div className="grid gap-2">
          <Label>{t('type')}</Label>
          <select className="border rounded-md h-10 px-3 bg-background" value={type} onChange={(e) => setType(e.target.value)}>
            {['email', 'discord', 'telegram', 'slack'].map((x) => <option key={x} value={x}>{x}</option>)}
          </select>
        </div>
        <div className="grid gap-2 md:col-span-2"><Label>{t('config')}</Label><Input value={webhookUrl} onChange={(e) => setWebhookUrl(e.target.value)} placeholder={t('configPlaceholder')} dir="ltr" /></div>
        <Button disabled={pending || !name} onClick={() => void handleCreate()}>{t('add')}</Button>
      </CardContent></Card>
      <div className="grid gap-2">
        {rows.map((row) => (
          <div key={row.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm">
            <div><p className="font-medium">{row.name}</p><Badge variant="outline">{row.type}</Badge></div>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={pending} onClick={async () => { await testNotification(row.id); }}>{t('test')}</Button>
              <Button size="sm" variant="destructive" disabled={pending} onClick={async () => { await deleteNotification(row.id); await load(); }}>{tP('delete')}</Button>
            </div>
          </div>
        ))}
      </div>
    </PlatformPageLayout>
  );
}
