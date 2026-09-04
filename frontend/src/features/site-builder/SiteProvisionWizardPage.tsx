'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import {
  createProvision,
  fetchCatalog,
  fetchPackages,
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
import { normalizeListPayload } from '@/lib/list-utils';
import { WizardFooter, WizardShell } from './wizard/WizardShell';
import { StepCustomer, type CrmAccount } from './wizard/StepCustomer';
import { StepCategory } from './wizard/StepCategory';
import { StepType } from './wizard/StepType';
import { StepPackage } from './wizard/StepPackage';
import { StepSiteInfo } from './wizard/StepSiteInfo';
import { StepDomain } from './wizard/StepDomain';
import { StepLicense } from './wizard/StepLicense';
import { LaunchControlPanel } from './wizard/LaunchControlPanel';

const DEFAULT_BASE_DOMAIN = 'webinaagency.ir';

function slugify(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/[\s_]+/g, '-')
    .replace(/[^a-z0-9-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

function pickDefaultServer(servers: PlatformServer[]): number | null {
  const localhost = servers.find(
    (s) => s.is_localhost || s.ip === '127.0.0.1' || /localhost/i.test(s.name),
  );
  if (localhost?.id) return localhost.id;
  const ready = servers.find((s) => (s.status ?? '').toLowerCase() === 'ready');
  if (ready?.id) return ready.id;
  return servers[0]?.id ?? null;
}

export function SiteProvisionWizardPage() {
  const t = useTranslations('siteBuilder');
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
          apiClient.get('/v1/crm/accounts', { params: { per_page: 100 } }),
          fetchServers().catch(() => [] as PlatformServer[]),
        ]);
        setCategories(cat);
        setServers(platformServers);
        setServerId(pickDefaultServer(platformServers));
        const accData = unwrapData<unknown>(accRes);
        const rows = normalizeListPayload(accData);
        setAccounts(
          rows
            .map((r) => ({
              id: Number(r.id),
              name: String(r.name ?? ''),
              company_name: typeof r.company_name === 'string' ? r.company_name : undefined,
            }))
            .filter((r) => Number.isFinite(r.id) && r.id > 0),
        );

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
    if (!typeId) {
      setPackages([]);
      return;
    }
    const nested = selectedType?.packages ?? [];
    if (nested.length) setPackages(nested);
    void fetchPackages(typeId)
      .then(setPackages)
      .catch(() => {
        if (nested.length) setPackages(nested);
      });
  }, [typeId, selectedType]);

  useEffect(() => {
    if (selectedType?.slug) {
      setSiteTypeSlug(selectedType.slug);
    }
  }, [selectedType?.slug]);

  const persistWizard = useCallback(async () => {
    const autoSlug = slugify(slug) || slugify(siteName);
    const payload: Record<string, unknown> = {
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
        site_type_slug: selectedType?.slug || siteTypeSlug,
        server_id: serverId,
      },
      uses_custom_domain: usesCustomDomain,
    };
    if (crmAccountId) payload.crm_account_id = crmAccountId;
    if (packageId) payload.package_id = packageId;
    if (autoSlug) payload.slug = autoSlug;
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
    selectedType?.slug,
    serverId,
    provision?.id,
  ]);

  async function nextStep() {
    setError(null);
    if (step === 0 && !crmAccountId) {
      setError(t('customerRequired'));
      return;
    }
    if (step === 1 && !categoryId) return;
    if (step === 2 && !typeId) return;
    if (step === 3 && !packageId) return;
    if (step === 4 && !siteName.trim()) return;

    if (step < 3) {
      setStep((s) => s + 1);
      return;
    }

    if (step === 6) {
      if (!provision?.license?.license_key) {
        setError(t('licensePending'));
        return;
      }
      setStep(7);
      return;
    }

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

  function backStep() {
    setError(null);
    setStep((s) => Math.max(0, s - 1));
  }

  async function createCustomerQuick() {
    const name = newCustomerName.trim();
    if (!name) return;
    setPending(true);
    setError(null);
    try {
      const res = await apiClient.post('/v1/crm/accounts', { name, type: 'customer' });
      const created = unwrapData<CrmAccount>(res);
      if (!created?.id) throw new Error(t('saveError'));
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

  const stepSubtitles = [
    t('stepCustomerSubtitle'),
    t('stepCategorySubtitle'),
    t('stepTypeSubtitle'),
    t('stepPackageSubtitle'),
    t('stepSiteInfoSubtitle'),
    t('stepDomainSubtitle'),
    t('stepLicenseSubtitle'),
    t('stepLaunchSubtitle'),
  ];

  const canContinue =
    (step === 0 && !!crmAccountId) ||
    (step === 1 && !!categoryId) ||
    (step === 2 && !!typeId) ||
    (step === 3 && !!packageId) ||
    (step === 4 && !!siteName.trim()) ||
    step === 5 ||
    (step === 6 && !!provision?.license?.license_key);

  return (
    <WizardShell
      step={step}
      steps={steps}
      title={t('wizardTitle')}
      subtitle={stepSubtitles[step]}
      error={error}
      footer={
        step < 7 ? (
          <WizardFooter>
            <Button type="button" variant="outline" onClick={backStep} disabled={step === 0 || pending}>
              {t('back')}
            </Button>
            <Button
              disabled={pending || !canContinue}
              onClick={() => void nextStep()}
              data-testid="wizard-continue"
            >
              {t('continue')}
            </Button>
          </WizardFooter>
        ) : null
      }
    >
      {step === 0 ? (
        <StepCustomer
          accounts={accounts}
          crmAccountId={crmAccountId}
          onSelect={setCrmAccountId}
          newCustomerName={newCustomerName}
          onNewName={setNewCustomerName}
          onCreate={() => void createCustomerQuick()}
          pending={pending}
        />
      ) : null}

      {step === 1 ? (
        <StepCategory
          categories={categories}
          categoryId={categoryId}
          onSelect={(id) => {
            setCategoryId(id);
            setTypeId(null);
          }}
        />
      ) : null}

      {step === 2 ? (
        <StepType
          types={selectedCategory?.types ?? []}
          typeId={typeId}
          onSelect={(type) => {
            setTypeId(type.id);
            if (type.slug) setSiteTypeSlug(type.slug);
          }}
        />
      ) : null}

      {step === 3 ? (
        <StepPackage
          packages={packages}
          packageId={packageId}
          selectedType={selectedType}
          selectedFeatures={selectedFeatures}
          onSelectPackage={(p) => {
            setPackageId(p.id);
            const fromType = (selectedType?.features ?? [])
              .filter((f) => !f.is_addon)
              .map((f) => f.slug);
            if (fromType.length) setSelectedFeatures(fromType);
          }}
          onToggleFeature={(slugVal, on) => {
            setSelectedFeatures((prev) =>
              on ? [...prev, slugVal] : prev.filter((s) => s !== slugVal),
            );
          }}
        />
      ) : null}

      {step === 4 ? (
        <StepSiteInfo
          siteName={siteName}
          slug={slug}
          adminName={adminName}
          adminEmail={adminEmail}
          currency={currency}
          siteTypeSlug={siteTypeSlug}
          serverId={serverId}
          servers={servers}
          previewDomain={finalDomain}
          onChange={(patch) => {
            if (patch.slug !== undefined) setSlug(patch.slug);
            if (patch.adminName !== undefined) setAdminName(patch.adminName);
            if (patch.adminEmail !== undefined) setAdminEmail(patch.adminEmail);
            if (patch.currency !== undefined) setCurrency(patch.currency);
            if (patch.siteTypeSlug !== undefined) setSiteTypeSlug(patch.siteTypeSlug);
            if (patch.serverId !== undefined) setServerId(patch.serverId);
          }}
          onSiteName={(next) => {
            setSiteName(next);
            if (!slug || slug === slugify(siteName)) {
              const auto = slugify(next);
              if (auto) setSlug(auto);
            }
          }}
        />
      ) : null}

      {step === 5 ? (
        <StepDomain
          usesCustomDomain={usesCustomDomain}
          customDomain={customDomain}
          slug={slug}
          baseDomain={baseDomain}
          finalDomain={finalDomain}
          onToggleCustom={setUsesCustomDomain}
          onCustomDomain={setCustomDomain}
        />
      ) : null}

      {step === 6 ? (
        <StepLicense
          provision={provision}
          pending={pending}
          onPrepare={() => void prepareLicense()}
        />
      ) : null}

      {step === 7 ? (
        <LaunchControlPanel
          provision={provision}
          finalDomain={finalDomain}
          packageSku={selectedPackage?.sku}
          usesCustomDomain={usesCustomDomain}
          onProvision={setProvision}
          onError={setError}
        />
      ) : null}
    </WizardShell>
  );
}
