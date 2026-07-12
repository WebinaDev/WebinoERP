'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type HostingSettings = {
  public_crm_url?: string;
  git_provider?: string;
  git_base_url?: string;
  git_pat_configured?: boolean;
  portainer_url?: string;
  portainer_api_token_configured?: boolean;
  portainer_tls_fingerprint?: string;
  portainer_endpoint_id?: number | null;
  git_webhook_secret_configured?: boolean;
  license_hmac_configured?: boolean;
  webinoserver_panel_url?: string;
  webinoserver_api_token_configured?: boolean;
  platform_base_domain?: string;
  default_product_channel?: string;
  provision_webhook_secret_configured?: boolean;
};

type GitSourceRow = {
  id?: number;
  slug?: string;
  clone_url?: string;
  auth_type?: string;
  credential_ref?: string | null;
};

export function HostingInfrastructureTab() {
  const t = useTranslations('hosting');
  const [settings, setSettings] = useState<HostingSettings | null>(null);
  const [sources, setSources] = useState<GitSourceRow[]>([]);
  const [stacks, setStacks] = useState<unknown[] | null>(null);
  const [endpoints, setEndpoints] = useState<unknown[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState<string | null>(null);

  const [publicCrmUrl, setPublicCrmUrl] = useState('');
  const [gitProvider, setGitProvider] = useState('gitea');
  const [gitBaseUrl, setGitBaseUrl] = useState('');
  const [gitPat, setGitPat] = useState('');
  const [portainerUrl, setPortainerUrl] = useState('');
  const [portainerToken, setPortainerToken] = useState('');
  const [portainerFingerprint, setPortainerFingerprint] = useState('');
  const [portainerEndpointId, setPortainerEndpointId] = useState('');
  const [webhookSecret, setWebhookSecret] = useState('');
  const [webinoserverPanelUrl, setWebinoserverPanelUrl] = useState('');
  const [webinoserverToken, setWebinoserverToken] = useState('');
  const [platformBaseDomain, setPlatformBaseDomain] = useState('');
  const [defaultProductChannel, setDefaultProductChannel] = useState('LTS');
  const [provisionWebhookSecret, setProvisionWebhookSecret] = useState('');

  const [newSlug, setNewSlug] = useState('');
  const [newCloneUrl, setNewCloneUrl] = useState('');
  const [editingSourceId, setEditingSourceId] = useState<number | null>(null);
  const [editCloneUrl, setEditCloneUrl] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [sRes, gRes] = await Promise.all([
        apiClient.get('webinocrm/v1/hosting/settings'),
        apiClient.get('webinocrm/v1/hosting/module-git-sources'),
      ]);
      const s = unwrapData<HostingSettings>(sRes);
      setSettings(s);
      setPublicCrmUrl(String(s.public_crm_url ?? ''));
      setGitProvider(String(s.git_provider ?? 'gitea'));
      setGitBaseUrl(String(s.git_base_url ?? ''));
      setPortainerUrl(String(s.portainer_url ?? ''));
      setPortainerFingerprint(String(s.portainer_tls_fingerprint ?? ''));
      setPortainerEndpointId(s.portainer_endpoint_id != null ? String(s.portainer_endpoint_id) : '');
      setWebinoserverPanelUrl(String(s.webinoserver_panel_url ?? ''));
      setPlatformBaseDomain(String(s.platform_base_domain ?? ''));
      setDefaultProductChannel(String(s.default_product_channel ?? 'LTS'));
      setSources((unwrapData<GitSourceRow[]>(gRes) as GitSourceRow[]) ?? []);
      setStacks(null);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function saveSettings() {
    setMsg(null);
    setError(null);
    try {
      const payload: Record<string, string | number | null> = {
        public_crm_url: publicCrmUrl || '',
        git_provider: gitProvider || '',
        git_base_url: gitBaseUrl || '',
        portainer_url: portainerUrl || '',
        portainer_tls_fingerprint: portainerFingerprint || '',
        webinoserver_panel_url: webinoserverPanelUrl || '',
        platform_base_domain: platformBaseDomain || '',
        default_product_channel: defaultProductChannel || 'LTS',
      };
      const pe = portainerEndpointId.trim();
      payload.portainer_endpoint_id = pe === '' ? null : Number(pe);
      if (gitPat.trim()) {
        payload.git_pat = gitPat;
      }
      if (portainerToken.trim()) {
        payload.portainer_api_token = portainerToken;
      }
      if (webhookSecret.trim()) {
        payload.git_webhook_secret = webhookSecret;
      }
      if (webinoserverToken.trim()) {
        payload.webinoserver_api_token = webinoserverToken;
      }
      if (provisionWebhookSecret.trim()) {
        payload.provision_webhook_secret = provisionWebhookSecret;
      }
      await apiClient.put('webinocrm/v1/hosting/settings', payload);
      setGitPat('');
      setPortainerToken('');
      setWebhookSecret('');
      setWebinoserverToken('');
      setProvisionWebhookSecret('');
      setMsg(t('saved'));
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function loadEndpoints() {
    setError(null);
    try {
      const res = await apiClient.get('webinocrm/v1/hosting/portainer/endpoints');
      setEndpoints((unwrapData<unknown[]>(res) as unknown[]) ?? []);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function loadStacks() {
    setError(null);
    try {
      const q =
        portainerEndpointId.trim() !== ''
          ? { params: { endpoint_id: Number(portainerEndpointId) } }
          : {};
      const res = await apiClient.get('webinocrm/v1/hosting/portainer/stacks', q);
      setStacks((unwrapData<unknown[]>(res) as unknown[]) ?? []);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function stackAct(stackId: number, action: 'start' | 'stop') {
    setError(null);
    const pe = portainerEndpointId.trim();
    if (!pe) {
      setError(t('endpointIdRequired'));
      return;
    }
    try {
      await apiClient.post(`webinocrm/v1/hosting/portainer/stacks/${stackId}/${action}`, {
        endpoint_id: Number(pe),
      });
      setMsg(t('stackQueued', { action }));
      void loadStacks();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function addSource() {
    setError(null);
    try {
      await apiClient.post('webinocrm/v1/hosting/module-git-sources', {
        slug: newSlug.trim(),
        clone_url: newCloneUrl.trim(),
        auth_type: 'none',
      });
      setNewSlug('');
      setNewCloneUrl('');
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function saveSourceCloneUrl(id: number) {
    setError(null);
    try {
      await apiClient.patch(`webinocrm/v1/hosting/module-git-sources/${id}`, {
        clone_url: editCloneUrl.trim(),
      });
      setEditingSourceId(null);
      setEditCloneUrl('');
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function deleteSource(id: number) {
    setError(null);
    try {
      await apiClient.delete(`webinocrm/v1/hosting/module-git-sources/${id}`);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  if (loading && !settings) {
    return <p className="text-sm text-muted-foreground">{t('loading')}</p>;
  }

  return (
    <div className="space-y-6">
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {msg ? <p className="text-sm text-green-600">{msg}</p> : null}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('settingsTitle')}</CardTitle>
          <CardDescription>{t('settingsDescription')}</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <p className="mb-1 text-xs text-muted-foreground">{t('publicCrmUrl')}</p>
            <Input value={publicCrmUrl} onChange={(e) => setPublicCrmUrl(e.target.value)} dir="ltr" className="font-mono text-xs" />
          </div>
          <div>
            <p className="mb-1 text-xs text-muted-foreground">{t('gitProvider')}</p>
            <Input value={gitProvider} onChange={(e) => setGitProvider(e.target.value)} dir="ltr" />
          </div>
          <div>
            <p className="mb-1 text-xs text-muted-foreground">{t('gitBaseUrl')}</p>
            <Input value={gitBaseUrl} onChange={(e) => setGitBaseUrl(e.target.value)} dir="ltr" className="font-mono text-xs" />
          </div>
          <div className="sm:col-span-2">
            <p className="mb-1 text-xs text-muted-foreground">{t('gitPat')}</p>
            <Input
              type="password"
              value={gitPat}
              onChange={(e) => setGitPat(e.target.value)}
              placeholder={settings?.git_pat_configured ? t('gitPatConfigured') : ''}
              dir="ltr"
              className="font-mono text-xs"
              autoComplete="new-password"
            />
          </div>
          <div className="sm:col-span-2">
            <p className="mb-1 text-xs text-muted-foreground">{t('portainerUrl')}</p>
            <Input value={portainerUrl} onChange={(e) => setPortainerUrl(e.target.value)} dir="ltr" className="font-mono text-xs" />
          </div>
          <div className="sm:col-span-2">
            <p className="mb-1 text-xs text-muted-foreground">{t('portainerToken')}</p>
            <Input
              type="password"
              value={portainerToken}
              onChange={(e) => setPortainerToken(e.target.value)}
              placeholder={settings?.portainer_api_token_configured ? t('portainerTokenConfigured') : ''}
              dir="ltr"
              className="font-mono text-xs"
              autoComplete="new-password"
            />
          </div>
          <div>
            <p className="mb-1 text-xs text-muted-foreground">{t('tlsFingerprint')}</p>
            <Input value={portainerFingerprint} onChange={(e) => setPortainerFingerprint(e.target.value)} dir="ltr" className="font-mono text-xs" />
          </div>
          <div>
            <p className="mb-1 text-xs text-muted-foreground">{t('endpointId')}</p>
            <Input value={portainerEndpointId} onChange={(e) => setPortainerEndpointId(e.target.value)} dir="ltr" className="font-mono text-xs" />
          </div>
          <div className="sm:col-span-2 border-t pt-3 mt-1">
            <p className="mb-2 text-sm font-medium">{t('webinoserverPanelUrl')}</p>
          </div>
          <div className="sm:col-span-2">
            <Input value={webinoserverPanelUrl} onChange={(e) => setWebinoserverPanelUrl(e.target.value)} dir="ltr" className="font-mono text-xs" placeholder="https://panel.example.com:2090" />
          </div>
          <div>
            <p className="mb-1 text-xs text-muted-foreground">{t('webinoserverApiToken')}</p>
            <Input
              type="password"
              value={webinoserverToken}
              onChange={(e) => setWebinoserverToken(e.target.value)}
              placeholder={settings?.webinoserver_api_token_configured ? t('portainerTokenConfigured') : ''}
              dir="ltr"
              className="font-mono text-xs"
              autoComplete="new-password"
            />
          </div>
          <div>
            <p className="mb-1 text-xs text-muted-foreground">{t('platformBaseDomain')}</p>
            <Input value={platformBaseDomain} onChange={(e) => setPlatformBaseDomain(e.target.value)} dir="ltr" className="font-mono text-xs" placeholder="webina.ir" />
          </div>
          <div>
            <p className="mb-1 text-xs text-muted-foreground">{t('defaultProductChannel')}</p>
            <Input value={defaultProductChannel} onChange={(e) => setDefaultProductChannel(e.target.value)} dir="ltr" className="font-mono text-xs" />
          </div>
          <div className="sm:col-span-2">
            <p className="mb-1 text-xs text-muted-foreground">{t('provisionWebhookSecret')}</p>
            <Input
              type="password"
              value={provisionWebhookSecret}
              onChange={(e) => setProvisionWebhookSecret(e.target.value)}
              placeholder={settings?.provision_webhook_secret_configured ? t('webhookConfigured') : ''}
              dir="ltr"
              className="font-mono text-xs"
              autoComplete="new-password"
            />
          </div>
          <div className="sm:col-span-2">
            <p className="mb-1 text-xs text-muted-foreground">{t('webhookSecret')}</p>
            <Input
              type="password"
              value={webhookSecret}
              onChange={(e) => setWebhookSecret(e.target.value)}
              placeholder={settings?.git_webhook_secret_configured ? t('webhookConfigured') : ''}
              dir="ltr"
              className="font-mono text-xs"
              autoComplete="new-password"
            />
          </div>
          <p className="text-xs text-muted-foreground sm:col-span-2">
            {t('licenseHmac')}: {settings?.license_hmac_configured ? t('licenseHmacActive') : t('licenseHmacInactive')}
          </p>
          <div className="flex flex-wrap gap-2 sm:col-span-2">
            <Button type="button" size="sm" onClick={() => void saveSettings()}>
              {t('saveSettings')}
            </Button>
            {portainerUrl ? (
              <Button type="button" size="sm" variant="outline" asChild>
                <a href={portainerUrl} target="_blank" rel="noopener noreferrer">
                  {t('openPortainer')}
                </a>
              </Button>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('stacksTitle')}</CardTitle>
          <CardDescription>{t('stacksDescription')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="secondary" onClick={() => void loadEndpoints()}>
              {t('loadEndpoints')}
            </Button>
            <Button type="button" size="sm" variant="secondary" onClick={() => void loadStacks()}>
              {t('loadStacks')}
            </Button>
          </div>
          {endpoints && endpoints.length > 0 ? (
            <div className="overflow-x-auto rounded border text-xs">
              <table className="w-full">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start">Id</th>
                    <th className="px-2 py-2 text-start">Name</th>
                  </tr>
                </thead>
                <tbody>
                  {endpoints.map((row) => {
                    const r = row as Record<string, unknown>;
                    const id = Number(r.Id ?? r.id ?? 0);
                    const name = String(r.Name ?? r.name ?? '');
                    return (
                      <tr key={id} className="border-b">
                        <td className="px-2 py-2 font-mono">{id}</td>
                        <td className="px-2 py-2">{name}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : null}
          {stacks && stacks.length === 0 ? <p className="text-xs text-muted-foreground">{t('noStacks')}</p> : null}
          {stacks && stacks.length > 0 ? (
            <div className="overflow-x-auto rounded border text-xs">
              <table className="w-full">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start">Id</th>
                    <th className="px-2 py-2 text-start">Name</th>
                    <th className="px-2 py-2 text-start"> </th>
                  </tr>
                </thead>
                <tbody>
                  {stacks.map((row) => {
                    const r = row as Record<string, unknown>;
                    const id = Number(r.Id ?? r.id ?? 0);
                    const name = String(r.Name ?? r.name ?? '');
                    return (
                      <tr key={id} className="border-b">
                        <td className="px-2 py-2 font-mono">{id}</td>
                        <td className="px-2 py-2">{name}</td>
                        <td className="px-2 py-2 space-x-1 whitespace-nowrap">
                          <Button type="button" variant="outline" size="sm" onClick={() => void stackAct(id, 'start')}>
                            start
                          </Button>
                          <Button type="button" variant="outline" size="sm" onClick={() => void stackAct(id, 'stop')}>
                            stop
                          </Button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('gitSourcesTitle')}</CardTitle>
          <CardDescription>{t('gitSourcesDescription')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex flex-wrap gap-2 items-end">
            <div>
              <p className="mb-1 text-xs text-muted-foreground">{t('slug')}</p>
              <Input value={newSlug} onChange={(e) => setNewSlug(e.target.value)} dir="ltr" className="w-40 font-mono text-xs" />
            </div>
            <div className="flex-1 min-w-[200px]">
              <p className="mb-1 text-xs text-muted-foreground">{t('cloneUrl')}</p>
              <Input value={newCloneUrl} onChange={(e) => setNewCloneUrl(e.target.value)} dir="ltr" className="font-mono text-xs" />
            </div>
            <Button type="button" size="sm" onClick={() => void addSource()}>
              {t('add')}
            </Button>
          </div>
          <ul className="space-y-2">
            {sources.map((s) => (
              <li key={String(s.id)} className="flex flex-wrap items-center justify-between gap-2 rounded border px-2 py-2">
                <span className="font-mono text-xs">{String(s.slug)}</span>
                {editingSourceId === s.id ? (
                  <div className="flex flex-1 flex-wrap items-center gap-2 min-w-[200px]">
                    <Input
                      value={editCloneUrl}
                      onChange={(e) => setEditCloneUrl(e.target.value)}
                      dir="ltr"
                      className="font-mono text-xs flex-1"
                    />
                    <Button type="button" size="sm" onClick={() => s.id && void saveSourceCloneUrl(s.id)}>
                      {t('save')}
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditingSourceId(null)}>
                      {t('cancel')}
                    </Button>
                  </div>
                ) : (
                  <span className="font-mono text-[11px] text-muted-foreground truncate max-w-[420px]" dir="ltr">
                    {String(s.clone_url ?? '')}
                  </span>
                )}
                <div className="flex gap-1">
                  {editingSourceId !== s.id && s.id ? (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setEditingSourceId(s.id ?? null);
                        setEditCloneUrl(String(s.clone_url ?? ''));
                      }}
                    >
                      {t('editCloneUrl')}
                    </Button>
                  ) : null}
                  <Button type="button" variant="ghost" size="sm" className="text-destructive" onClick={() => s.id && void deleteSource(s.id)}>
                    {t('delete')}
                  </Button>
                </div>
              </li>
            ))}
          </ul>
          <p className="text-xs text-muted-foreground">{t('webhookHint')}</p>
          <p className="text-xs text-muted-foreground">{t('hmacHint')}</p>
        </CardContent>
      </Card>
    </div>
  );
}
