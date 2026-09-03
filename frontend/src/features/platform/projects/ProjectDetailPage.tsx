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
  createProjectEnvironment,
  fetchProject,
  updateProject,
  type PlatformProject,
} from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

type Props = { id: string };

export function ProjectDetailPage({ id }: Props) {
  const t = useTranslations('platform.projects');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const [project, setProject] = useState<PlatformProject | null>(null);
  const [envName, setEnvName] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setProject(await fetchProject(id));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [id, tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function saveProject() {
    if (!project) return;
    setPending(true);
    try {
      setProject(await updateProject(id, { name: project.name, description: project.description }));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function addEnvironment() {
    if (!envName.trim()) return;
    setPending(true);
    try {
      await createProjectEnvironment(id, envName.trim());
      setEnvName('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout
      title={project?.name ?? t('title')}
      subtitle={t('detailSubtitle')}
      error={error}
      actions={
        <>
          <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, 'admin/platform/projects')}>{tP('back')}</Link></Button>
          <RefreshButton onClick={() => void load()} label={tP('refresh')} />
        </>
      }
    >
      {project ? (
        <div className="space-y-4">
          <Card>
            <CardContent className="grid gap-3 pt-6">
              <div className="grid gap-2"><Label>{t('name')}</Label><Input value={project.name} onChange={(e) => setProject({ ...project, name: e.target.value })} /></div>
              <div className="grid gap-2"><Label>{t('description')}</Label><Input value={project.description ?? ''} onChange={(e) => setProject({ ...project, description: e.target.value })} /></div>
              <Button disabled={pending} onClick={() => void saveProject()}>{tP('save')}</Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">{t('environments')}</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <div className="flex flex-wrap gap-2">
                {(project.environments ?? []).map((env) => (
                  <Badge key={env.id} variant="secondary">{env.name}</Badge>
                ))}
              </div>
              <div className="flex flex-wrap gap-2">
                <Input value={envName} onChange={(e) => setEnvName(e.target.value)} placeholder={t('newEnvironment')} className="max-w-xs" />
                <Button disabled={pending || !envName.trim()} onClick={() => void addEnvironment()}>{t('addEnvironment')}</Button>
              </div>
              <Button asChild variant="outline" size="sm">
                <Link href={dashboardHref(locale, `admin/platform/resources?project=${project.id}`)}>{t('viewResources')}</Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      ) : (
        <p className="text-sm text-muted-foreground">{tP('loading')}</p>
      )}
    </PlatformPageLayout>
  );
}
