'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import apiClient from '@/lib/api-client';
import { testGiteaConnection } from '@/lib/api/marketplace';

export function GiteaPage() {
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [form, setForm] = useState({ base_url: '', token: '', org: '' });

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/marketplace/gitea');
      const data = res.data as { data?: Record<string, unknown> };
      const d = data.data ?? {};
      setForm({
        base_url: String(d.base_url ?? ''),
        token: String(d.token ?? ''),
        org: String(d.org ?? ''),
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
      await apiClient.put('/v1/marketplace/gitea', form);
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const test = async () => {
    try {
      await testGiteaConnection(form);
      setSuccess('Gitea OK');
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.distribution.marketplaceGitea')} {...layoutProps}>
      <Card>
        <CardContent className="space-y-4 pt-6">
          <Input placeholder="Base URL" value={form.base_url} onChange={(e) => setForm({ ...form, base_url: e.target.value })} />
          <Input placeholder="Token" value={form.token} onChange={(e) => setForm({ ...form, token: e.target.value })} />
          <Input placeholder="Organization" value={form.org} onChange={(e) => setForm({ ...form, org: e.target.value })} />
          <div className="flex gap-2">
            <Button onClick={() => void save()}>{tNav('common.save')}</Button>
            <Button variant="outline" onClick={() => void test()}>Test</Button>
          </div>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
