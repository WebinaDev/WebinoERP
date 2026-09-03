'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  createBackupSchedule,
  fetchBackupSchedules,
  fetchBackups,
  fetchResources,
  fetchStorages,
  restoreBackup,
  runBackup,
  type PlatformBackup,
  type PlatformBackupSchedule,
  type PlatformResource,
  type PlatformStorage,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function BackupsPage() {
  const t = useTranslations('platform.backups');
  const tP = useTranslations('platform');
  const [backups, setBackups] = useState<PlatformBackup[]>([]);
  const [schedules, setSchedules] = useState<PlatformBackupSchedule[]>([]);
  const [resources, setResources] = useState<PlatformResource[]>([]);
  const [storages, setStorages] = useState<PlatformStorage[]>([]);
  const [resourceId, setResourceId] = useState<number | null>(null);
  const [storageId, setStorageId] = useState<number | null>(null);
  const [cron, setCron] = useState('0 3 * * *');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const [b, s, r, st] = await Promise.all([
        fetchBackups(),
        fetchBackupSchedules(),
        fetchResources(),
        fetchStorages(),
      ]);
      setBackups(b);
      setSchedules(s);
      setResources(r);
      setStorages(st);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleRun() {
    if (!resourceId) return;
    setPending(true);
    try {
      await runBackup({ resource_id: resourceId, storage_id: storageId });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleSchedule() {
    if (!resourceId) return;
    setPending(true);
    try {
      await createBackupSchedule({ resource_id: resourceId, storage_id: storageId, cron, enabled: true });
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function handleRestore(backupId: number) {
    if (!window.confirm(t('confirmRestore'))) return;
    setPending(true);
    try {
      await restoreBackup(backupId);
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout title={t('title')} subtitle={t('subtitle')} error={error} actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('runBackup')}</CardTitle></CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <div className="grid gap-2">
            <Label>{t('resource')}</Label>
            <select className="border rounded-md h-10 px-3 bg-background" value={resourceId ?? ''} onChange={(e) => setResourceId(Number(e.target.value))}>
              <option value="">{tP('select')}</option>
              {resources.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select>
          </div>
          <div className="grid gap-2">
            <Label>{t('storage')}</Label>
            <select className="border rounded-md h-10 px-3 bg-background" value={storageId ?? ''} onChange={(e) => setStorageId(e.target.value ? Number(e.target.value) : null)}>
              <option value="">{tP('optional')}</option>
              {storages.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>
          <div className="grid gap-2"><Label>{t('cron')}</Label><Input value={cron} onChange={(e) => setCron(e.target.value)} dir="ltr" className="font-mono" /></div>
          <div className="flex flex-wrap gap-2 items-end">
            <Button disabled={pending || !resourceId} onClick={() => void handleRun()}>{t('runNow')}</Button>
            <Button variant="outline" disabled={pending || !resourceId} onClick={() => void handleSchedule()}>{t('addSchedule')}</Button>
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('recentBackups')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {backups.map((b) => (
              <div key={b.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm">
                <span>#{b.id} / resource {b.resource_id}</span>
                <div className="flex items-center gap-2">
                  <span>{b.status}</span>
                  <Button size="sm" variant="outline" disabled={pending} onClick={() => void handleRestore(b.id)}>{t('restore')}</Button>
                </div>
              </div>
            ))}
            {!backups.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('schedules')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {schedules.map((s) => (
              <div key={s.id} className="rounded-md border p-3 text-sm">
                <p>resource {s.resource_id}</p>
                <p className="font-mono text-xs">{s.cron}</p>
              </div>
            ))}
            {!schedules.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
          </CardContent>
        </Card>
      </div>
    </PlatformPageLayout>
  );
}
