'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input'
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Card, CardContent } from '@/components/ui/card';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getPayrollDecrees, savePayrollDecree } from '@/lib/api/hrm';

export function PayrollDecreesPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [userId, setUserId] = useState('');
  const [daily, setDaily] = useState('0');
  const [jobCode, setJobCode] = useState('');
  const [effectiveFrom, setEffectiveFrom] = useState(() => new Date().toISOString().slice(0, 10));
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getPayrollDecrees();
      setRows(res?.decrees ?? []);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const create = async () => {
    try {
      await savePayrollDecree({
        user_id: Number(userId),
        decree_type: 'hire',
        status: 'issued',
        effective_from: effectiveFrom,
        daily_wage: Number(daily) || 0,
        job_code: jobCode,
      });
      setSuccess(tNav('common.saved'));
      setUserId('');
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.decrees')} {...layoutProps}>
      <Card className="mb-4 text-start">
        <CardContent className="pt-6 flex flex-wrap gap-2">
          <Input
            className="max-w-[8rem]"
            value={userId}
            onChange={(e) => setUserId(e.target.value)}
            placeholder={t('portal.userId')}
          />
          <Input
            className="max-w-[8rem]"
            value={daily}
            onChange={(e) => setDaily(e.target.value)}
            placeholder={t('portal.dailyWage')}
          />
          <Input
            className="max-w-[8rem]"
            value={jobCode}
            onChange={(e) => setJobCode(e.target.value)}
            placeholder={t('portal.jobCode')}
          />
          <LocaleDatePicker
            className="max-w-[12rem]"
            value={effectiveFrom}
            onChange={setEffectiveFrom}
          />
          <Button onClick={() => void create()} disabled={!userId}>
            {t('portal.issueDecree')}
          </Button>
        </CardContent>
      </Card>
      {loading ? (
        <PageLoadingState />
      ) : (
        <div className="space-y-2">
          {rows.length === 0 ? (
            <div className="py-8 text-muted-foreground">{tNav('common.empty')}</div>
          ) : (
            rows.map((d) => (
              <div
                key={String(d.id)}
                className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
              >
                <span>
                  {String(d.decree_no ?? d.id)} — user {String(d.user_id ?? '—')} — {String(d.status)}
                </span>
              </div>
            ))
          )}
        </div>
      )}
    </CrmPageLayout>
  );
}
