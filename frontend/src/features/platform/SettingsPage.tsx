'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { fetchPlatformSettings, updatePlatformSettings, type PlatformSettings } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function SettingsPage() {
  const t = useTranslations('platform.settings');
  const tP = useTranslations('platform');
  const [settings, setSettings] = useState<PlatformSettings | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setSettings(await fetchPlatformSettings());
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  async function save() {
    if (!settings) return;
    setPending(true);
    try {
      setSettings(await updatePlatformSettings({
        default_proxy: settings.default_proxy,
        wildcard_domain: settings.wildcard_domain,
        api_enabled: settings.api_enabled,
      }));
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('saveError'));
    } finally {
      setPending(false);
    }
  }

  return (
    <PlatformPageLayout title={t('title')} subtitle={t('subtitle')} error={error} actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}>
      {settings ? (
        <Card><CardContent className="grid gap-3 pt-6 md:grid-cols-2">
          <div className="grid gap-2"><Label>{t('defaultProxy')}</Label><Input value={settings.default_proxy} onChange={(e) => setSettings({ ...settings, default_proxy: e.target.value })} /></div>
          <div className="grid gap-2"><Label>{t('wildcardDomain')}</Label><Input value={settings.wildcard_domain ?? ''} onChange={(e) => setSettings({ ...settings, wildcard_domain: e.target.value })} dir="ltr" className="font-mono" /></div>
          <div className="flex items-center gap-2"><Switch checked={settings.api_enabled} onCheckedChange={(on) => setSettings({ ...settings, api_enabled: on })} /><Label>{t('apiEnabled')}</Label></div>
          <Button disabled={pending} onClick={() => void save()}>{tP('save')}</Button>
        </CardContent></Card>
      ) : (
        <p className="text-sm text-muted-foreground">{tP('loading')}</p>
      )}
    </PlatformPageLayout>
  );
}
