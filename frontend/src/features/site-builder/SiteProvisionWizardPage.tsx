'use client';

import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { dashboardHref } from '@/lib/route-resolver';
import {
  createProvision,
  fetchCatalog,
  fetchPackages,
  launchProvision,
  pollProvisionStatus,
  prepareProvisionLicense,
  updateProvision,
  type BusinessCategory,
  type BusinessType,
  type PackageRow,
  type SiteProvision,
} from '@/lib/api/site-builder';
import { fetchServers, type PlatformServer } from '@/lib/api/platform';
import apiClient from '@/lib/api-client';
import { getAxiosMessage, unwrapData } from '@/lib/api-helpers';

type CrmAccount = { id: number; name?: string; company_name?: string };

const DEFAULT_BASE_DOMAIN = 'webinaagency.ir';

function pickDefaultServer(servers: PlatformServer[]): number | null {
  const localhost = servers.find((s) => s.is_localhost || s.ip === '127.0.0.1' || /localhost/i.test(s.name));
  if (localhost?.id) return localhost.id;
  const ready = servers.find((s) => (s.status ?? '').toLowerCase() === 'ready');
  if (ready?.id) return ready.id;
  return servers[0]?.id ?? null;
}

export function SiteProvisionWizardPage() {
  const t = useTranslations('siteBuilder');
  const locale = useLocale();
  const router = useRouter();
  const [step, setStep] = useState(0);
  const [categories, setCategories] = useState<BusinessCategory[]>([]);
  const [packages, setPackages] = useState<PackageRow[]>([]);
  const [accounts, setAccounts] = useState<CrmAccount[]>([]);
  const [provision, setProvision] = useState<SiteProvision | null>(null);
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [typeId, setTypeId] = useState<number | null>(null);
  const [packageId, setPackageId] = useState<number | null>(null);
  const [crmAccountId, setCrmAccountId] = useState<number | null>(null);
  const [newCustomerName, setNewCustomerName] = useState('');
  const [siteName, setSiteName] = useState('');
  const [slug, setSlug] = useState('');
  const [currency, setCurrency] = useState('IRR');
  const [adminEmail, setAdminEmail] = useState('');
  const [adminName, setAdminName] = useState('');
  const [usesCustomDomain, setUsesCustomDomain] = useState(false);
  const [customDomain, setCustomDomain] = useState('');
  const [selectedFeatures, setSelectedFeatures] = useState<string[]>([]);
  const [siteTypeSlug, setSiteTypeSlug] = useState('corporate');
  const [serverId, setServerId] = useState<number | null>(null);
  const [servers, setServers] = useState<PlatformServer[]>([]);
  const [baseDomain, setBaseDomain] = useState(DEFAULT_BASE_DOMAIN);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const selectedCategory = useMemo(
    () => categories.find((c) => c.id === categoryId) ?? null,
    [categories, categoryId],
  );
  const selectedType: BusinessType | null = useMemo(() => {
    const types = selectedCategory?.types ?? [];
    return types.find((x) => x.id === typeId) ?? null;
  }, [selectedCategory, typeId]);
  const selectedPackage = packages.find((p) => p.id === packageId) ?? null;

  const finalDomain = useMemo(() => {
    if (usesCustomDomain && customDomain.trim()) return customDomain.trim();
    const s = (slug || 'my-shop').replace(/[^a-z0-9-]/gi, '').toLowerCase() || 'my-shop';
    return `${s}.${baseDomain}`;
  }, [usesCustomDomain, customDomain, slug, baseDomain]);

  useEffect(() => {
    void (async () => {
      try {
        const [cat, accRes, platformServers] = await Promise.all([
          fetchCatalog(),
          apiClient.get('/v1/crm/accounts', { params: { per_page: 50 } }),
          fetchServers().catch(() => [] as PlatformServer[]),
        ]);
        setCategories(cat);
        setServers(platformServers);
        setServerId(pickDefaultServer(platformServers));
        const accData = unwrapData<{ data?: CrmAccount[] } | CrmAccount[]>(accRes);
        setAccounts(Array.isArray(accData) ? accData : (accData.data ?? []));

        try {
          const settingsRes = await apiClient.get('webinocrm/v1/hosting/settings');
          const settings = unwrapData<{ platform_base_domain?: string }>(settingsRes);
          if (settings?.platform_base_domain) {
            setBaseDomain(settings.platform_base_domain);
          }
        } catch {
          /* keep default */
        }
      } catch (e) {
        setError(getAxiosMessage(e) || t('loadError'));
      }
    })();
  }, [t]);

  useEffect(() => {
    if (!typeId) return;
    void fetchPackages(typeId).then(setPackages).catch(() => {});
  }, [typeId]);

  const persistWizard = useCallback(async () => {
    const payload = {
      crm_account_id: crmAccountId,
      package_id: packageId,
      slug: slug || undefined,
      wizard_payload: {
        site_name: siteName,
        currency,
        admin_email: adminEmail || undefined,
        admin_name: adminName || undefined,
        uses_custom_domain: usesCustomDomain,
        custom_domain: customDomain || undefined,
        selected_feature_slugs: selectedFeatures,
        business_category_id: categoryId,
        business_type_id: typeId,
        site_type_slug: siteTypeSlug,
        server_id: serverId,
      },
      uses_custom_domain: usesCustomDomain,
    };
    if (provision?.id) {
      return updateProvision(provision.id, payload);
    }
    return createProvision(payload);
  }, [
    crmAccountId,
    packageId,
    slug,
    siteName,
    currency,
    adminEmail,
    adminName,
    usesCustomDomain,
    customDomain,
    selectedFeatures,
    categoryId,
    typeId,
    siteTypeSlug,
    serverId,
    provision?.id,
  ]);

  async function nextStep() {
    setError(null);
    setPending(true);
    try {
      const row = await persistWizard();
      setProvision(row);
      setStep((s) => s + 1);
    } catch (e) {
      setError(getAxiosMessage(e) || t('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function createCustomerQuick() {
    const name = newCustomerName.trim();
    if (!name) return;
    setPending(true);
    setError(null);
    try {
      const res = await apiClient.post('/v1/crm/accounts', { name });
      const created = unwrapData<CrmAccount>(res);
      setAccounts((prev) => [created, ...prev]);
      setCrmAccountId(created.id);
      setNewCustomerName('');
    } catch (e) {
      setError(getAxiosMessage(e) || t('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function prepareLicense() {
    if (!provision?.id) return;
    setPending(true);
    setError(null);
    try {
      const row = await persistWizard();
      setProvision(row);
      const licensed = await prepareProvisionLicense(row.id);
      setProvision(licensed);
    } catch (e) {
      setError(getAxiosMessage(e) || t('saveError'));
    } finally {
      setPending(false);
    }
  }

  async function launch() {
    if (!provision?.id) return;
    setPending(true);
    setError(null);
    try {
      await launchProvision(provision.id);
      const poll = async () => {
        try {
          const st = await pollProvisionStatus(provision.id);
          setProvision(st);
          if (!['ready', 'failed'].includes(st.status)) {
            setTimeout(() => void poll(), 4000);
          }
        } catch (e) {
          setError(getAxiosMessage(e) || t('launchError'));
        }
      };
      void poll();
    } catch (e) {
      setError(getAxiosMessage(e) || t('launchError'));
    } finally {
      setPending(false);
    }
  }

  const steps = [
    t('stepCustomer'),
    t('stepCategory'),
    t('stepType'),
    t('stepPackage'),
    t('stepSiteInfo'),
    t('stepDomain'),
    t('stepLicense'),
    t('stepLaunch'),
  ];

  return (
    <div className="mx-auto max-w-2xl space-y-4 p-4">
      <h1 className="text-2xl font-semibold">{t('wizardTitle')}</h1>
      <ol className="flex flex-wrap gap-2 text-xs text-muted-foreground">
        {steps.map((label, i) => (
          <li key={label} className={i === step ? 'text-foreground font-medium' : ''}>
            {i + 1}. {label}
          </li>
        ))}
      </ol>
      {error ? <p className="text-destructive text-sm">{error}</p> : null}

      {step === 0 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6">
            <Label>{t('selectCustomer')}</Label>
            <select
              className="bg-background h-10 rounded-md border px-3"
              value={crmAccountId ?? ''}
              onChange={(e) => setCrmAccountId(e.target.value ? Number(e.target.value) : null)}
            >
              <option value="">{t('customerRequired')}</option>
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.company_name || a.name || `#${a.id}`}
                </option>
              ))}
            </select>
            <div className="grid gap-2">
              <Label>{t('createCustomer')}</Label>
              <div className="flex gap-2">
                <Input
                  value={newCustomerName}
                  onChange={(e) => setNewCustomerName(e.target.value)}
                  placeholder={t('createCustomer')}
                />
                <Button
                  type="button"
                  variant="outline"
                  disabled={pending || !newCustomerName.trim()}
                  onClick={() => void createCustomerQuick()}
                >
                  {t('save')}
                </Button>
              </div>
            </div>
            <Button disabled={pending || !crmAccountId} onClick={() => void nextStep()}>
              {t('continue')}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {step === 1 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6 md:grid-cols-2">
            {categories.map((c) => (
              <Button
                key={c.id}
                type="button"
                variant={categoryId === c.id ? 'default' : 'outline'}
                onClick={() => {
                  setCategoryId(c.id);
                  setTypeId(null);
                }}
              >
                {c.name_fa}
              </Button>
            ))}
            <Button disabled={!categoryId || pending} onClick={() => void nextStep()}>
              {t('continue')}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {step === 2 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6 md:grid-cols-2">
            {(selectedCategory?.types ?? []).map((type) => (
              <Button
                key={type.id}
                type="button"
                variant={typeId === type.id ? 'default' : 'outline'}
                onClick={() => {
                  setTypeId(type.id);
                  if (type.slug) setSiteTypeSlug(type.slug);
                }}
              >
                {type.name_fa}
              </Button>
            ))}
            <Button disabled={!typeId || pending} onClick={() => void nextStep()}>
              {t('continue')}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {step === 3 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6">
            {packages.map((p) => (
              <Button
                key={p.id}
                type="button"
                variant={packageId === p.id ? 'default' : 'outline'}
                className="justify-start"
                onClick={() => setPackageId(p.id)}
              >
                {p.name_fa} ({p.sku})
              </Button>
            ))}
            {(selectedType?.features ?? [])
              .filter((f) => f.is_addon)
              .map((f) => (
                <div key={f.id} className="flex items-center justify-between rounded border p-3">
                  <span>{f.name_fa}</span>
                  <Switch
                    checked={selectedFeatures.includes(f.slug)}
                    onCheckedChange={(on) => {
                      setSelectedFeatures((prev) =>
                        on ? [...prev, f.slug] : prev.filter((s) => s !== f.slug),
                      );
                    }}
                  />
                </div>
              ))}
            <Button disabled={!packageId || pending} onClick={() => void nextStep()}>
              {t('continue')}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {step === 4 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6">
            <div className="grid gap-2">
              <Label>{t('siteName')}</Label>
              <Input value={siteName} onChange={(e) => setSiteName(e.target.value)} />
            </div>
            <div className="grid gap-2">
              <Label>{t('slug')}</Label>
              <Input value={slug} onChange={(e) => setSlug(e.target.value)} dir="ltr" className="font-mono" />
            </div>
            <div className="grid gap-2">
              <Label>{t('adminName')}</Label>
              <Input value={adminName} onChange={(e) => setAdminName(e.target.value)} />
            </div>
            <div className="grid gap-2">
              <Label>{t('adminEmail')}</Label>
              <Input
                type="email"
                value={adminEmail}
                onChange={(e) => setAdminEmail(e.target.value)}
                dir="ltr"
              />
            </div>
            <div className="grid gap-2">
              <Label>{t('currency')}</Label>
              <Input value={currency} onChange={(e) => setCurrency(e.target.value)} dir="ltr" />
            </div>
            <div className="grid gap-2">
              <Label>{t('siteTypeSlug')}</Label>
              <select
                className="bg-background h-10 rounded-md border px-3"
                value={siteTypeSlug}
                onChange={(e) => setSiteTypeSlug(e.target.value)}
              >
                {['ecommerce', 'magazine', 'cafe', 'resume', 'corporate'].map((st) => (
                  <option key={st} value={st}>
                    {st}
                  </option>
                ))}
                {siteTypeSlug &&
                !['ecommerce', 'magazine', 'cafe', 'resume', 'corporate'].includes(siteTypeSlug) ? (
                  <option value={siteTypeSlug}>{siteTypeSlug}</option>
                ) : null}
              </select>
            </div>
            <div className="grid gap-2">
              <Label>
                {t('platformServer')} ({t('optional')})
              </Label>
              <select
                className="bg-background h-10 rounded-md border px-3"
                value={serverId ?? ''}
                onChange={(e) => setServerId(e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">{t('localServerHint')}</option>
                {servers.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name} ({s.ip})
                  </option>
                ))}
              </select>
              <p className="text-muted-foreground text-xs">{t('localServerHint')}</p>
            </div>
            <Button disabled={!siteName || pending} onClick={() => void nextStep()}>
              {t('continue')}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {step === 5 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6">
            <div className="flex items-center justify-between">
              <Label>{t('customDomain')}</Label>
              <Switch checked={usesCustomDomain} onCheckedChange={setUsesCustomDomain} />
            </div>
            {usesCustomDomain ? (
              <Input
                value={customDomain}
                onChange={(e) => setCustomDomain(e.target.value)}
                dir="ltr"
                className="font-mono"
              />
            ) : (
              <p className="text-muted-foreground text-sm">
                {t('subdomainHint', { slug: slug || 'my-shop', domain: baseDomain })}
              </p>
            )}
            <p className="text-sm font-medium">
              {t('finalDomain')}: <span className="font-mono" dir="ltr">{finalDomain}</span>
            </p>
            <Button disabled={pending} onClick={() => void nextStep()}>
              {t('continue')}
            </Button>
          </CardContent>
        </Card>
      ) : null}

      {step === 6 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6 text-sm">
            <p>{t('licenseHint')}</p>
            {!provision?.license?.license_key ? (
              <Button disabled={pending || !provision?.id} onClick={() => void prepareLicense()}>
                {t('continue')}
              </Button>
            ) : (
              <>
                <p className="font-mono text-xs break-all">{provision.license.license_key}</p>
                <Button
                  disabled={pending}
                  onClick={() => {
                    setStep(7);
                  }}
                >
                  {t('continue')}
                </Button>
              </>
            )}
            {!provision?.license?.license_key ? (
              <p className="text-muted-foreground">{t('licensePending')}</p>
            ) : null}
          </CardContent>
        </Card>
      ) : null}

      {step === 7 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6">
            <p className="text-sm">
              {t('reviewDomain')}:{' '}
              <span className="font-mono">{provision?.domain || finalDomain}</span>
            </p>
            {provision?.license?.license_key ? (
              <p className="font-mono text-xs break-all">{provision.license.license_key}</p>
            ) : null}
            <p className="text-sm">{selectedPackage?.sku}</p>
            {usesCustomDomain ? (
              <p className="text-muted-foreground text-xs">{t('dnsCustomHint')}</p>
            ) : null}
            <p className="text-sm">
              {t('status')}: {provision?.status}
            </p>
            {provision?.status === 'failed' && provision.error_log ? (
              <pre className="bg-muted max-h-40 overflow-auto rounded p-2 text-xs whitespace-pre-wrap">
                {provision.error_log.slice(0, 800)}
              </pre>
            ) : null}
            {provision?.status === 'ready' ? (
              <div className="flex flex-wrap gap-2">
                {provision.domain ? (
                  <Button asChild>
                    <a href={`https://${provision.domain}`} target="_blank" rel="noopener noreferrer">
                      {t('openSite')}
                    </a>
                  </Button>
                ) : null}
                <Button
                  variant="outline"
                  onClick={() => router.push(dashboardHref(locale, 'admin/platform/sites'))}
                >
                  {t('done')}
                </Button>
              </div>
            ) : (
              <Button disabled={pending} onClick={() => void launch()}>
                {t('launch')}
              </Button>
            )}
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
