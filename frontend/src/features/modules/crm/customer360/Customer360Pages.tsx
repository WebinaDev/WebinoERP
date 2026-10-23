'use client';

import { useState, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImportCsvDialog } from '@/features/modules/crm/ImportCsvDialog';
import { P5_DUPLICATES, P5_EXPORT, P5_PORTAL, P5_REPORTS, PORTAL_SECTIONS, REPORT_SECTIONS, type Area } from './phase5-api';

function Pending({ area, children }: { area: Area; children?: ReactNode }) {
  const t = useTranslations('crm.hub.c360');
  if (area.ready && children) return <>{children}</>;
  return (
    <div className="flex items-center gap-2 rounded-md border border-dashed p-4 text-sm text-muted-foreground" data-testid="contract-pending">
      <Clock className="h-4 w-4" />{t('pending')}
    </div>
  );
}

function SectionSwitcher<K extends string>({ title, keys, areas }: { title: string; keys: readonly K[]; areas: Record<K, Area> }) {
  const t = useTranslations('crm.hub.c360');
  const [cur, setCur] = useState<K>(keys[0]!);
  return (
    <div dir="rtl" className="space-y-4 p-4">
      <h1 className="text-xl font-bold">{title}</h1>
      <div className="flex flex-wrap gap-1" role="tablist">
        {keys.map((k) => (
          <Button key={k} role="tab" aria-selected={cur === k} size="sm" variant={cur === k ? 'default' : 'outline'} onClick={() => setCur(k)}>{t(k as never)}</Button>
        ))}
      </div>
      <Card>
        <CardHeader><CardTitle className="text-base">{t(cur as never)}</CardTitle></CardHeader>
        <CardContent><Pending area={areas[cur]} /></CardContent>
      </Card>
    </div>
  );
}

/** Customer-facing portal: profile, contracts (OTP signing), invoices (pay), services, documents, tickets. */
export function Customer360PortalPage() {
  const t = useTranslations('crm.hub.c360');
  return <SectionSwitcher title={t('portal')} keys={PORTAL_SECTIONS} areas={P5_PORTAL} />;
}

/** BI: LTV, debt, churn, cohorts, top customers. */
export function Customer360ReportsPage() {
  const t = useTranslations('crm.hub.c360');
  return <SectionSwitcher title={t('reports')} keys={REPORT_SECTIONS} areas={P5_REPORTS} />;
}

/** Duplicate detection / merge + import / export. Import reuses the existing CSV dialog. */
export function Customer360DataPage() {
  const t = useTranslations('crm.hub.c360');
  const [imp, setImp] = useState(false);
  return (
    <div dir="rtl" className="space-y-4 p-4">
      <Card>
        <CardHeader><CardTitle className="text-base">{t('duplicates')}</CardTitle></CardHeader>
        <CardContent><Pending area={P5_DUPLICATES} /></CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between"><CardTitle className="text-base">{t('exportImport')}</CardTitle>
          <Button size="sm" onClick={() => setImp(true)}>{t('import')}</Button>
        </CardHeader>
        <CardContent><Pending area={P5_EXPORT} /></CardContent>
      </Card>
      <ImportCsvDialog open={imp} onOpenChange={setImp} onImported={() => undefined} />
    </div>
  );
}
