'use client';

import { useCallback, useEffect, useState } from 'react';
import { Download, Printer, Radio } from 'lucide-react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboardHref } from '@/lib/route-resolver';
import { getApiErrorMessage } from '@/lib/api-helpers';
import { downloadHubCsv, useHubLive, useHubSummary } from './hub/hub-api';
import { HubProvider } from './hub/HubContext';
import { HubActionBar, HubAlerts, HubHeader, HubKpis } from './hub/HubHeader';
import { AssetsTab, BillingTab, IdentityTab, LicensesTab } from './hub/TabsCore';
import { AccessTab, AnalyticsTab, NotesTab, RelationsTab, SalesTab, SitePanelTab, SupportTab, TimelineTab, UsageTab } from './hub/TabsMore';
import { CustomerClassicPanel } from './CustomerClassicPanel';
import { ContractsTab } from './hub/tabs360/ContractsTab';
import { DocumentsTab360 } from './hub/tabs360/DocumentsTab360';
import { ProjectsTab } from './hub/tabs360/ProjectsTab';
import { AccountingTab, OrdersTab, SmsTab } from './hub/tabs360/Phase3Tabs';

type Props = { id: string };

/** Print-only: show just the hub print root (browser "Save as PDF" gives the PDF export). */
const PRINT_CSS = `@media print {
  body * { visibility: hidden !important; }
  #hub-print-root, #hub-print-root * { visibility: visible !important; }
  #hub-print-root { position: absolute; inset: 0; padding: 12mm; font-family: var(--font-yekan), 'Yekan Bakh', Tahoma, sans-serif; }
  #hub-print-root button { display: none !important; }
  @page { size: A4; margin: 10mm; }
}`;

/** Tab key → backend section that gates it (see CustomerHubAccess::VIEW). */
export const TABS: { key: string; section: string; label: string; render: () => JSX.Element }[] = [
  { key: 'identity', section: 'identity', label: 'tab.identity', render: () => <IdentityTab /> },
  { key: 'assets', section: 'assets', label: 'tab.assets', render: () => <AssetsTab /> },
  { key: 'licenses', section: 'licenses', label: 'tab.licenses', render: () => <LicensesTab /> },
  { key: 'billing', section: 'billing', label: 'tab.billing', render: () => <BillingTab /> },
  { key: 'sales', section: 'sales', label: 'tab.sales', render: () => <SalesTab /> },
  { key: 'support', section: 'support', label: 'tab.support', render: () => <SupportTab /> },
  { key: 'timeline', section: 'timeline', label: 'tab.timeline', render: () => <TimelineTab /> },
  { key: 'usage', section: 'usage', label: 'tab.usage', render: () => <UsageTab /> },
  { key: 'site_panel', section: 'site_panel', label: 'tab.sitePanel', render: () => <SitePanelTab /> },
  { key: 'contracts', section: 'contracts', label: 'tab.contracts', render: () => <ContractsTab /> },
  { key: 'projects', section: 'projects', label: 'tab.projects', render: () => <ProjectsTab /> },
  { key: 'documents', section: 'documents', label: 'tab.documents', render: () => <DocumentsTab360 /> },
  { key: 'orders', section: 'orders', label: 'tab.orders', render: () => <OrdersTab /> },
  { key: 'accounting', section: 'accounting', label: 'tab.accounting', render: () => <AccountingTab /> },
  { key: 'sms', section: 'sms', label: 'tab.sms', render: () => <SmsTab /> },
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
  const [tab, setTab] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null;
    return new URLSearchParams(window.location.search).get('tab');
  });
  const onRefresh = useCallback(() => void reload(), [reload]);
  const [liveTick, setLiveTick] = useState(0);
  const [printing, setPrinting] = useState(false);
  const { connected } = useHubLive(id, () => {
    if (printing) return;
    void reload();
    setLiveTick((x) => x + 1);
  });

  useEffect(() => {
    if (!printing) return undefined;
    const done = () => setPrinting(false);
    window.addEventListener('afterprint', done);
    const timer = window.setTimeout(() => window.print(), 1200); // let sections load
    return () => {
      window.clearTimeout(timer);
      window.removeEventListener('afterprint', done);
    };
  }, [printing]);

  const visible = data ? TABS.filter((x) => data.sections.includes(x.section)) : [];
  const showClassic = data ? data.sections.includes('identity') : false;
  const current = (tab && (tab === 'classic' || visible.some((v) => v.key === tab)) ? tab : null) ?? visible[0]?.key ?? 'classic';
  const status = (error as { response?: { status?: number } } | null)?.response?.status;

  return (
    <CrmPageLayout
      title={t('title')}
      actions={
        <div className="flex flex-wrap items-center gap-2 print:hidden">
          <span className={`inline-flex items-center gap-1 text-xs ${connected ? 'text-emerald-600' : 'text-muted-foreground'}`} title={t('ex.live')} data-testid="hub-live">
            <Radio className="h-3 w-3" />{t('ex.live')}
          </span>
          {data ? (
            <>
              <Button variant="outline" size="sm" onClick={() => void downloadHubCsv(id).catch((e) => setNotice(getApiErrorMessage(e)))}>
                <Download className="me-1 h-4 w-4" />{t('ex.csv')}
              </Button>
              <Button variant="outline" size="sm" onClick={() => setPrinting(true)}><Printer className="me-1 h-4 w-4" />{t('ex.print')}</Button>
            </>
          ) : null}
          <Button variant="outline" size="sm" asChild>
            <Link href={dashboardHref(locale, 'crm/customers')}>{t('back')}</Link>
          </Button>
        </div>
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
        <HubProvider id={id} summary={data} onRefresh={onRefresh} setNotice={setNotice} externalVersion={liveTick}>
          {printing ? (
            <div id="hub-print-root" className="space-y-4" dir="rtl">
              <style>{PRINT_CSS}</style>
              <p className="text-xs text-muted-foreground">{t('ex.printedAt')}: {new Date().toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US')}</p>
              <HubHeader />
              <HubKpis />
              {visible.map((x) => (
                <section key={x.key} className="break-inside-avoid space-y-2">
                  <h3 className="border-b pb-1 text-base font-semibold">{t(x.label as never)}</h3>
                  {x.render()}
                </section>
              ))}
            </div>
          ) : null}
          <div className={printing ? 'hidden' : 'space-y-4'}>
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
