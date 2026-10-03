'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { expansionApi, type Row } from '@/features/expansion/api';
import { AiAssistPanel } from '@/features/expansion/AiAssistPanel';

export function CompaniesPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [companies, setCompanies] = useState<Row[]>([]);
  const [currencies, setCurrencies] = useState<Row[]>([]);
  const [active, setActive] = useState<Row | null>(null);
  const [form, setForm] = useState({ name: '', legal_name: '', national_id: '', economic_code: '', currency_code: 'IRR' });
  const [fx, setFx] = useState({ base_code: 'USD', quote_code: 'IRR', rate: '' });
  const [convert, setConvert] = useState({ amount: '1', from: 'USD', to: 'IRR', result: '' });

  const load = useCallback(async () => {
    try {
      const [rows, rates, current] = await Promise.all([
        expansionApi.companies(),
        expansionApi.currencies(),
        expansionApi.activeCompany(),
      ]);
      setCompanies(rows);
      setCurrencies(rates);
      setActive(current);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={t('companies.title')} description={t('companies.description')} {...layoutProps}>
      {active ? <p className="text-sm">{t('companies.active', { name: String(active.name) })}</p> : null}
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('companies.new')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('companies.name')} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            <Input placeholder={t('companies.legal')} value={form.legal_name} onChange={(e) => setForm({ ...form, legal_name: e.target.value })} />
            <Input placeholder={t('companies.national')} value={form.national_id} onChange={(e) => setForm({ ...form, national_id: e.target.value })} />
            <Input placeholder={t('companies.economic')} value={form.economic_code} onChange={(e) => setForm({ ...form, economic_code: e.target.value })} />
            <Input placeholder={t('companies.currency')} value={form.currency_code} onChange={(e) => setForm({ ...form, currency_code: e.target.value.toUpperCase() })} />
            <Button type="button" onClick={() => void expansionApi.saveCompany(form).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
          </CardContent>
        </Card>
        <div className="space-y-3">
          {companies.map((row) => (
            <Card key={String(row.id)}>
              <CardContent className="flex flex-wrap items-center justify-between gap-2 py-4">
                <div>
                  <p className="font-medium">{String(row.name)}</p>
                  <p className="text-xs text-muted-foreground">{String(row.currency_code || 'IRR')} · {String(row.economic_code || '')}</p>
                </div>
                <Button type="button" size="sm" variant="outline" onClick={() => void expansionApi.switchCompany(Number(row.id)).then(() => { setSuccess(t('companies.switched')); return load(); }).catch(applyAxiosError)}>
                  {t('companies.switch')}
                </Button>
              </CardContent>
            </Card>
          ))}
          <p className="text-xs text-muted-foreground">{currencies.map((c) => String(c.code)).join(' · ')}</p>
        </div>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('companies.fx')}</CardTitle></CardHeader>
          <CardContent className="grid gap-2 sm:grid-cols-3">
            <Input value={fx.base_code} onChange={(e) => setFx({ ...fx, base_code: e.target.value.toUpperCase() })} />
            <Input value={fx.quote_code} onChange={(e) => setFx({ ...fx, quote_code: e.target.value.toUpperCase() })} />
            <Input value={fx.rate} placeholder={t('companies.rate')} onChange={(e) => setFx({ ...fx, rate: e.target.value })} />
            <Button type="button" className="sm:col-span-3" onClick={() => void expansionApi.saveRate({ ...fx, rate: Number(fx.rate) }).then(() => setSuccess(t('common.saved'))).catch(applyAxiosError)}>{t('common.save')}</Button>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('companies.convert')}</CardTitle></CardHeader>
          <CardContent className="grid gap-2 sm:grid-cols-3">
            <Input value={convert.amount} onChange={(e) => setConvert({ ...convert, amount: e.target.value })} />
            <Input value={convert.from} onChange={(e) => setConvert({ ...convert, from: e.target.value.toUpperCase() })} />
            <Input value={convert.to} onChange={(e) => setConvert({ ...convert, to: e.target.value.toUpperCase() })} />
            <Button
              type="button"
              className="sm:col-span-3"
              variant="outline"
              onClick={() => void expansionApi.convert({ amount: Number(convert.amount), from: convert.from, to: convert.to }).then((row) => setConvert({ ...convert, result: String(row.amount) })).catch(applyAxiosError)}
            >
              {t('companies.convert')}
            </Button>
            {convert.result ? <p className="text-sm sm:col-span-3">{convert.result}</p> : null}
          </CardContent>
        </Card>
      </div>
      <AiAssistPanel purpose="lead_suggest" />
    </CrmPageLayout>
  );
}
