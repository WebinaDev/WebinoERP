'use client';

import { useCallback, useEffect, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getMyAttendance, getMyShift, punchMyAttendance } from '@/lib/api/hrm';

export function MyTimePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const intlLocale = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [attendance, setAttendance] = useState<
    Array<{ id: number; work_date: string; check_in?: string; check_out?: string }>
  >([]);
  const [shift, setShift] = useState<{ name: string; start_time?: string; end_time?: string } | null>(null);
  const [loading, setLoading] = useState(true);

  const formatNumber = (n: number) =>
    new Intl.NumberFormat(intlLocale === 'fa' ? 'fa-IR' : 'en-US').format(n);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [att, sh] = await Promise.all([getMyAttendance(), getMyShift()]);
      setAttendance(att?.items ?? []);
      setShift(sh?.shift ?? null);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const punch = async (action: 'in' | 'out') => {
    try {
      await punchMyAttendance({ action });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.myTime')} {...layoutProps}>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-4 text-start">
          <div className="flex flex-wrap gap-2">
            <Button onClick={() => void punch('in')}>{t('checkIn')}</Button>
            <Button variant="outline" onClick={() => void punch('out')}>
              {t('checkOut')}
            </Button>
          </div>
          {shift ? (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('portal.shift')}</CardTitle>
              </CardHeader>
              <CardContent className="text-sm">
                {shift.name}: {shift.start_time || '—'} – {shift.end_time || '—'}
              </CardContent>
            </Card>
          ) : null}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{tNav('nav.erp.hrm.attendance')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              {attendance.length === 0 ? (
                <div className="text-muted-foreground">{tNav('common.empty')}</div>
              ) : (
                attendance.map((a) => (
                  <div key={a.id} className="flex justify-between rounded border px-3 py-2">
                    <span>{a.work_date}</span>
                    <span>
                      {a.check_in || '—'} – {a.check_out || '—'}
                    </span>
                  </div>
                ))
              )}
              {attendance.length > 0 ? (
                <div className="text-xs text-muted-foreground pt-1">
                  {formatNumber(attendance.length)} {t('portal.records')}
                </div>
              ) : null}
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  );
}
