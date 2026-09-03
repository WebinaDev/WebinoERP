'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { getModirPayamakSettings, updateModirPayamakSettings } from '@/lib/api/modirpayamak';
import { ModirPayamakBreadcrumb } from './components/shared';

type SettingsForm = {
  api_key: string;
  default_from: string;
  enabled: boolean;
};

type SettingsPayload = {
  enabled?: boolean;
  default_from?: string;
  has_api_key?: boolean;
  api_key_masked?: string | null;
};

export function ModirpayamakSettingsPage() {
  const tNav = useTranslations();
  const t = useTranslations('modirpayamak');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [form, setForm] = useState<SettingsForm>({ api_key: '', default_from: '', enabled: true });
  const [hasApiKey, setHasApiKey] = useState(false);
  const [apiKeyMasked, setApiKeyMasked] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const applyPayload = useCallback((data: SettingsPayload) => {
    const hasKey = Boolean(data.has_api_key);
    setHasApiKey(hasKey);
    setApiKeyMasked(data.api_key_masked ? String(data.api_key_masked) : null);
    setForm({
      // Secret is never returned by the API — keep input blank so refresh cannot wipe it.
      api_key: '',
      default_from: String(data.default_from ?? ''),
      // First-time setup defaults to enabled; otherwise honor stored flag.
      enabled: hasKey ? Boolean(data.enabled) : Boolean(data.enabled ?? true),
    });
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getModirPayamakSettings();
      applyPayload((res ?? {}) as SettingsPayload);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, applyPayload]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async () => {
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        enabled: form.enabled,
        default_from: form.default_from,
      };
      const nextKey = form.api_key.trim();
      if (nextKey) {
        payload.api_key = nextKey;
      }
      const res = await updateModirPayamakSettings(payload);
      applyPayload((res ?? {}) as SettingsPayload);
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSaving(false);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.mpSettings')} {...layoutProps}>
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpSettings')} />
      <Card>
        <CardContent className="space-y-4 pt-6">
          {loading ? (
            <p className="text-muted-foreground text-sm">{tNav('common.loading')}</p>
          ) : (
            <>
              <div className="flex items-center gap-2">
                <input
                  id="mp-enabled"
                  type="checkbox"
                  className="size-4"
                  checked={form.enabled}
                  onChange={(e) => setForm({ ...form, enabled: e.target.checked })}
                />
                <Label htmlFor="mp-enabled">{t('settingsEnabled')}</Label>
              </div>

              <div className="grid gap-2">
                <Label htmlFor="mp-api-key">{t('settingsApiKey')}</Label>
                <Input
                  id="mp-api-key"
                  type="password"
                  autoComplete="off"
                  placeholder={hasApiKey ? t('settingsApiKeyKeep') : t('settingsApiKey')}
                  value={form.api_key}
                  onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                  dir="ltr"
                  className="font-mono"
                />
                {hasApiKey ? (
                  <p className="text-muted-foreground text-xs" dir="ltr">
                    {t('settingsApiKeyStored', { mask: apiKeyMasked || '••••' })}
                  </p>
                ) : (
                  <p className="text-muted-foreground text-xs">{t('settingsApiKeyHint')}</p>
                )}
              </div>

              <div className="grid gap-2">
                <Label htmlFor="mp-from">{t('settingsDefaultFrom')}</Label>
                <Input
                  id="mp-from"
                  placeholder={t('settingsDefaultFrom')}
                  value={form.default_from}
                  onChange={(e) => setForm({ ...form, default_from: e.target.value })}
                  dir="ltr"
                  className="font-mono"
                />
              </div>

              <Button disabled={saving} onClick={() => void save()}>
                {tNav('common.save')}
              </Button>
            </>
          )}
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
