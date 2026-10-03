'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { expansionApi, type Row } from '@/features/expansion/api';

export function LeadFormsPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Row[]>([]);
  const [form, setForm] = useState({ name: '', slug: '', landing_url: '' });
  const [model, setModel] = useState('linear');
  const [roi, setRoi] = useState<Row[]>([]);
  const [spend, setSpend] = useState({ campaign_key: '', spent: '' });

  const load = useCallback(async () => {
    try {
      setRows(await expansionApi.forms());
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={t('forms.title')} description={t('forms.description')} {...layoutProps}>
      <div className="grid max-w-xl gap-2">
        <Input placeholder={t('forms.name')} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        <Input placeholder={t('forms.slug')} value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} />
        <Input placeholder={t('forms.landing')} value={form.landing_url} onChange={(e) => setForm({ ...form, landing_url: e.target.value })} />
        <Button type="button" onClick={() => void expansionApi.saveForm(form).then(() => { setSuccess(t('common.saved')); setForm({ name: '', slug: '', landing_url: '' }); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
      </div>
      <div className="grid gap-3">
        {rows.map((row) => (
          <Card key={String(row.id)}>
            <CardContent className="py-4">
              <p className="font-medium">{String(row.name)}</p>
              <p className="text-sm text-muted-foreground">{t('forms.public')}: /api/v1/crm/public/forms/{String(row.slug)}</p>
              {row.landing_url ? <p className="text-xs text-muted-foreground">{String(row.landing_url)}</p> : null}
            </CardContent>
          </Card>
        ))}
      </div>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('forms.roi')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          <select className="h-9 w-full max-w-xs rounded-md border border-input bg-background px-3 text-sm" value={model} onChange={(event) => setModel(event.target.value)}>
            <option value="linear">linear</option>
            <option value="first">first</option>
            <option value="last">last</option>
            <option value="position">position</option>
          </select>
          <div className="flex flex-wrap gap-2">
            <Input className="max-w-xs" placeholder={t('forms.campaign')} value={spend.campaign_key} onChange={(event) => setSpend({ ...spend, campaign_key: event.target.value })} />
            <Input className="max-w-xs" placeholder={t('forms.spent')} value={spend.spent} onChange={(event) => setSpend({ ...spend, spent: event.target.value })} />
            <Button type="button" variant="outline" onClick={() => void expansionApi.saveSpend({ campaign_key: spend.campaign_key, spent: Number(spend.spent), currency_code: 'IRR' }).then(() => setSuccess(t('common.saved'))).catch(applyAxiosError)}>{t('common.save')}</Button>
            <Button type="button" onClick={() => void expansionApi.roi(model).then(setRoi).catch(applyAxiosError)}>{t('forms.roi')}</Button>
          </div>
          <ul className="space-y-1 text-sm">
            {roi.map((row) => (
              <li key={String(row.campaign)}>{String(row.campaign)} · {String(row.revenue)} / {String(row.spent)} · ROI {String(row.roi ?? '')}</li>
            ))}
          </ul>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
