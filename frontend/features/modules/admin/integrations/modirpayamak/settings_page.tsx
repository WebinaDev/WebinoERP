'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { getModirPayamakSettings, updateModirPayamakSettings } from '@/lib/api/modirpayamak';
import { ModirPayamakBreadcrumb } from './components/shared';

export function ModirpayamakSettingsPage() {
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [form, setForm] = useState({ api_key: '', default_from: '', enabled: true });

  const load = useCallback(async () => {
    try {
      const res = await getModirPayamakSettings();
      const data = res as Record<string, unknown>;
      setForm({
        api_key: String(data.api_key ?? ''),
        default_from: String(data.default_from ?? ''),
        enabled: Boolean(data.enabled ?? true),
      });
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async () => {
    try {
      await updateModirPayamakSettings(form);
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpSettings')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpSettings')} />
      <Card>
        <CardContent className="space-y-4 pt-6">
          <Input placeholder="API Key" value={form.api_key} onChange={(e) => setForm({ ...form, api_key: e.target.value })} />
          <Input placeholder="Default from line" value={form.default_from} onChange={(e) => setForm({ ...form, default_from: e.target.value })} />
          <Button onClick={() => void save()}>{tNav('common.save')}</Button>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
