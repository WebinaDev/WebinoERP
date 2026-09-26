'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { AxiosError } from 'axios';
import apiClient from '@/lib/api-client';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { RahnCalculatorTab } from './rahn_percent/rahn_calculator_tab';
import { RahnQuotesTab } from './rahn_percent/rahn_quotes_tab';
import { RahnSettingsTab } from './rahn_percent/rahn_settings_tab';
import { RahnStatementsTab } from './rahn_percent/rahn_statements_tab';
import { RAHN_API, DEFAULT_WIZARD_STEPS, type RahnSettings } from './rahn_percent/types';

function looksLikeSettings(value: unknown): value is Record<string, unknown> {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const o = value as Record<string, unknown>;
  return (
    'p_min' in o ||
    'catalog' in o ||
    'T' in o ||
    'topics' in o ||
    's_hat_default' in o
  );
}

function extractSettings(body: unknown): Record<string, unknown> | null {
  if (!body || typeof body !== 'object') return null;
  const root = body as Record<string, unknown>;

  if (looksLikeSettings(root.settings)) return root.settings as Record<string, unknown>;

  const data = root.data;
  if (data && typeof data === 'object' && !Array.isArray(data)) {
    const inner = data as Record<string, unknown>;
    if (looksLikeSettings(inner.settings)) return inner.settings as Record<string, unknown>;
    if (looksLikeSettings(inner)) return inner;
  }

  if (looksLikeSettings(root)) return root;
  return null;
}

function normalizeLoadedSettings(raw: Record<string, unknown>): RahnSettings {
  const settings = raw as unknown as RahnSettings;
  return {
    ...settings,
    topics: settings.topics ?? [],
    domains: settings.domains ?? [],
    categories: settings.categories ?? [],
    catalog: settings.catalog ?? [],
    duration_options: settings.duration_options?.length
      ? settings.duration_options
      : [6, 9, 12, 18, 24],
    wizard_steps: settings.wizard_steps?.length
      ? settings.wizard_steps
      : [...DEFAULT_WIZARD_STEPS],
    review: settings.review ?? {
      enabled: false,
      deviation_percent: 25,
      consecutive_months: 3,
    },
    sales_definition: settings.sales_definition ?? {
      G: { enabled: true, label: 'فروش ثبت‌شده سایت' },
      R: { enabled: true, label: 'لغو و مرجوعی قطعی' },
      D: { enabled: true, label: 'کارمزد درگاه / پلتفرم / تخفیف کانال' },
      X: { enabled: true, label: 'سفارش تست و فروش خارج از اسکوپ' },
    },
    T: settings.T ?? 12,
    m: settings.m ?? 0.6,
    k: settings.k ?? 0.7,
    p_min: settings.p_min ?? 0.05,
    p_max: settings.p_max ?? 0.25,
    p_default: settings.p_default ?? 0.1,
    s_hat_default: settings.s_hat_default ?? 100000000,
    clause_template: settings.clause_template ?? '',
  };
}

function apiErrorMessage(err: unknown, fallback: string): string {
  if (err instanceof AxiosError) {
    const data = err.response?.data as
      | { message?: string; data?: { message?: string } }
      | undefined;
    const msg = data?.message ?? data?.data?.message;
    if (typeof msg === 'string' && msg.trim() && /[\u0600-\u06FF]/.test(msg)) {
      return msg;
    }
  }
  return fallback;
}

export function RahnPercentPage() {
  const t = useTranslations('sales.rahn');
  const tNav = useTranslations();
  const [settings, setSettings] = useState<RahnSettings | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void apiClient
      .get(`${RAHN_API}/settings`)
      .then((res) => {
        const extracted = extractSettings(res.data);
        if (!extracted) {
          setError(t('loadError'));
          return;
        }
        setSettings(normalizeLoadedSettings(extracted));
      })
      .catch((err) => setError(apiErrorMessage(err, t('loadError'))))
      .finally(() => setLoading(false));
  }, [t]);

  if (loading) {
    return (
      <CrmPageLayout title={tNav('nav.erp.sales.rahnPercent')} description={t('description')}>
        <p className="text-sm text-muted-foreground">{tNav('common.loading')}</p>
      </CrmPageLayout>
    );
  }

  if (error || !settings) {
    return (
      <CrmPageLayout title={tNav('nav.erp.sales.rahnPercent')} description={t('description')}>
        <Alert>
          <AlertDescription>{error || t('loadError')}</AlertDescription>
        </Alert>
      </CrmPageLayout>
    );
  }

  return (
    <CrmPageLayout title={t('title')} description={t('description')}>
      <div className="space-y-4 text-start" dir="rtl">
      <Tabs defaultValue="calculator">
        <TabsList className="flex h-auto flex-wrap gap-1">
          <TabsTrigger value="calculator">{t('tabCalculator')}</TabsTrigger>
          <TabsTrigger value="statements">{t('tabStatements')}</TabsTrigger>
          <TabsTrigger value="quotes">{t('tabQuotes')}</TabsTrigger>
          <TabsTrigger value="settings">{t('tabSettings')}</TabsTrigger>
        </TabsList>
        <TabsContent value="calculator" className="mt-4">
          <RahnCalculatorTab settings={settings} />
        </TabsContent>
        <TabsContent value="statements" className="mt-4">
          <RahnStatementsTab settings={settings} />
        </TabsContent>
        <TabsContent value="quotes" className="mt-4">
          <RahnQuotesTab />
        </TabsContent>
        <TabsContent value="settings" className="mt-4">
          <RahnSettingsTab settings={settings} onSaved={setSettings} />
        </TabsContent>
      </Tabs>
      </div>
    </CrmPageLayout>
  );
}
