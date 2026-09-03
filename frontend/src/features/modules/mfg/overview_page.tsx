'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PermissionGate } from '@/components/auth/PermissionGate';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { mfgOverview } from '@/lib/api/mfg';

export function MfgOverviewPage() {
  const t = useTranslations('mfg');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [stats, setStats] = useState<Record<string, unknown> | null>(null);

  const load = useCallback(async () => {
    try {
      setStats(await mfgOverview());
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const boms = (stats?.boms ?? {}) as Record<string, number>;
  const wo = (stats?.work_orders ?? {}) as Record<string, number>;
  const insp = (stats?.inspections ?? {}) as Record<string, number>;

  return (
    <CrmPageLayout title={tNav('nav.erp.mfg.overview')} {...layoutProps}>
      <PermissionGate permission="mfg.boms.view">
        <p className="mb-4 text-sm text-muted-foreground">{t('overviewHint')}</p>
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader><CardTitle className="text-base">{t('bomsTitle')}</CardTitle></CardHeader>
            <CardContent className="text-sm">
              <p>{t('total')}: {boms.total ?? 0}</p>
              <p>{t('active')}: {boms.active ?? 0}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t('workOrdersTitle')}</CardTitle></CardHeader>
            <CardContent className="space-y-1 text-sm">
              {Object.keys(wo).length === 0 ? <p>—</p> : Object.entries(wo).map(([k, v]) => (
                <p key={k}>{k}: {v}</p>
              ))}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t('qualityTitle')}</CardTitle></CardHeader>
            <CardContent className="text-sm">
              <p>{t('openInspections')}: {insp.open ?? 0}</p>
              <p>{t('failedInspections')}: {insp.failed ?? 0}</p>
            </CardContent>
          </Card>
        </div>
      </PermissionGate>
    </CrmPageLayout>
  );
}
