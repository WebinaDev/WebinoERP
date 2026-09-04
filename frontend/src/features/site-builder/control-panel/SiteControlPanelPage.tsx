'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState, type ComponentType, type ReactNode } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import {
  ArrowLeft,
  ClipboardCopy,
  Database,
  ExternalLink,
  FlaskConical,
  Globe2,
  ImageIcon,
  KeyRound,
  Layers,
  Loader2,
  PackagePlus,
  Power,
  PowerOff,
  RefreshCw,
  ScrollText,
  Server,
  ShieldCheck,
  UserRound,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { dashboardHref } from '@/lib/route-resolver';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  fetchFeatures,
  fetchProvisionControl,
  fetchProvisionLogs,
  queueProvisionUpdate,
  renewProvisionSsl,
  setProvisionChannel,
  startProvision,
  stopProvision,
  updateProvision,
  updateProvisionAdmin,
  updateProvisionModules,
  type DashboardFeature,
  type SiteControlPayload,
} from '@/lib/api/site-builder';

function Section({
  icon: Icon,
  title,
  description,
  children,
  testId,
}: {
  icon: ComponentType<{ className?: string }>;
  title: string;
  description?: string;
  children: ReactNode;
  testId?: string;
}) {
  return (
    <Card className="border-border/70 overflow-hidden" data-testid={testId}>
      <CardHeader className="border-b bg-muted/30 pb-4">
        <div className="flex items-start gap-3">
          <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
            <Icon className="size-5" />
          </div>
          <div>
            <CardTitle className="text-lg">{title}</CardTitle>
            {description ? <CardDescription className="mt-1">{description}</CardDescription> : null}
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-4 pt-5">{children}</CardContent>
    </Card>
  );
}

export function SiteControlPanelPage({ id }: { id: string }) {
  const t = useTranslations('siteBuilder');
  const locale = useLocale();
  const provisionId = Number(id);
  const [data, setData] = useState<SiteControlPayload | null>(null);
  const [features, setFeatures] = useState<DashboardFeature[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [msg, setMsg] = useState<string | null>(null);

  const [adminName, setAdminName] = useState('');
  const [adminEmail, setAdminEmail] = useState('');
  const [adminPassword, setAdminPassword] = useState('');
  const [domain, setDomain] = useState('');
  const [logoUrl, setLogoUrl] = useState('');
  const [siteName, setSiteName] = useState('');
  const [expiresAt, setExpiresAt] = useState('');
  const [startDate, setStartDate] = useState('');
  const [installSlug, setInstallSlug] = useState('');
  const [composeLogs, setComposeLogs] = useState('');
  const [logsBusy, setLogsBusy] = useState(false);

  const load = useCallback(async () => {
    if (!Number.isFinite(provisionId)) return;
    setError(null);
    try {
      const [ctrl, feats] = await Promise.all([
        fetchProvisionControl(provisionId),
        fetchFeatures().catch(() => [] as DashboardFeature[]),
      ]);
      setData(ctrl);
      setFeatures(feats);
      setAdminName(ctrl.admin?.name ?? '');
      setAdminEmail(ctrl.admin?.email ?? '');
      setDomain(ctrl.provision?.domain ?? '');
      setLogoUrl(ctrl.license?.logo_url ?? String(ctrl.provision?.wizard_payload?.logo_url ?? ''));
      setSiteName(String(ctrl.provision?.wizard_payload?.site_name ?? ctrl.license?.project_name ?? ''));
      setExpiresAt(ctrl.license?.expires_at ? ctrl.license.expires_at.slice(0, 10) : '');
      setStartDate(ctrl.license?.start_date ?? '');
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    }
  }, [provisionId, t]);

  const loadLogs = useCallback(async () => {
    if (!Number.isFinite(provisionId)) return;
    setLogsBusy(true);
    try {
      const raw = await fetchProvisionLogs(provisionId, 400);
      const text =
        typeof raw === 'string'
          ? raw
          : raw && typeof raw === 'object' && 'logs' in raw
            ? String(raw.logs ?? '')
            : '';
      setComposeLogs(text);
    } catch (e) {
      setComposeLogs(getAxiosMessage(e) || t('loadError'));
    } finally {
      setLogsBusy(false);
    }
  }, [provisionId, t]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (data) void loadLogs();
  }, [data?.provision?.id, loadLogs]);

  useEffect(() => {
    if (!data?.update || !['queued', 'running'].includes(data.update.status ?? '')) return;
    const timer = setInterval(() => void load(), 4000);
    return () => clearInterval(timer);
  }, [data?.update?.status, load]);

  async function run(key: string, fn: () => Promise<unknown>) {
    setBusy(key);
    setError(null);
    setMsg(null);
    try {
      await fn();
      setMsg(t('controlSaved'));
      await load();
    } catch (e) {
      setError(getAxiosMessage(e) || t('saveError'));
    } finally {
      setBusy(null);
    }
  }

  if (!Number.isFinite(provisionId)) {
    return <p className="text-destructive text-sm">{t('loadError')}</p>;
  }

  const modules = data?.license?.modules ?? [];
  const updateStatus = data?.update?.status;

  return (
    <div className="space-y-6" data-testid="site-control-panel">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="space-y-2">
          <Button asChild variant="ghost" size="sm" className="-ms-2 gap-1">
            <Link href={dashboardHref(locale, 'admin/platform/sites')}>
              <ArrowLeft className="size-4" />
              {t('controlBack')}
            </Link>
          </Button>
          <h1 className="text-3xl font-semibold tracking-tight">
            {data?.provision.domain ?? t('controlTitle')}
          </h1>
          <p className="text-muted-foreground text-sm">{t('controlSubtitle')}</p>
          <div className="flex flex-wrap gap-2">
            {data ? (
              <Badge variant="outline" className="capitalize">
                {data.provision.status}
              </Badge>
            ) : null}
            {data ? (
              <Badge variant="secondary" className="capitalize">
                {data.channel}
              </Badge>
            ) : null}
            {data?.license ? (
              <Badge variant={data.license.is_expired ? 'destructive' : 'outline'} className="gap-1">
                <ShieldCheck className="size-3" />
                {data.license.is_expired
                  ? t('controlLicenseExpired')
                  : t('controlLicenseUntil', {
                      date: data.license.expires_at
                        ? new Date(data.license.expires_at).toLocaleDateString()
                        : '—',
                    })}
              </Badge>
            ) : null}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => void load()} className="gap-1.5">
            <RefreshCw className="size-3.5" />
            {t('refresh')}
          </Button>
          {data?.provision.domain ? (
            <Button asChild size="sm" variant="outline" className="gap-1.5">
              <a href={`https://${data.provision.domain}`} target="_blank" rel="noopener noreferrer">
                <ExternalLink className="size-3.5" />
                {t('openSite')}
              </a>
            </Button>
          ) : null}
        </div>
      </div>

      {error ? <p className="text-destructive text-sm">{error}</p> : null}
      {msg ? <p className="text-sm text-emerald-600">{msg}</p> : null}
      {!data ? (
        <div className="text-muted-foreground flex items-center gap-2 text-sm">
          <Loader2 className="size-4 animate-spin" />
          {t('loading')}
        </div>
      ) : (
        <div className="grid gap-4 xl:grid-cols-2">
          <Section icon={Power} title={t('controlPower')} description={t('controlPowerHint')} testId="control-power">
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                className="gap-1.5"
                disabled={busy !== null}
                onClick={() => void run('start', () => startProvision(provisionId))}
                data-testid="control-start"
              >
                <Power className="size-4" />
                {t('start')}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="gap-1.5"
                disabled={busy !== null}
                onClick={() => void run('stop', () => stopProvision(provisionId))}
                data-testid="control-stop"
              >
                <PowerOff className="size-4" />
                {t('stop')}
              </Button>
            </div>
          </Section>

          <Section
            icon={UserRound}
            title={t('controlIdentity')}
            description={t('controlIdentityHint')}
            testId="control-identity"
          >
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>{t('adminName')}</Label>
                <Input value={adminName} onChange={(e) => setAdminName(e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('adminEmail')}</Label>
                <Input type="email" value={adminEmail} onChange={(e) => setAdminEmail(e.target.value)} />
              </div>
              <div className="space-y-1.5 sm:col-span-2">
                <Label>{t('controlPassword')}</Label>
                <Input
                  type="password"
                  value={adminPassword}
                  onChange={(e) => setAdminPassword(e.target.value)}
                  placeholder="••••••••"
                />
              </div>
            </div>
            <Button
              type="button"
              disabled={busy !== null}
              onClick={() =>
                void run('admin', () =>
                  updateProvisionAdmin(provisionId, {
                    name: adminName || undefined,
                    email: adminEmail || undefined,
                    password: adminPassword || undefined,
                  }).then(() => setAdminPassword('')),
                )
              }
            >
              {t('controlSaveIdentity')}
            </Button>
          </Section>

          <Section icon={Globe2} title={t('controlDomain')} description={t('controlDomainHint')}>
            <div className="space-y-1.5">
              <Label>{t('customDomain')}</Label>
              <Input value={domain} onChange={(e) => setDomain(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('siteName')}</Label>
              <Input value={siteName} onChange={(e) => setSiteName(e.target.value)} />
            </div>
            <Button
              type="button"
              disabled={busy !== null}
              onClick={() =>
                void run('domain', () =>
                  updateProvision(provisionId, { domain, site_name: siteName || undefined }),
                )
              }
            >
              {t('controlSaveDomain')}
            </Button>
          </Section>

          <Section icon={ImageIcon} title={t('controlLogo')} description={t('controlLogoHint')}>
            <div className="space-y-1.5">
              <Label>{t('controlLogoUrl')}</Label>
              <Input value={logoUrl} onChange={(e) => setLogoUrl(e.target.value)} />
            </div>
            {logoUrl ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={logoUrl} alt="" className="bg-muted h-16 w-auto rounded-md border object-contain p-1" />
            ) : null}
            <Button
              type="button"
              disabled={busy !== null}
              onClick={() => void run('logo', () => updateProvision(provisionId, { logo_url: logoUrl }))}
            >
              {t('controlSaveLogo')}
            </Button>
          </Section>

          <Section
            icon={KeyRound}
            title={t('controlLicense')}
            description={t('controlLicenseHint')}
            testId="control-license"
          >
            {data.license ? (
              <div className="grid gap-2 text-sm" data-testid="control-license-dates">
                <div className="font-mono text-xs">{data.license.license_key}</div>
                <div>
                  {t('controlLicenseStatus')}: <strong>{data.license.status ?? '—'}</strong>
                </div>
                <div>
                  {t('controlLicenseActivated')}:{' '}
                  {data.license.start_date ||
                    (data.license.created_at
                      ? new Date(data.license.created_at).toLocaleDateString()
                      : '—')}
                </div>
                <div>
                  {t('controlLicenseExpires')}:{' '}
                  {data.license.expires_at
                    ? new Date(data.license.expires_at).toLocaleDateString()
                    : t('controlLicenseOpen')}
                </div>
                {data.license.days_remaining != null ? (
                  <div>
                    {t('controlLicenseDaysLeft')}: {data.license.days_remaining}
                  </div>
                ) : null}
              </div>
            ) : (
              <p className="text-muted-foreground text-sm">{t('controlNoLicense')}</p>
            )}
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>{t('controlLicenseStart')}</Label>
                <Input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('controlLicenseExpires')}</Label>
                <Input type="date" value={expiresAt} onChange={(e) => setExpiresAt(e.target.value)} />
              </div>
            </div>
            <Button
              type="button"
              disabled={busy !== null || !data.license}
              onClick={() =>
                void run('license', () =>
                  updateProvision(provisionId, {
                    license: {
                      start_date: startDate || undefined,
                      expires_at: expiresAt || undefined,
                    },
                  }),
                )
              }
            >
              {t('controlSaveLicense')}
            </Button>
          </Section>

          <Section
            icon={Layers}
            title={t('controlModules')}
            description={t('controlModulesHint')}
            testId="control-modules"
          >
            <div className="space-y-3">
              {(features.length
                ? features.map((f) => f.module_slug || f.slug)
                : modules
              )
                .filter((v, i, a) => a.indexOf(v) === i && v)
                .map((slug) => {
                  const on = modules.includes(slug);
                  return (
                    <div key={slug} className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2">
                      <span className="font-mono text-sm">{slug}</span>
                      <Switch
                        checked={on}
                        disabled={busy !== null}
                        onCheckedChange={(checked) =>
                          void run(`mod-${slug}`, () =>
                            updateProvisionModules(provisionId, {
                              enable: checked ? [slug] : undefined,
                              disable: checked ? undefined : [slug],
                            }),
                          )
                        }
                      />
                    </div>
                  );
                })}
            </div>
            <div className="flex flex-wrap items-end gap-2">
              <div className="min-w-[12rem] flex-1 space-y-1.5">
                <Label>{t('controlInstallModule')}</Label>
                <Input
                  value={installSlug}
                  onChange={(e) => setInstallSlug(e.target.value)}
                  placeholder="commerce"
                />
              </div>
              <Button
                type="button"
                variant="secondary"
                className="gap-1.5"
                disabled={busy !== null || !installSlug.trim()}
                onClick={() =>
                  void run('install', () =>
                    updateProvisionModules(provisionId, {
                      install: installSlug.trim(),
                      enable: [installSlug.trim()],
                    }),
                  )
                }
              >
                <PackagePlus className="size-4" />
                {t('controlInstall')}
              </Button>
            </div>
          </Section>

          <Section
            icon={FlaskConical}
            title={t('controlUpdates')}
            description={t('controlUpdatesHint')}
            testId="control-updates"
          >
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="secondary"
                disabled={busy !== null}
                onClick={() => void run('channel-beta', () => setProvisionChannel(provisionId, 'beta'))}
                data-testid="control-channel-beta"
              >
                {t('controlSwitchBeta')}
              </Button>
              <Button type="button" variant="outline" disabled title={t('controlStableSoon')}>
                {t('controlSwitchStable')}
              </Button>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              <Button
                type="button"
                variant="outline"
                className="justify-start gap-2"
                disabled={busy !== null}
                data-testid="control-update-frontend"
                onClick={() => void run('upd-fe', () => queueProvisionUpdate(provisionId, 'frontend'))}
              >
                <Globe2 className="size-4" />
                {t('controlUpdateFrontend')}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="justify-start gap-2"
                disabled={busy !== null}
                data-testid="control-update-backend"
                onClick={() => void run('upd-be', () => queueProvisionUpdate(provisionId, 'backend'))}
              >
                <Server className="size-4" />
                {t('controlUpdateBackend')}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="justify-start gap-2"
                disabled={busy !== null}
                data-testid="control-update-migrate"
                onClick={() => void run('upd-mig', () => queueProvisionUpdate(provisionId, 'migrate'))}
              >
                <Database className="size-4" />
                {t('controlUpdateMigrate')}
              </Button>
              <Button
                type="button"
                className="justify-start gap-2"
                disabled={busy !== null}
                data-testid="control-update-full"
                onClick={() => void run('upd-full', () => queueProvisionUpdate(provisionId, 'full'))}
              >
                <RefreshCw className="size-4" />
                {t('controlUpdateFull')}
              </Button>
            </div>
            <p className="text-muted-foreground text-xs">{t('controlSslSafe')}</p>
            {updateStatus ? (
              <div className="bg-muted/50 rounded-lg border p-3 text-xs" data-testid="control-update-status">
                <div className="mb-1 font-medium capitalize">
                  {data.update?.target} · {updateStatus}
                </div>
                {data.update?.log || data.update?.error ? (
                  <pre className="max-h-40 overflow-auto whitespace-pre-wrap">
                    {data.update.error || data.update.log}
                  </pre>
                ) : null}
              </div>
            ) : null}
          </Section>

          <Section
            icon={ShieldCheck}
            title={t('controlSsl')}
            description={t('controlSslHint')}
            testId="control-ssl"
          >
            <div className="grid gap-2 text-sm">
              <div>
                {t('controlSslStatus')}:{' '}
                <strong className="capitalize">{data.ssl?.ssl_status || t('controlSslUnknown')}</strong>
              </div>
              <div>
                {t('controlSslExpires')}:{' '}
                {data.ssl?.expires_at
                  ? new Date(data.ssl.expires_at).toLocaleString()
                  : t('controlSslUnknown')}
              </div>
            </div>
            <p className="text-muted-foreground text-xs">{t('controlSslDnsHint')}</p>
            {data.ssl?.log ? (
              <pre className="bg-muted/50 max-h-40 overflow-auto whitespace-pre-wrap rounded-lg border p-3 text-xs">
                {data.ssl.log}
              </pre>
            ) : null}
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="secondary"
                className="gap-1.5"
                disabled={busy !== null}
                data-testid="control-ssl-renew"
                onClick={() => void run('ssl', () => renewProvisionSsl(provisionId, false))}
              >
                <ShieldCheck className="size-4" />
                {t('controlSslRenew')}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="gap-1.5"
                disabled={busy !== null}
                data-testid="control-ssl-force"
                onClick={() => void run('ssl-force', () => renewProvisionSsl(provisionId, true))}
              >
                <RefreshCw className="size-4" />
                {t('controlSslForce')}
              </Button>
            </div>
          </Section>

          <Section
            icon={ScrollText}
            title={t('controlDiagnostics')}
            description={t('controlDiagnosticsHint')}
            testId="control-diagnostics"
          >
            <div className="grid gap-2 text-sm" data-testid="control-stack-health">
              <div className="font-medium">{t('controlStackHealth')}</div>
              <div>
                {t('controlDbAuth')}:{' '}
                <Badge variant={data?.stack?.db_auth_ok ? 'default' : 'destructive'}>
                  {data?.stack?.db_auth_ok ? t('controlOk') : t('controlFail')}
                </Badge>
              </div>
              <div>
                {t('controlCaddyToBackend')}:{' '}
                <Badge variant={data?.stack?.caddy_to_backend ? 'default' : 'destructive'}>
                  {data?.stack?.caddy_to_backend ? t('controlOk') : t('controlFail')}
                </Badge>
              </div>
              <div>
                {t('controlFrontendToBackend')}:{' '}
                <Badge variant={data?.stack?.frontend_to_backend ? 'default' : 'destructive'}>
                  {data?.stack?.frontend_to_backend ? t('controlOk') : t('controlFail')}
                </Badge>
              </div>
              <div>
                {t('controlOnProxyNet')}: backend=
                {data?.stack?.on_webino_sites?.backend ? t('controlOk') : t('controlFail')}, frontend=
                {data?.stack?.on_webino_sites?.frontend ? t('controlOk') : t('controlFail')}
              </div>
            </div>
            {data?.stack?.log ? (
              <pre className="bg-muted/50 max-h-40 overflow-auto whitespace-pre-wrap rounded-lg border p-3 text-xs">
                {data.stack.log}
              </pre>
            ) : null}
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="secondary"
                className="gap-1.5"
                disabled={logsBusy || busy !== null}
                data-testid="control-refresh-logs"
                onClick={() => void loadLogs()}
              >
                {logsBusy ? <Loader2 className="size-4 animate-spin" /> : <RefreshCw className="size-4" />}
                {t('controlRefreshLogs')}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="gap-1.5"
                disabled={!composeLogs && !data?.stack?.log}
                data-testid="control-copy-logs"
                onClick={async () => {
                  const text = [data?.stack?.log, composeLogs].filter(Boolean).join('\n\n--- compose ---\n\n');
                  try {
                    await navigator.clipboard.writeText(text);
                    setMsg(t('controlLogsCopied'));
                  } catch {
                    setError(t('saveError'));
                  }
                }}
              >
                <ClipboardCopy className="size-4" />
                {t('controlCopyLogs')}
              </Button>
            </div>
            <pre
              className="bg-muted/50 max-h-72 overflow-auto whitespace-pre-wrap rounded-lg border p-3 text-xs"
              data-testid="control-compose-logs"
            >
              {composeLogs || t('controlLogsEmpty')}
            </pre>
          </Section>

          {data.customer ? (
            <Section icon={UserRound} title={t('controlCustomer')}>
              <p className="text-sm">
                {data.customer.name}
                {data.customer.email ? ` · ${data.customer.email}` : ''}
              </p>
            </Section>
          ) : null}
        </div>
      )}
    </div>
  );
}
