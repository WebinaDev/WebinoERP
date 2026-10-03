'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { useLocale } from '@/hooks/use-locale-next';

type FunnelRow = { stage_id: number; name: string; count: number; amount: number; weighted: number };
type QuotaRow = { id: number; user_id: number; amount: number; won_amount: number; attainment: number; period_start: string; period_end: string };
type LossRow = { reason: string; count: number };
type Report = {
  pipeline_amount: number;
  weighted_amount: number;
  won_amount: number;
  won_count: number;
  lost_amount: number;
  lost_count: number;
  funnel: FunnelRow[];
  quotas: QuotaRow[];
  loss_reasons: LossRow[];
};

export function CrmForecastPage() {
  const t = useTranslations('suite');
  const { formatNumber, formatDate, isRtl } = useLocale();
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [report, setReport] = useState<Report | null>(null);
  const [userId, setUserId] = useState('');
  const [amount, setAmount] = useState('');
  const [start, setStart] = useState<string | null>(null);
  const [end, setEnd] = useState<string | null>(null);

  const load = useCallback(async () => {
    const res = await apiClient.get('/v1/crm/forecast');
    setReport(unwrapData<Report>(res));
  }, []);

  useEffect(() => {
    void load().catch((error) => applyAxiosError(error));
  }, [load, applyAxiosError]);

  async function saveQuota() {
    if (!userId || !amount || !start || !end) return;
    try {
      await apiClient.post('/v1/crm/quotas', {
        user_id: Number(userId),
        amount: Number(amount),
        period_start: start,
        period_end: end,
      });
      setAmount('');
      setSuccess(t('saveQuota'));
      await load();
    } catch (error) {
      applyAxiosError(error);
    }
  }

  const cards = [
    { label: t('pipeline'), value: Number(report?.pipeline_amount ?? 0) },
    { label: t('weighted'), value: Number(report?.weighted_amount ?? 0) },
    { label: t('won'), value: Number(report?.won_amount ?? 0) },
    { label: t('lost'), value: Number(report?.lost_amount ?? 0) },
  ];

  return (
    <CrmPageLayout title={t('forecastTitle')} description={t('forecastHint')} {...layoutProps}>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {cards.map((card) => (
          <Card key={card.label}>
            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-muted-foreground">{card.label}</CardTitle></CardHeader>
            <CardContent className="text-2xl font-semibold">{formatNumber(card.value)}</CardContent>
          </Card>
        ))}
      </div>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('funnel')}</CardTitle></CardHeader>
        <CardContent className="overflow-x-auto">
          {!report?.funnel.length ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : (
            <table className="w-full text-sm" dir={isRtl ? 'rtl' : 'ltr'}>
              <thead>
                <tr className="border-b text-muted-foreground">
                  <th className="py-2 text-start">{t('stage')}</th>
                  <th className="py-2 text-start">{t('count')}</th>
                  <th className="py-2 text-start">{t('amount')}</th>
                  <th className="py-2 text-start">{t('weighted')}</th>
                </tr>
              </thead>
              <tbody>
                {report.funnel.map((row) => (
                  <tr key={row.stage_id} className="border-b border-border/60">
                    <td className="py-2">{row.name}</td>
                    <td className="py-2">{formatNumber(Number(row.count))}</td>
                    <td className="py-2">{formatNumber(Number(row.amount))}</td>
                    <td className="py-2">{formatNumber(Number(row.weighted))}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>
      </Card>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('quota')}</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <form className="grid gap-2 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); void saveQuota(); }}>
              <div className="space-y-1">
                <Label htmlFor="quota-user">{t('quotaUser')}</Label>
                <Input id="quota-user" inputMode="numeric" value={userId} onChange={(event) => setUserId(event.target.value)} />
              </div>
              <div className="space-y-1">
                <Label htmlFor="quota-amount">{t('amount')}</Label>
                <Input id="quota-amount" inputMode="decimal" value={amount} onChange={(event) => setAmount(event.target.value)} />
              </div>
              <div className="space-y-1">
                <Label>{t('periodStart')}</Label>
                <LocaleDatePicker value={start} onChange={setStart} />
              </div>
              <div className="space-y-1">
                <Label>{t('periodEnd')}</Label>
                <LocaleDatePicker value={end} onChange={setEnd} />
              </div>
              <Button type="submit" size="sm" className="sm:col-span-2 w-fit">{t('saveQuota')}</Button>
            </form>
            {!report?.quotas.length ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : report.quotas.map((row) => (
              <p key={row.id} className="text-sm">
                #{formatNumber(Number(row.user_id))} · {formatNumber(Number(row.won_amount))} / {formatNumber(Number(row.amount))} · {formatNumber(Number(row.attainment))}%
                <span className="text-muted-foreground"> · {formatDate(row.period_start)}</span>
              </p>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('lossReasons')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {!report?.loss_reasons.length ? <p className="text-sm text-muted-foreground">{t('noLoss')}</p> : report.loss_reasons.map((row) => (
              <p key={row.reason || 'none'} className="text-sm">{row.reason || t('empty')} · {formatNumber(Number(row.count))}</p>
            ))}
          </CardContent>
        </Card>
      </div>
    </CrmPageLayout>
  );
}
