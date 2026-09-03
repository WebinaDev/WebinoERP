'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Loader2, RefreshCw, Webhook, ScrollText } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PermissionGate } from '@/components/auth/PermissionGate';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  baleCreateCampaign,
  baleDiagnosticsStats,
  baleFetchWebhookUrl,
  baleGetKpi,
  baleGetLogs,
  baleGetSettings,
  baleGetStats,
  baleGetUserLogs,
  baleListCampaigns,
  baleRunCampaign,
  baleSetWebhook,
  baleTestLog,
  baleUpdateSettings,
  baleWebhookInfo,
  type BaleBotStats,
  type BaleCampaign,
  type BaleKpi,
  type BaleLogRow,
  type BaleSettings,
} from '@/lib/api/bale';

const SEGMENTS = ['newcomer', 'hot-leads', 'past-buyers', 'inactive-30d'] as const;

function FlagSelect({
  id,
  label,
  value,
  onChange,
  activeLabel,
  inactiveLabel,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (v: string) => void;
  activeLabel: string;
  inactiveLabel: string;
}) {
  return (
    <div className="grid gap-2">
      <Label htmlFor={id}>{label}</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger id={id}>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="1">{activeLabel}</SelectItem>
          <SelectItem value="0">{inactiveLabel}</SelectItem>
        </SelectContent>
      </Select>
    </div>
  );
}

export function BaleDashboardPage() {
  const t = useTranslations('bale');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const { layoutProps, setError, setSuccess } = useCrmFeedback();

  const [loading, setLoading] = useState(true);
  const [settings, setSettings] = useState<BaleSettings>({});
  const [webhookUrl, setWebhookUrl] = useState('');
  const [webhookNote, setWebhookNote] = useState('');
  const [webhookInfoJson, setWebhookInfoJson] = useState('');
  const [logs, setLogs] = useState<BaleLogRow[]>([]);
  const [stats, setStats] = useState<{ support_opened: number; support_item_clicked: number } | null>(null);
  const [botStats, setBotStats] = useState<BaleBotStats | null>(null);
  const [kpi, setKpi] = useState<BaleKpi | null>(null);
  const [campaigns, setCampaigns] = useState<BaleCampaign[]>([]);
  const [savingSettings, setSavingSettings] = useState(false);
  const [diagLoading, setDiagLoading] = useState(false);
  const [userLogsChatId, setUserLogsChatId] = useState('');
  const [userLogsJson, setUserLogsJson] = useState('');
  const [campaignName, setCampaignName] = useState('');
  const [campaignSegment, setCampaignSegment] = useState<string>('newcomer');
  const [campaignVariant, setCampaignVariant] = useState('A');
  const [campaignMessage, setCampaignMessage] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [s, wh, l, st, bs, k, c] = await Promise.all([
        baleGetSettings(),
        baleFetchWebhookUrl(),
        baleGetLogs(80),
        baleDiagnosticsStats().catch(() => null),
        baleGetStats().catch(() => null),
        baleGetKpi().catch(() => null),
        baleListCampaigns().catch(() => [] as BaleCampaign[]),
      ]);
      setSettings(s);
      setWebhookUrl(wh.url);
      setWebhookNote(wh.message ?? '');
      setLogs(l);
      setStats(st);
      setBotStats(bs);
      setKpi(k);
      setCampaigns(c);
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

  const runSetWebhook = async () => {
    setDiagLoading(true);
    setError(null);
    try {
      const res = await baleSetWebhook();
      setWebhookInfoJson(JSON.stringify(res, null, 2));
      setSuccess(t('webhookSet'));
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setDiagLoading(false);
    }
  };

  const runWebhookInfo = async () => {
    setDiagLoading(true);
    setError(null);
    try {
      const info = await baleWebhookInfo();
      setWebhookInfoJson(JSON.stringify(info, null, 2));
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setDiagLoading(false);
    }
  };

  const runTestLog = async () => {
    setDiagLoading(true);
    try {
      await baleTestLog();
      setSuccess(t('testLogOk'));
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setDiagLoading(false);
    }
  };

  const loadUserLogs = async () => {
    if (!userLogsChatId.trim()) return;
    try {
      const rows = await baleGetUserLogs(userLogsChatId.trim());
      setUserLogsJson(JSON.stringify(rows, null, 2));
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const createCampaign = async () => {
    if (!campaignName.trim() || !campaignMessage.trim()) return;
    try {
      const id = await baleCreateCampaign({
        name: campaignName.trim(),
        message_template: campaignMessage.trim(),
        segment_key: campaignSegment,
        variant: campaignVariant,
      });
      if (id) {
        await baleRunCampaign(id);
      }
      setCampaignName('');
      setCampaignMessage('');
      setSuccess(t('campaignCreated'));
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  };

  const onChange = (key: string, value: string | number) => {
    setSettings((prev) => ({ ...prev, [key]: value }));
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.sales.bale')}
      description={t('pageDesc')}
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
          <TabsList className="grid h-auto w-full max-w-xl grid-cols-4">
            <TabsTrigger value="settings">{t('tabs.settings')}</TabsTrigger>
            <TabsTrigger value="webhook">{t('tabs.webhook')}</TabsTrigger>
            <TabsTrigger value="logs">{t('tabs.logs')}</TabsTrigger>
            <TabsTrigger value="funnel">{t('tabs.funnel')}</TabsTrigger>
          </TabsList>

          <TabsContent value="settings" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('settingsTitle')}</CardTitle>
              </CardHeader>
              <CardContent className="grid gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="bot_token">{t('botToken')}</Label>
                  <Input
                    id="bot_token"
                    dir="ltr"
                    autoComplete="off"
                    value={String(settings.bot_token ?? '')}
                    onChange={(e) => onChange('bot_token', e.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="provider_token">{t('providerToken')}</Label>
                  <Input
                    id="provider_token"
                    dir="ltr"
                    autoComplete="off"
                    value={String(settings.provider_token ?? '')}
                    onChange={(e) => onChange('provider_token', e.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="webhook_secret">{t('webhookSecret')}</Label>
                  <Input
                    id="webhook_secret"
                    dir="ltr"
                    type="password"
                    autoComplete="off"
                    value={String(settings.webhook_secret ?? '')}
                    onChange={(e) => onChange('webhook_secret', e.target.value)}
                  />
                </div>
                <FlagSelect
                  id="enable_auto_register_user"
                  label={t('autoRegisterUser')}
                  value={String(settings.enable_auto_register_user ?? '1')}
                  onChange={(v) => onChange('enable_auto_register_user', v)}
                  activeLabel={tCommon('active')}
                  inactiveLabel={tCommon('inactive')}
                />
                <FlagSelect
                  id="enable_auto_lead_from_business"
                  label={t('autoLeadFromBusiness')}
                  value={String(settings.enable_auto_lead_from_business ?? '1')}
                  onChange={(v) => onChange('enable_auto_lead_from_business', v)}
                  activeLabel={tCommon('active')}
                  inactiveLabel={tCommon('inactive')}
                />
                <div className="grid gap-2">
                  <Label htmlFor="channel_id">{t('channelId')}</Label>
                  <Input
                    id="channel_id"
                    dir="ltr"
                    value={String(settings.channel_id ?? '')}
                    onChange={(e) => onChange('channel_id', e.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="welcome_text">{t('welcomeText')}</Label>
                  <Textarea
                    id="welcome_text"
                    rows={3}
                    value={String(settings.welcome_text ?? settings.start_hint_text ?? '')}
                    onChange={(e) => onChange('welcome_text', e.target.value)}
                  />
                </div>
                <div>
                  <Button disabled={savingSettings} onClick={() => void saveSettings()}>
                    {tCommon('save')}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="webhook" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <Webhook className="h-5 w-5" />
                  {t('webhookUrl')}
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {webhookNote ? <p className="text-sm text-muted-foreground">{webhookNote}</p> : null}
                <code className="block break-all rounded-md bg-muted p-3 text-sm" dir="ltr">
                  {webhookUrl || '—'}
                </code>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" disabled={diagLoading} onClick={() => void runSetWebhook()}>
                    {t('setWebhook')}
                  </Button>
                  <Button type="button" variant="secondary" disabled={diagLoading} onClick={() => void runWebhookInfo()}>
                    {t('webhookInfo')}
                  </Button>
                  <Button type="button" variant="outline" disabled={diagLoading} onClick={() => void runTestLog()}>
                    {t('testLog')}
                  </Button>
                </div>
                {stats ? (
                  <div className="space-y-1 border-t pt-2 text-sm">
                    <p>{t('statsSupportOpened', { value: stats.support_opened })}</p>
                    <p>{t('statsSupportClicked', { value: stats.support_item_clicked })}</p>
                  </div>
                ) : null}
                {webhookInfoJson ? (
                  <pre className="max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs">{webhookInfoJson}</pre>
                ) : null}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('campaignsTitle')}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="grid gap-3 md:grid-cols-2">
                  <Input
                    placeholder={t('campaignName')}
                    value={campaignName}
                    onChange={(e) => setCampaignName(e.target.value)}
                  />
                  <Select value={campaignSegment} onValueChange={setCampaignSegment}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {SEGMENTS.map((s) => (
                        <SelectItem key={s} value={s}>
                          {t(`segments.${s}` as 'segments.newcomer')}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Select value={campaignVariant} onValueChange={setCampaignVariant}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="A">A</SelectItem>
                      <SelectItem value="B">B</SelectItem>
                    </SelectContent>
                  </Select>
                  <Textarea
                    placeholder={t('campaignMessage')}
                    value={campaignMessage}
                    onChange={(e) => setCampaignMessage(e.target.value)}
                  />
                </div>
                <Button type="button" className="w-fit" onClick={() => void createCampaign()}>
                  {t('createCampaign')}
                </Button>
                <ul className="divide-y rounded border text-sm">
                  {campaigns.length === 0 ? (
                    <li className="p-3 text-muted-foreground">{t('noCampaigns')}</li>
                  ) : (
                    campaigns.map((c) => (
                      <li key={String(c.id)} className="flex items-center justify-between gap-2 p-3">
                        <span>
                          {c.name ?? c.id} · {String(c.status ?? '—')} · {String(c.segment_key ?? '')}
                        </span>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => void baleRunCampaign(Number(c.id)).then(load).catch((e) => setError(getAxiosMessage(e)))}
                        >
                          {t('runCampaign')}
                        </Button>
                      </li>
                    ))
                  )}
                </ul>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="logs" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t('userLogs')}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex flex-wrap gap-2">
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
                </div>
                {userLogsJson ? (
                  <pre className="max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs">{userLogsJson}</pre>
                ) : null}
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                  <ScrollText className="h-5 w-5" />
                  {t('systemLogs')}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-16">#</TableHead>
                      <TableHead>{t('colTime')}</TableHead>
                      <TableHead>{t('colLevel')}</TableHead>
                      <TableHead>{t('colType')}</TableHead>
                      <TableHead>{t('colContext')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {logs.map((row) => (
                      <TableRow key={String(row.id)}>
                        <TableCell>{row.id}</TableCell>
                        <TableCell className="whitespace-nowrap">{String(row.created_at ?? '')}</TableCell>
                        <TableCell>{String(row.level ?? '')}</TableCell>
                        <TableCell>{String(row.log_type ?? '')}</TableCell>
                        <TableCell className="max-w-md truncate font-mono text-xs">
                          {typeof row.context === 'string' ? row.context : JSON.stringify(row.context ?? '')}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                {logs.length === 0 ? (
                  <p className="py-4 text-sm text-muted-foreground">{t('noLogs')}</p>
                ) : null}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="funnel" className="space-y-4">
            {kpi ? (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t('kpiTitle')}</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                  <div>{t('kpiStartLead', { value: kpi.start_to_lead_rate })}</div>
                  <div>{t('kpiLeadCustomer', { value: kpi.lead_to_customer_rate })}</div>
                  <div>{t('kpiFirstResponse', { value: kpi.first_response_minutes })}</div>
                  <div>{t('kpiRetention', { value: kpi.retention_rate })}</div>
                  <div>{t('kpiCampaignRevenue', { value: kpi.campaign_revenue_impact })}</div>
                  <div>{t('kpiDropoff', { value: JSON.stringify(kpi.funnel_dropoff) })}</div>
                </CardContent>
              </Card>
            ) : (
              <p className="text-sm text-muted-foreground">{t('kpiEmpty')}</p>
            )}

            {botStats ? (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t('botStatsTitle')}</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
                  <div>{t('statsStartedUsers', { value: botStats.started_users })}</div>
                  <div>{t('statsTotalUsers', { value: botStats.total_users })}</div>
                  <div>{t('statsTotalBusinesses', { value: botStats.total_businesses })}</div>
                  <div>{t('statsTotalEvents', { value: botStats.total_events })}</div>
                  <div>{t('statsTotalLogs', { value: botStats.total_logs })}</div>
                </CardContent>
              </Card>
            ) : null}

            {kpi?.campaign_metrics ? (
              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t('campaignMetrics')}</CardTitle>
                </CardHeader>
                <CardContent>
                  <ul className="space-y-1 text-sm">
                    {Object.entries(kpi.campaign_metrics).map(([k, v]) => (
                      <li key={k} className="flex justify-between gap-2">
                        <span className="text-muted-foreground">{k}</span>
                        <span dir="ltr">{v}</span>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>
            ) : null}
          </TabsContent>
        </Tabs>
      </PermissionGate>
    </CrmPageLayout>
  );
}
