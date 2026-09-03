'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useLocale, useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getHrmMe, type HrmMeResponse } from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';

export function MyPortalPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const intlLocale = useLocale();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [data, setData] = useState<HrmMeResponse | null>(null);
  const [loading, setLoading] = useState(true);

  const formatNumber = (n: number) =>
    new Intl.NumberFormat(intlLocale === 'fa' ? 'fa-IR' : 'en-US').format(n);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setData(await getHrmMe());
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const id = data?.identity;

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.portal')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : !id ? (
        <div className="py-8 text-muted-foreground">{t('portal.noEmployee')}</div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 text-start">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.identity')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div>{id.display_name}</div>
              <div>
                {id.job_title || '—'} · {id.department || '—'}
              </div>
              <div>
                {t('portal.personnelCode')}: {id.personnel_code || '—'}
              </div>
              <div>
                {t('portal.nationalId')}: {id.national_id || '—'}
              </div>
              <div>
                {t('portal.manager')}: {id.direct_manager?.name || '—'}
              </div>
              <div>
                {t('portal.hireDate')}: {id.hire_date || '—'}
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.leaveBalance')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {(data.leave_balances ?? []).length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                data.leave_balances?.map((b) => (
                  <div key={b.leave_type_id} className="flex justify-between">
                    <span>{b.type_name}</span>
                    <span>
                      {formatNumber(b.balance)} {t('leaveDays')}
                    </span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.latestPayslip')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {data.latest_payslip ? (
                <>
                  <div>{data.latest_payslip.run_title || `#${data.latest_payslip.id}`}</div>
                  <div>
                    {formatNumber(data.latest_payslip.net ?? 0)} {t('net')}
                  </div>
                  <Button size="sm" variant="outline" asChild>
                    <Link href={dashboardHref(locale, 'hrm/my-payroll')}>{t('portal.viewPayslips')}</Link>
                  </Button>
                </>
              ) : (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.openRequests')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="text-2xl font-semibold">{formatNumber(data.open_requests ?? 0)}</div>
              <Button size="sm" variant="outline" asChild>
                <Link href={dashboardHref(locale, 'hrm/my-org')}>{t('portal.viewRequests')}</Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
