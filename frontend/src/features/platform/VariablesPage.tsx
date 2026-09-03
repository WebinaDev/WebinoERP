'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
  createSharedVariable,
  deleteSharedVariable,
  fetchProjects,
  fetchSharedVariables,
  type PlatformProject,
  type PlatformSharedVariable,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function VariablesPage() {
  const t = useTranslations('platform.variables');
  const tP = useTranslations('platform');
  const [rows, setRows] = useState<PlatformSharedVariable[]>([]);
  const [projects, setProjects] = useState<PlatformProject[]>([]);
  const [key, setKey] = useState('');
  const [value, setValue] = useState('');
  const [isSecret, setIsSecret] = useState(false);
  const [projectId, setProjectId] = useState<number | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const [vars, projs] = await Promise.all([fetchSharedVariables(), fetchProjects()]);
      setRows(vars);
      setProjects(projs);
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
      await createSharedVariable({ key, value, is_secret: isSecret, project_id: projectId });
      setKey('');
      setValue('');
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
        <div className="grid gap-2"><Label>{t('key')}</Label><Input value={key} onChange={(e) => setKey(e.target.value)} dir="ltr" /></div>
        <div className="grid gap-2"><Label>{t('value')}</Label><Input value={value} onChange={(e) => setValue(e.target.value)} dir="ltr" /></div>
        <div className="grid gap-2">
          <Label>{t('project')}</Label>
          <select className="border rounded-md h-10 px-3 bg-background" value={projectId ?? ''} onChange={(e) => setProjectId(e.target.value ? Number(e.target.value) : null)}>
            <option value="">{t('global')}</option>
            {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </div>
        <div className="flex items-center gap-2"><Switch checked={isSecret} onCheckedChange={setIsSecret} /><Label>{t('secret')}</Label></div>
        <Button disabled={pending || !key} onClick={() => void handleCreate()}>{t('add')}</Button>
      </CardContent></Card>
      <div className="grid gap-2">
        {rows.map((row) => (
          <div key={row.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
            <div><p className="font-mono">{row.key}</p><p className="text-muted-foreground">{row.is_secret ? '••••••' : row.value ?? '—'}</p></div>
            <Button size="sm" variant="destructive" disabled={pending} onClick={async () => { await deleteSharedVariable(row.id); await load(); }}>{tP('delete')}</Button>
          </div>
        ))}
      </div>
    </PlatformPageLayout>
  );
}
