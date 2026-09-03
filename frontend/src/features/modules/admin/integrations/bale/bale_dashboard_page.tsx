'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Loader2, RefreshCw, Send } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PermissionGate } from '@/components/auth/PermissionGate';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  baleCreateCampaign,
  baleGetSettings,
  baleGetUserLogs,
  baleListCampaigns,
  baleRunCampaign,
  baleSendBulk,
  baleUpdateSettings,
  type BaleCampaign,
  type BaleSettings,
} from '@/lib/api/bale';

export function BaleDashboardPage() {
  const t = useTranslations('bale');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const { layoutProps, setError, setSuccess } = useCrmFeedback();

  const [loading, setLoading] = useState(true);
  const [settings, setSettings] = useState<BaleSettings>({});
  const [stats, setStats] = useState<Record<string, number>>({});
  const [logs, setLogs] = useState<unknown[]>([]);
  const [campaigns, setCampaigns] = useState<BaleCampaign[]>([]);
  const [webhookUrl, setWebhookUrl] = useState('');
  const [userLogsChatId, setUserLogsChatId] = useState('');
  const [userLogs, setUserLogs] = useState<unknown[]>([]);
  const [bulkMessage, setBulkMessage] = useState('');
  const [campaignName, setCampaignName] = useState('');
  const [campaignMessage, setCampaignMessage] = useState('');
  const [savingSettings, setSavingSettings] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [s, st, l, c, w] = await Promise.all([
        baleGetSettings(),
        apiClient.get('webinocrm/v1/bale/stats').then((r) => unwrapData<Record<string, number>>(r)),
        apiClient.get('webinocrm/v1/bale/logs', { params: { limit: 25 } }).then((r) => {
          const body = unwrapData<{ logs?: unknown[] }>(r);
          return Array.isArray(body.logs) ? body.logs : [];
        }),
        baleListCampaigns(),
        apiClient.get('webinocrm/v1/bale/webhook-url').then((r) => {
          const body = unwrapData<{ url?: string }>(r);
          return typeof body.url === 'string' ? body.url : '';
        }),
      ]);
      setSettings(s);
      setStats(st);
      setLogs(l);
      setCampaigns(c);
      setWebhookUrl(w);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [setError]);

  useEffect(() => {
    void load();
  }, [load]);

  const saveSettings = async () => {
    setSavingSettings(true);
    setError(null);
    try {
      const updated = await baleUpdateSettings(settings);
      setSettings(updated);
      setSuccess(tCommon('saved'));
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSavingSettings(false);
    }
  };

  const loadUserLogs = async () => {
    if (!userLogsChatId.trim()) {
      return;
    }
    try {
      const rows = await baleGetUserLogs(userLogsChatId.trim());
      setUserLogs(rows);
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const createCampaign = async () => {
    try {
      await baleCreateCampaign({ name: campaignName, message: campaignMessage });
      setCampaignName('');
      setCampaignMessage('');
      setSuccess(t('campaignCreated'));
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const runCampaign = async (id: number) => {
    try {
      await baleRunCampaign(id);
      setSuccess(t('campaignRun'));
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const sendBulk = async () => {
    try {
      await baleSendBulk(bulkMessage);
      setBulkMessage('');
      setSuccess(t('bulkSent'));
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const tokenPreview = settings.bot_token ? `${String(settings.bot_token).slice(0, 6)}…` : '—';

  return (
    <CrmPageLayout
      title={tNav('nav.erp.sales.bale')}
      {...layoutProps}
      actions={
        <Button type="button" variant="outline" size="sm" onClick={() => void load()} disabled={loading}>
          {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
          {tCommon('refresh')}
        </Button>
      }
    >
      <PermissionGate permission="integrations.bale.manage">
        <Tabs defaultValue="settings" className="space-y-4">
          <TabsList className="flex h-auto flex-wrap gap-1">
            <TabsTrigger value="settings">{t('tabs.settings')}</TabsTrigger>
            <TabsTrigger value="campaigns">{t('tabs.campaigns')}</TabsTrigger>
            <TabsTrigger value="logs">{t('tabs.logs')}</TabsTrigger>
            <TabsTrigger value="bulk">{t('tabs.bulk')}</TabsTrigger>
            <TabsTrigger value="diagnostics">{t('tabs.diagnostics')}</TabsTrigger>
          </TabsList>

          <TabsContent value="settings" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('settingsTitle')}</CardTitle>
              </CardHeader>
              <CardContent className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label>{t('botToken')}</Label>
                  <Input
                    dir="ltr"
                    value={String(settings.bot_token ?? '')}
                    onChange={(e) => setSettings((s) => ({ ...s, bot_token: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('webhookSecret')}</Label>
                  <Input
                    dir="ltr"
                    type="password"
                    value={String(settings.webhook_secret ?? '')}
                    onChange={(e) => setSettings((s) => ({ ...s, webhook_secret: e.target.value }))}
                  />
                </div>
                <div className="space-y-2 sm:col-span-2">
                  <Label>{t('welcomeText')}</Label>
                  <Textarea
                    rows={3}
                    value={String(settings.welcome_text ?? '')}
                    onChange={(e) => setSettings((s) => ({ ...s, welcome_text: e.target.value }))}
                  />
                </div>
                <div className="space-y-2 sm:col-span-2">
                  <Label>{t('startHint')}</Label>
                  <Textarea
                    rows={2}
                    value={String(settings.start_hint_text ?? '')}
                    onChange={(e) => setSettings((s) => ({ ...s, start_hint_text: e.target.value }))}
                  />
                </div>
                <div className="sm:col-span-2">
                  <Button disabled={savingSettings} onClick={() => void saveSettings()}>
                    {tCommon('save')}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="campaigns">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('newCampaign')}</CardTitle>
              </CardHeader>
              <CardContent className="grid gap-3 sm:grid-cols-2">
                <Input placeholder={t('campaignName')} value={campaignName} onChange={(e) => setCampaignName(e.target.value)} />
                <Textarea placeholder={t('campaignMessage')} value={campaignMessage} onChange={(e) => setCampaignMessage(e.target.value)} />
                <Button className="sm:col-span-2 w-fit" onClick={() => void createCampaign()}>
                  {t('createCampaign')}
                </Button>
              </CardContent>
            </Card>
            <Card className="mt-4">
              <CardContent className="p-0 pt-6">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('campaignName')}</TableHead>
                      <TableHead>{t('status')}</TableHead>
                      <TableHead className="w-[120px]">{tCommon('actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {campaigns.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={3} className="text-center text-muted-foreground">
                          {t('noCampaigns')}
                        </TableCell>
                      </TableRow>
                    ) : (
                      campaigns.map((c) => (
                        <TableRow key={c.id}>
                          <TableCell>{c.name ?? c.id}</TableCell>
                          <TableCell>{String(c.status ?? '—')}</TableCell>
                          <TableCell>
                            <Button size="sm" variant="outline" onClick={() => void runCampaign(Number(c.id))}>
                              {t('runCampaign')}
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="logs" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('userLogs')}</CardTitle>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-2">
                <Input
                  dir="ltr"
                  placeholder={t('chatId')}
                  value={userLogsChatId}
                  onChange={(e) => setUserLogsChatId(e.target.value)}
                  className="max-w-xs"
                />
                <Button variant="secondary" onClick={() => void loadUserLogs()}>
                  {t('loadUserLogs')}
                </Button>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('systemLogs')}</CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="max-h-64 space-y-2 overflow-y-auto text-xs">
                  {(userLogs.length > 0 ? userLogs : logs).map((row, i) => {
                    const r = row as Record<string, unknown>;
                    return (
                      <li key={String(r.id ?? i)} className="rounded border bg-muted/30 px-2 py-1.5">
                        <span className="font-mono text-muted-foreground">{String(r.log_type ?? r.level ?? '')}</span>
                        <span className="ms-2">{String(r.created_at ?? '')}</span>
                      </li>
                    );
                  })}
                </ul>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="bulk">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('bulkSend')}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <Textarea rows={4} value={bulkMessage} onChange={(e) => setBulkMessage(e.target.value)} />
                <Button onClick={() => void sendBulk()}>
                  <Send className="me-2 h-4 w-4" />
                  {t('sendBulk')}
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="diagnostics" className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t('connection')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                  <p>
                    <span className="text-muted-foreground">{t('botToken')}: </span>
                    <span dir="ltr" className="font-mono">
                      {tokenPreview}
                    </span>
                  </p>
                  <p className="break-all text-xs" dir="ltr">
                    {webhookUrl || '—'}
                  </p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t('stats')}</CardTitle>
                </CardHeader>
                <CardContent>
                  <ul className="space-y-1 text-sm">
                    {Object.entries(stats).map(([k, v]) => (
                      <li key={k} className="flex justify-between gap-2">
                        <span className="text-muted-foreground">{k}</span>
                        <span dir="ltr">{v}</span>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>
            </div>
          </TabsContent>
        </Tabs>
      </PermissionGate>
    </CrmPageLayout>
  );
}
