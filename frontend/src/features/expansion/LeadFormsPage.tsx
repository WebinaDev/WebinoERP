'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { expansionApi, type Row } from '@/features/expansion/api';

export function LeadFormsPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Row[]>([]);
  const [form, setForm] = useState({ name: '', slug: '', landing_url: '' });

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
    </CrmPageLayout>
  );
}
