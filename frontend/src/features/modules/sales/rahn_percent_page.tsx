'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { RahnCalculatorTab } from './rahn_percent/rahn_calculator_tab';
import { RahnQuotesTab } from './rahn_percent/rahn_quotes_tab';
import { RahnSettingsTab } from './rahn_percent/rahn_settings_tab';
import { RahnStatementsTab } from './rahn_percent/rahn_statements_tab';
import { RAHN_API, type RahnSettings } from './rahn_percent/types';

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
        const body = res.data as any;
        const settings = body?.settings ?? body?.data?.settings ?? null;
        if (!settings) {
          setError(t('loadError'));
          return;
        }
        setSettings({
          ...settings,
          topics: settings.topics ?? [],
          domains: settings.domains ?? [],
          categories: settings.categories ?? [],
          duration_options: settings.duration_options?.length ? settings.duration_options : [6, 9, 12, 18, 24],
        });
      })
      .catch(() => setError(t('loadError')))
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
