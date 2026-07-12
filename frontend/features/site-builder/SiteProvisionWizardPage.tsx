'use client';

import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { dashboardHref } from '@/lib/dashboard-href';
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
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

type CrmAccount = { id: number; name?: string; company_name?: string };

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
  const [siteName, setSiteName] = useState('');
  const [slug, setSlug] = useState('');
  const [currency, setCurrency] = useState('IRR');
  const [usesCustomDomain, setUsesCustomDomain] = useState(false);
  const [customDomain, setCustomDomain] = useState('');
  const [selectedFeatures, setSelectedFeatures] = useState<string[]>([]);
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

  useEffect(() => {
    void (async () => {
      try {
        const [cat, accRes] = await Promise.all([
          fetchCatalog(),
          apiClient.get('/crm/accounts', { params: { per_page: 50 } }),
        ]);
        setCategories(cat);
        const accData = unwrapData<{ data?: CrmAccount[] } | CrmAccount[]>(accRes);
        setAccounts(Array.isArray(accData) ? accData : (accData.data ?? []));
      } catch (e) {
        setError(e instanceof Error ? e.message : t('loadError'));
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
        uses_custom_domain: usesCustomDomain,
        custom_domain: customDomain || undefined,
        selected_feature_slugs: selectedFeatures,
        business_category_id: categoryId,
        business_type_id: typeId,
      },
      uses_custom_domain: usesCustomDomain,
    };
    if (provision?.id) {
      return updateProvision(provision.id, payload);
    }
    return createProvision(payload);
  }, [
    crmAccountId, packageId, slug, siteName, currency, usesCustomDomain, customDomain,
    selectedFeatures, categoryId, typeId, provision?.id,
  ]);

  async function nextStep() {
    setError(null);
    setPending(true);
    try {
      const row = await persistWizard();
      setProvision(row);
      setStep((s) => s + 1);
    } catch (e) {
      setError(e instanceof Error ? e.message : t('saveError'));
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
      setStep(7);
      const poll = async () => {
        const st = await pollProvisionStatus(provision.id);
        setProvision(st);
        if (!['ready', 'failed'].includes(st.status)) {
          setTimeout(() => void poll(), 4000);
        }
      };
      void poll();
    } catch (e) {
      setError(e instanceof Error ? e.message : t('launchError'));
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
              className="border rounded-md h-10 px-3 bg-background"
              value={crmAccountId ?? ''}
              onChange={(e) => setCrmAccountId(e.target.value ? Number(e.target.value) : null)}
            >
              <option value="">{t('optional')}</option>
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>{a.company_name || a.name || `#${a.id}`}</option>
              ))}
            </select>
            <Button disabled={pending} onClick={() => void nextStep()}>{t('continue')}</Button>
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
                onClick={() => { setCategoryId(c.id); setTypeId(null); }}
              >
                {c.name_fa}
              </Button>
            ))}
            <Button disabled={!categoryId || pending} onClick={() => void nextStep()}>{t('continue')}</Button>
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
                onClick={() => setTypeId(type.id)}
              >
                {type.name_fa}
              </Button>
            ))}
            <Button disabled={!typeId || pending} onClick={() => void nextStep()}>{t('continue')}</Button>
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
            {(selectedType?.features ?? []).filter((f) => f.is_addon).map((f) => (
              <div key={f.id} className="flex items-center justify-between border rounded p-3">
                <span>{f.name_fa}</span>
                <Switch
                  checked={selectedFeatures.includes(f.slug)}
                  onCheckedChange={(on) => {
                    setSelectedFeatures((prev) => on ? [...prev, f.slug] : prev.filter((s) => s !== f.slug));
                  }}
                />
              </div>
            ))}
            <Button disabled={!packageId || pending} onClick={() => void nextStep()}>{t('continue')}</Button>
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
              <Label>{t('currency')}</Label>
              <Input value={currency} onChange={(e) => setCurrency(e.target.value)} dir="ltr" />
            </div>
            <Button disabled={!siteName || pending} onClick={() => void nextStep()}>{t('continue')}</Button>
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
              <Input value={customDomain} onChange={(e) => setCustomDomain(e.target.value)} dir="ltr" className="font-mono" />
            ) : (
              <p className="text-muted-foreground text-sm">{t('subdomainHint', { slug: slug || 'my-shop' })}</p>
            )}
            <Button disabled={pending} onClick={() => void nextStep()}>{t('continue')}</Button>
          </CardContent>
        </Card>
      ) : null}

      {step === 6 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6 text-sm">
            <p>{t('licenseHint')}</p>
            <Button
              disabled={pending || !provision?.id}
              onClick={async () => {
                if (!provision?.id) return;
                setPending(true);
                try {
                  const row = await prepareProvisionLicense(provision.id);
                  setProvision(row);
                  setStep(7);
                } catch (e) {
                  setError(e instanceof Error ? e.message : t('saveError'));
                } finally {
                  setPending(false);
                }
              }}
            >
              {t('continue')}
            </Button>
            {provision?.license?.license_key ? (
              <p className="font-mono text-xs">{provision.license.license_key}</p>
            ) : (
              <p className="text-muted-foreground">{t('licensePending')}</p>
            )}
          </CardContent>
        </Card>
      ) : null}

      {step === 7 ? (
        <Card>
          <CardContent className="grid gap-3 pt-6">
            <p className="text-sm">{t('reviewDomain')}: <span className="font-mono">{provision?.domain}</span></p>
            {provision?.license?.license_key ? (
              <p className="font-mono text-xs">{provision.license.license_key}</p>
            ) : null}
            <p className="text-sm">{selectedPackage?.sku}</p>
            {usesCustomDomain ? (
              <p className="text-muted-foreground text-xs">{t('dnsCustomHint')}</p>
            ) : null}
            <p className="text-sm">{t('status')}: {provision?.status}</p>
            {provision?.status === 'ready' ? (
              <Button onClick={() => router.push(dashboardHref(locale, 'admin/site-builder/provisions'))}>
                {t('done')}
              </Button>
            ) : (
              <Button disabled={pending} onClick={() => void launch()}>{t('launch')}</Button>
            )}
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
