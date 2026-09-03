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
import { createProject, deleteProject, fetchProjects, type PlatformProject } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function ProjectsListPage() {
  const t = useTranslations('platform.projects');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const [rows, setRows] = useState<PlatformProject[]>([]);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchProjects());
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
      await createProject({ name, description: description || undefined });
      setName('');
      setDescription('');
      setShowForm(false);
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
      await deleteProject(id);
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
          <Button size="sm" onClick={() => setShowForm((v) => !v)}>{t('addProject')}</Button>
        </>
      }
    >
      {showForm ? (
        <Card>
          <CardContent className="grid gap-3 pt-6">
            <div className="grid gap-2"><Label>{t('name')}</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
            <div className="grid gap-2"><Label>{t('description')}</Label><Input value={description} onChange={(e) => setDescription(e.target.value)} /></div>
            <Button disabled={pending || !name} onClick={() => void handleCreate()}>{tP('save')}</Button>
          </CardContent>
        </Card>
      ) : null}

      <div className="grid gap-3">
        {rows.map((row) => (
          <Card key={row.id}>
            <CardHeader className="flex flex-row items-start justify-between pb-2">
              <CardTitle className="text-base">
                <Link href={dashboardHref(locale, `admin/platform/projects/${row.id}`)} className="hover:underline">{row.name}</Link>
              </CardTitle>
              <div className="flex gap-1">
                {(row.environments ?? []).map((e) => (
                  <Badge key={e.id} variant="outline">{e.name}</Badge>
                ))}
              </div>
            </CardHeader>
            <CardContent className="flex gap-2">
              <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, `admin/platform/projects/${row.id}`)}>{t('manage')}</Link></Button>
              <Button size="sm" variant="destructive" disabled={pending} onClick={() => void handleDelete(row.id)}>{tP('delete')}</Button>
            </CardContent>
          </Card>
        ))}
        {!rows.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
      </div>
    </PlatformPageLayout>
  );
}
