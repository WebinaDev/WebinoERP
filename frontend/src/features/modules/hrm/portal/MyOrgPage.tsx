'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getMyNotices, getMyOrgChart } from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';

export function MyOrgPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [notices, setNotices] = useState<Array<{ id: number; title: string }>>([]);
  const [org, setOrg] = useState<{
    departments: Array<{ id: number; name: string }>;
    positions: Array<{ id: number; name: string }>;
  } | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [nRes, orgRes] = await Promise.all([getMyNotices(), getMyOrgChart()]);
      setNotices(nRes?.notices ?? []);
      setOrg(orgRes ?? { departments: [], positions: [] });
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.myOrg')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-4 text-start">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.notices')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {notices.length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                notices.slice(0, 5).map((n) => (
                  <div key={n.id} className="rounded border px-3 py-2">
                    {n.title}
                  </div>
                ))
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.orgChart')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-1 text-sm">
              {(org?.departments ?? []).length === 0 && (org?.positions ?? []).length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                <>
                  {(org?.departments ?? []).map((d) => (
                    <div key={d.id} className="font-medium">
                      {d.name}
                    </div>
                  ))}
                  {(org?.positions ?? []).map((p) => (
                    <div key={p.id} className="ps-4 text-muted-foreground">
                      ↳ {p.name}
                    </div>
                  ))}
                </>
              )}
            </CardContent>
          </Card>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" asChild>
              <Link href={dashboardHref(locale, 'hrm/training')}>{t('portal.myTraining')}</Link>
            </Button>
            <Button variant="outline" asChild>
              <Link href={dashboardHref(locale, 'hrm/performance')}>{t('portal.myReviews')}</Link>
            </Button>
            <Button variant="outline" asChild>
              <Link href={dashboardHref(locale, 'hrm/cartable')}>{tNav('nav.erp.hrm.cartable')}</Link>
            </Button>
          </div>
        </div>
      )}
    </CrmPageLayout>
  );
}
