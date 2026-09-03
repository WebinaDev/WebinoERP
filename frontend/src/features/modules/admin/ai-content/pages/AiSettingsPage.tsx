'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { AiContentShell } from '../components/AiContentShell';
import { type AiSettings, fetchAiSettings, saveAiSettings } from '../lib/ai-content-api';

export function AiSettingsPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [form, setForm] = useState<AiSettings>({});
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      setForm(await fetchAiSettings());
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const set = <K extends keyof AiSettings>(key: K, value: AiSettings[K]) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const save = async () => {
    setBusy(true);
    try {
      const saved = await saveAiSettings(form);
      setForm(saved);
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setBusy(false);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.aiContent.settings')}
      actions={
        <Button size="sm" disabled={busy} onClick={() => void save()}>
          {tNav('common.save')}
        </Button>
      }
      {...layoutProps}
    >
      <AiContentShell active="settings">
        <Card>
          <CardHeader>
            <CardTitle>{t('settingsGeneral')}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2">
            <div className="flex items-center justify-between gap-2 md:col-span-2">
              <Label>{t('enabled')}</Label>
              <Switch checked={!!form.enabled} onCheckedChange={(v) => set('enabled', v)} />
            </div>
            <div className="space-y-1">
              <Label>{t('defaultProvider')}</Label>
              <Input
                value={String(form.default_provider ?? '')}
                onChange={(e) => set('default_provider', e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label>{t('siteName')}</Label>
              <Input value={String(form.site_name ?? '')} onChange={(e) => set('site_name', e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label>{t('siteTopic')}</Label>
              <Input value={String(form.site_topic ?? '')} onChange={(e) => set('site_topic', e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label>{t('tone')}</Label>
              <Input value={String(form.tone ?? '')} onChange={(e) => set('tone', e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label>{t('language')}</Label>
              <Input value={String(form.language ?? '')} onChange={(e) => set('language', e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label>{t('dailyBlogQuota')}</Label>
              <Input
                type="number"
                value={Number(form.daily_blog_quota ?? 0)}
                onChange={(e) => set('daily_blog_quota', Number(e.target.value))}
              />
            </div>
            <div className="space-y-1">
              <Label>{t('dailyProductQuota')}</Label>
              <Input
                type="number"
                value={Number(form.daily_product_quota ?? 0)}
                onChange={(e) => set('daily_product_quota', Number(e.target.value))}
              />
            </div>
            <div className="flex items-center justify-between gap-2">
              <Label>{t('doEntity')} (product)</Label>
              <Switch checked={!!form.do_product} onCheckedChange={(v) => set('do_product', v)} />
            </div>
            <div className="flex items-center justify-between gap-2">
              <Label>{t('doEntity')} (blog)</Label>
              <Switch checked={!!form.do_blog} onCheckedChange={(v) => set('do_blog', v)} />
            </div>
            <div className="flex items-center justify-between gap-2">
              <Label>{t('doEntity')} (page)</Label>
              <Switch checked={!!form.do_page} onCheckedChange={(v) => set('do_page', v)} />
            </div>
            <div className="flex items-center justify-between gap-2">
              <Label>{t('autoPublish')}</Label>
              <Switch checked={!!form.auto_publish} onCheckedChange={(v) => set('auto_publish', v)} />
            </div>
            <div className="space-y-1 md:col-span-2">
              <Label>{t('siteDescription')}</Label>
              <Textarea
                rows={3}
                value={String(form.site_description ?? '')}
                onChange={(e) => set('site_description', e.target.value)}
              />
            </div>
            <div className="space-y-1 md:col-span-2">
              <Label>{t('promptSystem')}</Label>
              <Textarea
                rows={4}
                value={String(form.prompt_system ?? '')}
                onChange={(e) => set('prompt_system', e.target.value)}
              />
            </div>
            <div className="space-y-1 md:col-span-2">
              <Label>{t('promptProduct')}</Label>
              <Textarea
                rows={3}
                value={String(form.prompt_product ?? '')}
                onChange={(e) => set('prompt_product', e.target.value)}
              />
            </div>
            <div className="space-y-1 md:col-span-2">
              <Label>{t('promptBlog')}</Label>
              <Textarea
                rows={3}
                value={String(form.prompt_blog ?? '')}
                onChange={(e) => set('prompt_blog', e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label>{t('gapgptApiKey')}</Label>
              <Input
                type="password"
                placeholder={form.has_gapgpt_key ? '••••••••' : ''}
                onChange={(e) => set('gapgpt_api_key' as keyof AiSettings, e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label>{t('openaiApiKey')}</Label>
              <Input
                type="password"
                placeholder={form.has_openai_key ? '••••••••' : ''}
                onChange={(e) => set('openai_api_key' as keyof AiSettings, e.target.value)}
              />
            </div>
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
