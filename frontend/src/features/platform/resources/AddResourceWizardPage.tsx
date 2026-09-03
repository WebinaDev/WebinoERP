'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboardHref } from '@/lib/route-resolver';
import {
  createResource,
  fetchProjects,
  fetchServers,
  fetchServiceTemplates,
  type PlatformEnvironment,
  type PlatformProject,
  type PlatformServer,
  type PlatformServiceTemplate,
} from '@/lib/api/platform';
import { PlatformPageLayout } from '@/features/platform/PlatformPageLayout';

const WIZARD_TYPES = [
  'public_git',
  'private_git',
  'dockerfile',
  'compose',
  'image',
  'database',
  'service',
  'webino_dashboard',
] as const;

type WizardType = (typeof WIZARD_TYPES)[number];

function buildPayload(
  type: WizardType,
  base: Record<string, unknown>,
  extras: Record<string, unknown>,
): Record<string, unknown> {
  const common = { ...base, name: extras.name, fqdn: extras.fqdn || undefined };
  switch (type) {
    case 'public_git':
    case 'private_git':
      return { ...common, type: 'application', build_pack: 'nixpacks', git_repository: extras.git_repository, git_branch: extras.git_branch || 'main' };
    case 'dockerfile':
      return { ...common, type: 'application', build_pack: 'dockerfile', git_repository: extras.git_repository, git_branch: extras.git_branch || 'main', dockerfile_location: extras.dockerfile_location || 'Dockerfile' };
    case 'compose':
      return { ...common, type: 'application', build_pack: 'compose', docker_compose_location: extras.docker_compose_location || 'docker-compose.yml', docker_compose_raw: extras.docker_compose_raw || undefined };
    case 'image':
      return { ...common, type: 'application', build_pack: 'image', docker_image: extras.docker_image };
    case 'database':
      return { ...common, type: 'database', database_type: extras.database_type || 'postgresql' };
    case 'service':
      return { ...common, type: 'service', service_template: extras.service_template };
    case 'webino_dashboard':
      return { ...common, type: 'webino_dashboard', site_type_slug: extras.site_type_slug, crm_account_id: extras.crm_account_id || undefined };
    default:
      return common;
  }
}

export function AddResourceWizardPage() {
  const t = useTranslations('platform.resources');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const router = useRouter();
  const searchParams = useSearchParams();
  const templateFromQuery = searchParams.get('template');
  const [step, setStep] = useState(0);
  const [wizardType, setWizardType] = useState<WizardType>(templateFromQuery ? 'service' : 'public_git');
  const [projects, setProjects] = useState<PlatformProject[]>([]);
  const [servers, setServers] = useState<PlatformServer[]>([]);
  const [templates, setTemplates] = useState<PlatformServiceTemplate[]>([]);
  const [projectId, setProjectId] = useState<number | null>(null);
  const [environmentId, setEnvironmentId] = useState<number | null>(null);
  const [serverId, setServerId] = useState<number | null>(null);
  const [name, setName] = useState('');
  const [fqdn, setFqdn] = useState('');
  const [gitRepository, setGitRepository] = useState('');
  const [gitBranch, setGitBranch] = useState('main');
  const [dockerfileLocation, setDockerfileLocation] = useState('Dockerfile');
  const [serviceTemplate, setServiceTemplate] = useState(templateFromQuery ?? '');
  const [dockerComposeLocation, setDockerComposeLocation] = useState('docker-compose.yml');
  const [dockerComposeRaw, setDockerComposeRaw] = useState('');
  const [dockerImage, setDockerImage] = useState('');
  const [databaseType, setDatabaseType] = useState('postgresql');
  const [siteTypeSlug, setSiteTypeSlug] = useState('corporate');
  const [crmAccountId, setCrmAccountId] = useState('');
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const environments: PlatformEnvironment[] = useMemo(() => {
    const p = projects.find((x) => x.id === projectId);
    return p?.environments ?? [];
  }, [projects, projectId]);

  const load = useCallback(async () => {
    try {
      const [p, s, tpl] = await Promise.all([fetchProjects(), fetchServers(), fetchServiceTemplates()]);
      setProjects(p);
      setServers(s);
      setTemplates(tpl);
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (environments.length && !environmentId) {
      setEnvironmentId(environments[0]!.id);
    }
  }, [environments, environmentId]);

  async function finish() {
    if (!environmentId || !serverId || !name) return;
    setPending(true);
    setError(null);
    try {
      const payload = buildPayload(
        wizardType,
        { environment_id: environmentId, server_id: serverId },
        {
          name,
          fqdn,
          git_repository: gitRepository,
          git_branch: gitBranch,
          dockerfile_location: dockerfileLocation,
          docker_compose_location: dockerComposeLocation,
          docker_compose_raw: dockerComposeRaw,
          docker_image: dockerImage,
          database_type: databaseType,
          service_template: serviceTemplate,
          site_type_slug: siteTypeSlug,
          crm_account_id: crmAccountId ? Number(crmAccountId) : undefined,
        },
      );
      const row = await createResource(payload);
      router.push(dashboardHref(locale, `admin/platform/resources/${row.id}`));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout title={t('wizardTitle')} subtitle={t('wizardSubtitle')} error={error}>
      <ol className="flex flex-wrap gap-2 text-xs text-muted-foreground mb-2">
        {[t('stepType'), t('stepTarget'), t('stepConfig'), t('stepReview')].map((label, i) => (
          <li key={label} className={i === step ? 'text-foreground font-medium' : ''}>{i + 1}. {label}</li>
        ))}
      </ol>

      {step === 0 ? (
        <Card><CardContent className="grid gap-3 pt-6 md:grid-cols-2">
          {WIZARD_TYPES.map((wt) => (
            <Button key={wt} type="button" variant={wizardType === wt ? 'default' : 'outline'} onClick={() => setWizardType(wt)}>
              {t(`types.${wt}`)}
            </Button>
          ))}
          <Button className="md:col-span-2" onClick={() => setStep(1)}>{tP('continue')}</Button>
        </CardContent></Card>
      ) : null}

      {step === 1 ? (
        <Card><CardContent className="grid gap-3 pt-6">
          <div className="grid gap-2">
            <Label>{t('project')}</Label>
            <select className="border rounded-md h-10 px-3 bg-background" value={projectId ?? ''} onChange={(e) => { setProjectId(Number(e.target.value)); setEnvironmentId(null); }}>
              <option value="">{tP('select')}</option>
              {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
          <div className="grid gap-2">
            <Label>{t('environment')}</Label>
            <select className="border rounded-md h-10 px-3 bg-background" value={environmentId ?? ''} onChange={(e) => setEnvironmentId(Number(e.target.value))}>
              {environments.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
            </select>
          </div>
          <div className="grid gap-2">
            <Label>{t('server')}</Label>
            <select className="border rounded-md h-10 px-3 bg-background" value={serverId ?? ''} onChange={(e) => setServerId(Number(e.target.value))}>
              <option value="">{tP('select')}</option>
              {servers.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.ip})</option>)}
            </select>
          </div>
          <Button disabled={!projectId || !environmentId || !serverId} onClick={() => setStep(2)}>{tP('continue')}</Button>
        </CardContent></Card>
      ) : null}

      {step === 2 ? (
        <Card><CardContent className="grid gap-3 pt-6">
          <div className="grid gap-2"><Label>{t('name')}</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
          <div className="grid gap-2"><Label>{t('fqdn')}</Label><Input value={fqdn} onChange={(e) => setFqdn(e.target.value)} dir="ltr" className="font-mono" /></div>
          {(wizardType === 'public_git' || wizardType === 'private_git' || wizardType === 'dockerfile') ? (
            <>
              <div className="grid gap-2"><Label>{t('gitRepository')}</Label><Input value={gitRepository} onChange={(e) => setGitRepository(e.target.value)} dir="ltr" className="font-mono" /></div>
              <div className="grid gap-2"><Label>{t('gitBranch')}</Label><Input value={gitBranch} onChange={(e) => setGitBranch(e.target.value)} dir="ltr" /></div>
            </>
          ) : null}
          {wizardType === 'dockerfile' ? (
            <div className="grid gap-2"><Label>{t('dockerfileLocation')}</Label><Input value={dockerfileLocation} onChange={(e) => setDockerfileLocation(e.target.value)} dir="ltr" /></div>
          ) : null}
          {wizardType === 'compose' ? (
            <>
              <div className="grid gap-2"><Label>{t('composeLocation')}</Label><Input value={dockerComposeLocation} onChange={(e) => setDockerComposeLocation(e.target.value)} dir="ltr" /></div>
              <div className="grid gap-2"><Label>{t('composeRaw')}</Label><textarea className="min-h-24 w-full rounded-md border bg-background p-2 font-mono text-xs" value={dockerComposeRaw} onChange={(e) => setDockerComposeRaw(e.target.value)} /></div>
            </>
          ) : null}
          {wizardType === 'image' ? (
            <div className="grid gap-2"><Label>{t('dockerImage')}</Label><Input value={dockerImage} onChange={(e) => setDockerImage(e.target.value)} dir="ltr" className="font-mono" /></div>
          ) : null}
          {wizardType === 'database' ? (
            <div className="grid gap-2">
              <Label>{t('databaseType')}</Label>
              <select className="border rounded-md h-10 px-3 bg-background" value={databaseType} onChange={(e) => setDatabaseType(e.target.value)}>
                {['postgresql', 'mysql', 'mariadb', 'mongodb', 'redis', 'keydb', 'dragonfly', 'clickhouse'].map((db) => (
                  <option key={db} value={db}>{db}</option>
                ))}
              </select>
            </div>
          ) : null}
          {wizardType === 'service' ? (
            <div className="grid gap-2">
              <Label>{t('serviceTemplate')}</Label>
              <select className="border rounded-md h-10 px-3 bg-background" value={serviceTemplate} onChange={(e) => setServiceTemplate(e.target.value)}>
                <option value="">{tP('select')}</option>
                {templates.map((tpl) => <option key={tpl.slug} value={tpl.slug}>{tpl.name}</option>)}
              </select>
            </div>
          ) : null}
          {wizardType === 'webino_dashboard' ? (
            <>
              <div className="grid gap-2">
                <Label>{t('siteTypeSlug')}</Label>
                <select className="border rounded-md h-10 px-3 bg-background" value={siteTypeSlug} onChange={(e) => setSiteTypeSlug(e.target.value)}>
                  {['ecommerce', 'magazine', 'cafe', 'resume', 'corporate'].map((st) => (
                    <option key={st} value={st}>{st}</option>
                  ))}
                </select>
              </div>
              <div className="grid gap-2"><Label>{t('crmAccountId')}</Label><Input value={crmAccountId} onChange={(e) => setCrmAccountId(e.target.value)} dir="ltr" /></div>
            </>
          ) : null}
          <Button disabled={!name} onClick={() => setStep(3)}>{tP('continue')}</Button>
        </CardContent></Card>
      ) : null}

      {step === 3 ? (
        <Card><CardContent className="grid gap-3 pt-6 text-sm">
          <p>{t('reviewType')}: {t(`types.${wizardType}`)}</p>
          <p>{t('name')}: {name}</p>
          {fqdn ? <p className="font-mono">{fqdn}</p> : null}
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setStep(2)}>{tP('back')}</Button>
            <Button disabled={pending} onClick={() => void finish()}>{t('create')}</Button>
          </div>
        </CardContent></Card>
      ) : null}
    </PlatformPageLayout>
  );
}
