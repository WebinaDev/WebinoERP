'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { useLocale } from '@/hooks/use-locale-next';
import { approveHrmRequest, getCartableInbox, rejectHrmRequest } from '@/lib/api/hrm';

export function HrmCartablePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDate } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [items, setItems] = useState<
    Array<{ id: number; type: string; status: string; user_name?: string; created_at?: string }>
  >([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getCartableInbox();
      setItems(res?.requests ?? []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const decide = async (id: number, approve: boolean) => {
    try {
      if (approve) await approveHrmRequest(id);
      else await rejectHrmRequest(id);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.cartable')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : items.length === 0 ? (
        <div className="py-8 text-muted-foreground">{tNav('common.empty')}</div>
      ) : (
        <div className="space-y-3 text-start">
          {items.map((r) => (
            <Card key={r.id}>
              <CardContent className="pt-6 space-y-2 text-sm">
                <div className="flex flex-wrap justify-between gap-2">
                  <span className="font-medium">
                    {r.user_name} · {r.type}
                  </span>
                  <span className="text-muted-foreground">{r.status}</span>
                </div>
                {r.created_at ? (
                  <div className="text-xs text-muted-foreground">
                    {formatDate(r.created_at) || r.created_at}
                  </div>
                ) : null}
                <div className="text-xs text-muted-foreground">{t('portal.cartableHint')}</div>
                <div className="flex gap-2">
                  <Button size="sm" onClick={() => void decide(r.id, true)}>{t('approve')}</Button>
                  <Button size="sm" variant="outline" onClick={() => void decide(r.id, false)}>{t('reject')}</Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </HrmPageLayout>
  );
}
