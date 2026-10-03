'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale-next';

type LoadRow = { user_id: number | null; open_tasks: number; overdue: number; estimate_hours: number; logged_hours: number };
type DelayTask = { id: number; title: string; due_at?: string | null };

export function PmWorkloadPage() {
  const t = useTranslations('suite');
  const { formatNumber, formatDateTime, isRtl } = useLocale();
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [rows, setRows] = useState<LoadRow[]>([]);
  const [delays, setDelays] = useState<DelayTask[]>([]);

  const load = useCallback(async () => {
    const [work, late] = await Promise.all([
      apiClient.get('/v1/projects/workload'),
      apiClient.get('/v1/projects/delay-alerts'),
    ]);
    setRows(unwrapData<LoadRow[]>(work) ?? []);
    const payload = unwrapData<{ tasks?: DelayTask[] }>(late);
    setDelays(payload?.tasks ?? []);
  }, []);

  useEffect(() => {
    void load().catch((error) => applyAxiosError(error));
  }, [load, applyAxiosError]);

  return (
    <CrmPageLayout
      title={t('workloadTitle')}
      description={t('workloadHint')}
      actions={<Button size="sm" variant="outline" onClick={() => void apiClient.post('/v1/projects/delay-alerts/notify').then(() => setSuccess(t('notifyDelays'))).catch(applyAxiosError)}>{t('notifyDelays')}</Button>}
      {...layoutProps}
    >
      {rows.length === 0 ? <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">{t('empty')}</p> : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm" dir={isRtl ? 'rtl' : 'ltr'}>
            <thead>
              <tr className="border-b text-muted-foreground">
                <th className="px-3 py-2 text-start">{t('person')}</th>
                <th className="px-3 py-2 text-start">{t('openTasks')}</th>
                <th className="px-3 py-2 text-start">{t('overdue')}</th>
                <th className="px-3 py-2 text-start">{t('estimate')}</th>
                <th className="px-3 py-2 text-start">{t('logged')}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={String(row.user_id ?? 'none')} className="border-b border-border/60">
                  <td className="px-3 py-2">{row.user_id ? formatNumber(Number(row.user_id)) : t('unassigned')}</td>
                  <td className="px-3 py-2">{formatNumber(Number(row.open_tasks))}</td>
                  <td className="px-3 py-2">{formatNumber(Number(row.overdue))}</td>
                  <td className="px-3 py-2">{formatNumber(Number(row.estimate_hours))}</td>
                  <td className="px-3 py-2">{formatNumber(Number(row.logged_hours))}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <div className="space-y-2">
        <h2 className="text-base font-medium">{t('delays')}</h2>
        {delays.length === 0 ? <p className="text-sm text-muted-foreground">{t('noDelays')}</p> : delays.map((task) => (
          <p key={task.id} className="rounded-md border p-3 text-sm">
            {task.title}
            {task.due_at ? <span className="text-muted-foreground"> · {formatDateTime(task.due_at)}</span> : null}
          </p>
        ))}
      </div>
    </CrmPageLayout>
  );
}
