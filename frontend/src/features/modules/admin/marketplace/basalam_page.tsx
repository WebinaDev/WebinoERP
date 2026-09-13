'use client';

import { useCallback, useEffect, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  type BasalamConnection,
  disconnectBasalamConnection,
  getBasalamOAuthStatus,
  listBasalamConnections,
  saveBasalamOAuthConfig,
} from '@/lib/api/marketplace';

const DEFAULT_REDIRECT = 'https://webina.dev/api/basalam/oauth/callback';
const DEFAULT_CLIENT_ID = '2357';
const DEFAULT_SCOPES =
  'vendor.product.write vendor.product.read vendor.parcel.write vendor.parcel.read vendor.profile.read vendor.profile.write customer.profile.read customer.profile.write customer.order.read customer.order.write customer.chat.read customer.chat.write customer.wallet.read customer.wallet.write order-processing customer.identity.read';

function formatTs(value?: string) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString();
}

export function BasalamPage() {
  const t = useTranslations('marketplace.basalam');
  const { layoutProps, setError, setSuccess, applyAxiosError } = useCrmFeedback();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [disconnecting, setDisconnecting] = useState<string | null>(null);
  const [configured, setConfigured] = useState(false);
  const [connections, setConnections] = useState<BasalamConnection[]>([]);
  const [form, setForm] = useState({
    client_id: DEFAULT_CLIENT_ID,
    client_secret: '',
    redirect_uri: DEFAULT_REDIRECT,
    scopes: DEFAULT_SCOPES,
  });

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [status, connRes] = await Promise.all([
        getBasalamOAuthStatus(),
        listBasalamConnections().catch(() => ({ connections: [] as BasalamConnection[] })),
      ]);
      setConfigured(Boolean(status?.configured));
      setForm({
        client_id: status?.client_id || DEFAULT_CLIENT_ID,
        client_secret: '',
        redirect_uri: status?.redirect_uri || DEFAULT_REDIRECT,
        scopes: status?.scopes || DEFAULT_SCOPES,
      });
      setConnections(Array.isArray(connRes.connections) ? connRes.connections : []);
    } catch (err) {
      applyAxiosError(err, t('loadError'));
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, setError, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      const payload: Parameters<typeof saveBasalamOAuthConfig>[0] = {
        client_id: form.client_id.trim() || DEFAULT_CLIENT_ID,
        redirect_uri: form.redirect_uri.trim() || DEFAULT_REDIRECT,
        scopes: form.scopes.trim() || DEFAULT_SCOPES,
      };
      if (form.client_secret.trim() && form.client_secret.trim() !== '***') {
        payload.client_secret = form.client_secret.trim();
      }
      await saveBasalamOAuthConfig(payload);
      setSuccess(t('saved'));
      setForm((p) => ({ ...p, client_secret: '' }));
      void load();
    } catch (err) {
      applyAxiosError(err, t('saveError'));
    } finally {
      setSaving(false);
    }
  };

  const handleDisconnect = async (siteUrl: string) => {
    if (!siteUrl) return;
    setDisconnecting(siteUrl);
    setError(null);
    setSuccess(null);
    try {
      const res = await disconnectBasalamConnection(siteUrl);
      setSuccess(t('disconnected'));
      if (res?.connections) {
        setConnections(res.connections);
      } else {
        void load();
      }
    } catch (err) {
      applyAxiosError(err, t('disconnectError'));
    } finally {
      setDisconnecting(null);
    }
  };

  const statusLabel = (status?: string) => {
    if (status === 'connected') return t('statusConnected');
    if (status === 'disconnected') return t('statusDisconnected');
    return status || '—';
  };

  return (
    <CrmPageLayout title={t('title')} description={t('desc')} {...layoutProps}>
      <div className="grid gap-4">
        <Card>
          <CardHeader>
            <CardTitle>{t('oauthTitle')}</CardTitle>
            <CardDescription>{t('oauthDesc')}</CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="bg-muted/40 h-48 animate-pulse rounded-md" />
            ) : (
              <form onSubmit={handleSave} className="grid max-w-2xl gap-4">
                <p className="text-sm">{configured ? t('configured') : t('notConfigured')}</p>
                <div className="space-y-2">
                  <Label>{t('clientId')}</Label>
                  <Input
                    value={form.client_id}
                    onChange={(e) => setForm((p) => ({ ...p, client_id: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('clientSecret')}</Label>
                  <Input
                    type="password"
                    autoComplete="new-password"
                    placeholder={configured ? '***' : ''}
                    value={form.client_secret}
                    onChange={(e) => setForm((p) => ({ ...p, client_secret: e.target.value }))}
                  />
                  <p className="text-muted-foreground text-xs">{t('secretHint')}</p>
                </div>
                <div className="space-y-2">
                  <Label>{t('redirectUri')}</Label>
                  <Input
                    value={form.redirect_uri}
                    onChange={(e) => setForm((p) => ({ ...p, redirect_uri: e.target.value }))}
                  />
                  <p className="text-muted-foreground text-xs">{t('redirectHint')}</p>
                </div>
                <div className="space-y-2">
                  <Label>{t('scopes')}</Label>
                  <Textarea
                    rows={4}
                    className="font-mono text-xs"
                    value={form.scopes}
                    onChange={(e) => setForm((p) => ({ ...p, scopes: e.target.value }))}
                  />
                </div>
                <div>
                  <Button type="submit" disabled={saving}>
                    {saving ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
                    {t('save')}
                  </Button>
                </div>
              </form>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('connectionsTitle')}</CardTitle>
            <CardDescription>{t('connectionsDesc')}</CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="bg-muted/40 h-32 animate-pulse rounded-md" />
            ) : connections.length === 0 ? (
              <p className="text-muted-foreground text-sm">{t('connectionsEmpty')}</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('colSite')}</TableHead>
                    <TableHead>{t('colVendor')}</TableHead>
                    <TableHead>{t('colStatus')}</TableHead>
                    <TableHead>{t('colLastSeen')}</TableHead>
                    <TableHead className="w-[1%] whitespace-nowrap">{t('colActions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {connections.map((row) => {
                    const site = row.site_url || '';
                    const busy = disconnecting === site;
                    return (
                      <TableRow key={site || String(row.vendor_id)}>
                        <TableCell className="font-medium">{site || '—'}</TableCell>
                        <TableCell>{row.vendor_id ? String(row.vendor_id) : '—'}</TableCell>
                        <TableCell>{statusLabel(row.status)}</TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {formatTs(row.last_seen_at)}
                        </TableCell>
                        <TableCell>
                          {row.status === 'connected' ? (
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={busy || !site}
                              onClick={() => void handleDisconnect(site)}
                            >
                              {busy ? <Loader2 className="me-2 h-3.5 w-3.5 animate-spin" /> : null}
                              {t('disconnect')}
                            </Button>
                          ) : (
                            <span className="text-muted-foreground text-xs">—</span>
                          )}
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </CrmPageLayout>
  );
}
