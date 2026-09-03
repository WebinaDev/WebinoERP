'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getMyDecrees, getMyNotices } from '@/lib/api/hrm';

export function MyDocsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [decrees, setDecrees] = useState<
    Array<{ id: number; decree_no?: string; status?: string; effective_from?: string }>
  >([]);
  const [notices, setNotices] = useState<Array<{ id: number; title: string; body?: string }>>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [dRes, nRes] = await Promise.all([getMyDecrees(), getMyNotices()]);
      setDecrees(dRes?.decrees ?? []);
      setNotices(nRes?.notices ?? []);
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
    <CrmPageLayout title={tNav('nav.erp.hrm.myDocs')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-4 text-start">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.decrees')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {decrees.length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                decrees.map((d) => (
                  <div key={d.id} className="flex justify-between rounded border px-3 py-2">
                    <span>
                      {d.decree_no || `#${d.id}`} · {d.effective_from || '—'}
                    </span>
                    <span className="text-muted-foreground">{d.status}</span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.notices')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              {notices.length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                notices.map((n) => (
                  <div key={n.id} className="rounded border p-3">
                    <div className="font-medium">{n.title}</div>
                    {n.body ? <div className="text-muted-foreground whitespace-pre-wrap">{n.body}</div> : null}
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
