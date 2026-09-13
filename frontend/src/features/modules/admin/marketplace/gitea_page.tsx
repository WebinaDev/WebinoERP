'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Loader2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  getGiteaSettings,
  saveGiteaSettings,
  testGiteaConnectionDiagnostics,
  type GiteaConnectionDiagnosticsResult,
  type OrgGitProvider,
} from '@/lib/api/marketplace';
import { GiteaConnectionDiagnostics } from './GiteaConnectionDiagnostics';

export function GiteaPage() {
  const t = useTranslations('marketplace.gitea');
  const tNav = useTranslations();
  const { layoutProps, setError, setSuccess, applyAxiosError } = useCrmFeedback();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [form, setForm] = useState({
    provider: 'gitea' as OrgGitProvider,
    base_url: 'https://package.webina.dev',
    org: 'webina',
    token: '',
  });
  const [hasToken, setHasToken] = useState(false);
  const [diagnostics, setDiagnostics] = useState<GiteaConnectionDiagnosticsResult | null>(null);
  const autoTestDone = useRef(false);

  const runTest = useCallback(
    async (silent = false) => {
      setTesting(true);
      if (!silent) {
        setError(null);
        setSuccess(null);
      }
      try {
        const data = await testGiteaConnectionDiagnostics({
          provider: form.provider,
          base_url: form.base_url.trim(),
          org: form.org.trim(),
          token: form.token.trim() || undefined,
        });
        setDiagnostics(data);
        if (!silent) {
          if (data.ok) {
            setSuccess(data.user ? t('testOkUser', { user: data.user }) : t('testOk'));
          } else {
            setError(data.message?.trim() || t('testFail'));
          }
        }
        return data;
      } catch (err) {
        if (!silent) applyAxiosError(err, t('testFail'));
        return null;
      } finally {
        setTesting(false);
      }
    },
    [applyAxiosError, form, setError, setSuccess, t],
  );

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getGiteaSettings();
      const s = res.settings;
      setForm((p) => ({
        ...p,
        provider: (s.provider as OrgGitProvider) || 'gitea',
        base_url: s.base_url || s.gitea_base_url || p.base_url,
        org: s.org || s.gitea_org || p.org,
        token: '',
      }));
      setHasToken(Boolean(s.has_token));
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, setError, t]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    if (loading || autoTestDone.current || !hasToken) return;
    autoTestDone.current = true;
    void runTest(true);
  }, [hasToken, loading, runTest]);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      await saveGiteaSettings({
        provider: form.provider,
        base_url: form.base_url.trim(),
        org: form.org.trim(),
        token: form.token.trim() || undefined,
      });
      setSuccess(t('saved'));
      setForm((p) => ({ ...p, token: '' }));
      void load();
    } catch (err) {
      applyAxiosError(err, t('saveError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <CrmPageLayout title={t('title')} description={t('desc')} {...layoutProps}>
      <Card>
        <CardHeader>
          <CardTitle>{t('connection')}</CardTitle>
          <CardDescription>{t('connectionDesc')}</CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="bg-muted/40 h-48 animate-pulse rounded-md" />
          ) : (
            <form onSubmit={handleSave} className="grid max-w-xl gap-4">
              <div className="space-y-2">
                <Label>Provider</Label>
                <Select
                  value={form.provider}
                  onValueChange={(v) => setForm((p) => ({ ...p, provider: v as OrgGitProvider }))}
                >
                  <SelectTrigger className="w-full max-w-xs">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="gitea">Gitea</SelectItem>
                    <SelectItem value="github">GitHub</SelectItem>
                    <SelectItem value="gitlab">GitLab</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t('baseUrl')}</Label>
                <Input
                  value={form.base_url}
                  onChange={(e) => setForm((p) => ({ ...p, base_url: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t('org')}</Label>
                <Input
                  value={form.org}
                  onChange={(e) => setForm((p) => ({ ...p, org: e.target.value }))}
                />
                <p className="text-muted-foreground text-xs">{t('orgHint')}</p>
              </div>
              <div className="space-y-2">
                <Label>{t('apiToken')}</Label>
                <Input
                  type="password"
                  value={form.token}
                  onChange={(e) => setForm((p) => ({ ...p, token: e.target.value }))}
                  placeholder={hasToken ? t('tokenPlaceholder') : ''}
                />
              </div>
              <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={saving}>
                  {saving ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
                  {tNav('common.save')}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  disabled={testing}
                  onClick={() => void runTest(false)}
                >
                  {testing ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
                  {t('test')}
                </Button>
              </div>
            </form>
          )}
        </CardContent>
      </Card>

      {diagnostics || testing ? (
        <div className="mt-4">
          <GiteaConnectionDiagnostics result={diagnostics} loading={testing && !diagnostics} />
        </div>
      ) : null}
    </CrmPageLayout>
  );
}
