'use client';

import { useCallback, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboardHref } from '@/lib/route-resolver';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { useHubSummary } from './hub/hub-api';
import { HubProvider } from './hub/HubContext';
import { HubActionBar, HubAlerts, HubHeader, HubKpis } from './hub/HubHeader';
import { AssetsTab, BillingTab, IdentityTab, LicensesTab } from './hub/TabsCore';
import { AccessTab, AnalyticsTab, DocumentsTab, NotesTab, RelationsTab, SalesTab, SupportTab, TimelineTab, UsageTab } from './hub/TabsMore';
import { CustomerClassicPanel } from './CustomerClassicPanel';

type Props = { id: string };

/** Tab key → backend section that gates it (see CustomerHubAccess::VIEW). */
const TABS: { key: string; section: string; label: string; render: () => JSX.Element }[] = [
  { key: 'identity', section: 'identity', label: 'tab.identity', render: () => <IdentityTab /> },
  { key: 'assets', section: 'assets', label: 'tab.assets', render: () => <AssetsTab /> },
  { key: 'licenses', section: 'licenses', label: 'tab.licenses', render: () => <LicensesTab /> },
  { key: 'billing', section: 'billing', label: 'tab.billing', render: () => <BillingTab /> },
  { key: 'sales', section: 'sales', label: 'tab.sales', render: () => <SalesTab /> },
  { key: 'support', section: 'support', label: 'tab.support', render: () => <SupportTab /> },
  { key: 'timeline', section: 'timeline', label: 'tab.timeline', render: () => <TimelineTab /> },
  { key: 'usage', section: 'usage', label: 'tab.usage', render: () => <UsageTab /> },
  { key: 'documents', section: 'documents', label: 'tab.documents', render: () => <DocumentsTab /> },
  { key: 'access', section: 'access', label: 'tab.access', render: () => <AccessTab /> },
  { key: 'relations', section: 'relations', label: 'tab.relations', render: () => <RelationsTab /> },
  { key: 'notes', section: 'notes', label: 'tab.notes', render: () => <NotesTab /> },
  { key: 'analytics', section: 'analytics', label: 'tab.analytics', render: () => <AnalyticsTab /> },
];

export function CustomerDetailPage({ id }: Props) {
  const t = useTranslations('crm.hub');
  const tC = useTranslations('crm.customer360');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { data, error, loading, reload } = useHubSummary(id);
  const [notice, setNotice] = useState<string | null>(null);
  const [tab, setTab] = useState<string | null>(null);
  const onRefresh = useCallback(() => void reload(), [reload]);

  const visible = data ? TABS.filter((x) => data.sections.includes(x.section)) : [];
  const showClassic = data ? data.sections.includes('identity') : false;
  const current = tab ?? visible[0]?.key ?? 'classic';
  const status = (error as { response?: { status?: number } } | null)?.response?.status;

  return (
    <CrmPageLayout
      title={t('title')}
      actions={
        <Button variant="outline" size="sm" asChild>
          <Link href={dashboardHref(locale, 'crm/customers')}>{t('back')}</Link>
        </Button>
      }
      error={error && status !== 403 ? getApiErrorMessage(error) : null}
      success={notice}
      onDismissSuccess={() => setNotice(null)}
    >
      {loading && !data ? (
        <p className="text-sm text-muted-foreground">{t('loading')}</p>
      ) : status === 403 ? (
        <p className="rounded-lg border p-6 text-center text-sm text-muted-foreground">{t('forbidden')}</p>
      ) : data ? (
        <HubProvider id={id} summary={data} onRefresh={onRefresh} setNotice={setNotice}>
          <div className="space-y-4">
            <HubHeader />
            <HubAlerts />
            <HubKpis />
            <HubActionBar />
            <Tabs value={current} onValueChange={setTab}>
              <TabsList className="flex h-auto w-full flex-wrap justify-start">
                {visible.map((x) => (
                  <TabsTrigger key={x.key} value={x.key}>{t(x.label as never)}</TabsTrigger>
                ))}
                {showClassic ? <TabsTrigger value="classic">{tC('title')}</TabsTrigger> : null}
              </TabsList>
              {visible.map((x) => (
                <TabsContent key={x.key} value={x.key} className="mt-4">
                  {current === x.key ? x.render() : null}
                </TabsContent>
              ))}
              {showClassic ? (
                <TabsContent value="classic" className="mt-4">
                  {current === 'classic' ? <CustomerClassicPanel id={id} /> : null}
                </TabsContent>
              ) : null}
            </Tabs>
          </div>
        </HubProvider>
      ) : null}
    </CrmPageLayout>
  );
}
