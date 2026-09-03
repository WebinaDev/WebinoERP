'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getHrmMe, getMyDependents, saveMyDependent, type HrmMeResponse } from '@/lib/api/hrm';

export function MyInsurancePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [me, setMe] = useState<HrmMeResponse | null>(null);
  const [dependents, setDependents] = useState<
    Array<{ id: number; full_name: string; relation?: string; national_id?: string }>
  >([]);
  const [fullName, setFullName] = useState('');
  const [relation, setRelation] = useState('');
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [meRes, depRes] = await Promise.all([getHrmMe(), getMyDependents()]);
      setMe(meRes);
      setDependents(depRes?.dependents ?? []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const addDependent = async () => {
    if (!fullName.trim()) return;
    try {
      await saveMyDependent({ full_name: fullName.trim(), relation: relation.trim() || null });
      setFullName('');
      setRelation('');
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const id = me?.identity;

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.myInsurance')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-4 text-start">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.insuranceStatus')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div>
                {t('portal.insuranceNumber')}: {id?.insurance_number || '—'}
              </div>
              <div>
                {t('portal.workshop')}: {id?.workshop?.name || '—'}
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('portal.dependents')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              {dependents.length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                dependents.map((d) => (
                  <div key={d.id} className="rounded border px-3 py-2">
                    {d.full_name} · {d.relation || '—'} · {d.national_id || '—'}
                  </div>
                ))
              )}
              <div className="flex flex-wrap gap-2 pt-2">
                <Input
                  className="max-w-[12rem]"
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  placeholder={t('portal.dependentName')}
                />
                <Input
                  className="max-w-[10rem]"
                  value={relation}
                  onChange={(e) => setRelation(e.target.value)}
                  placeholder={t('portal.relation')}
                />
                <Button onClick={() => void addDependent()} disabled={!fullName.trim()}>
                  {tNav('common.add')}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
