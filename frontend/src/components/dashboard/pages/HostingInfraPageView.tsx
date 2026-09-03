'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { HostingInfrastructureTab } from '@/components/dashboard/hosting-infrastructure-tab';
import { usePermissions } from '@/features/shared/hooks/usePermissions';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

type AuditRow = {
  id: number;
  channel: string;
  action: string;
  payload?: Record<string, unknown> | null;
  created_at: string;
};

export function HostingInfraPageView() {
  const t = useTranslations('hosting');
  const { hasRole, loading: permLoading } = usePermissions();
  const [audits, setAudits] = useState<AuditRow[]>([]);
  const [auditErr, setAuditErr] = useState<string | null>(null);

  const loadAudits = useCallback(async () => {
    setAuditErr(null);
    try {
      const res = await apiClient.get('webinocrm/v1/hosting/audit-logs', { params: { limit: 40 } });
      setAudits((unwrapData<AuditRow[]>(res) as AuditRow[]) ?? []);
    } catch (e) {
      setAuditErr(getAxiosMessage(e));
    }
  }, []);

  useEffect(() => {
    if (hasRole('system_manager')) {
      void loadAudits();
    }
  }, [hasRole, loadAudits]);

  if (permLoading) {
    return <p className="text-sm text-muted-foreground">{t('loading')}</p>;
  }

  if (!hasRole('system_manager')) {
    return (
      <div className="space-y-2 max-w-5xl">
        <h1 className="text-xl font-semibold tracking-tight">{t('pageTitle')}</h1>
        <p className="text-sm text-destructive">{t('forbidden')}</p>
      </div>
    );
  }

  return (
    <div className="space-y-8 max-w-5xl">
      <div>
        <h1 className="text-xl font-semibold tracking-tight">{t('pageTitle')}</h1>
        <p className="text-sm text-muted-foreground mt-1">{t('pageDescription')}</p>
      </div>

      <HostingInfrastructureTab />

      <Card>
        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
          <div>
            <CardTitle className="text-base">{t('auditTitle')}</CardTitle>
            <CardDescription>{t('auditDescription')}</CardDescription>
          </div>
          <Button type="button" size="sm" variant="outline" onClick={() => void loadAudits()}>
            {t('refresh')}
          </Button>
        </CardHeader>
        <CardContent>
          {auditErr ? <p className="text-sm text-destructive">{auditErr}</p> : null}
          {!auditErr && audits.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('noAudit')}</p>
          ) : null}
          {audits.length > 0 ? (
            <div className="overflow-x-auto rounded-md border text-xs">
              <table className="w-full">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start font-medium">{t('time')}</th>
                    <th className="px-2 py-2 text-start font-medium">{t('channel')}</th>
                    <th className="px-2 py-2 text-start font-medium">{t('action')}</th>
                    <th className="px-2 py-2 text-start font-medium">{t('details')}</th>
                  </tr>
                </thead>
                <tbody>
                  {audits.map((a) => (
                    <tr key={a.id} className="border-b last:border-0">
                      <td className="px-2 py-2 whitespace-nowrap font-mono" dir="ltr">
                        {a.created_at}
                      </td>
                      <td className="px-2 py-2">{a.channel}</td>
                      <td className="px-2 py-2">{a.action}</td>
                      <td className="px-2 py-2 max-w-md truncate font-mono" dir="ltr" title={JSON.stringify(a.payload ?? {})}>
                        {JSON.stringify(a.payload ?? {})}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  );
}
