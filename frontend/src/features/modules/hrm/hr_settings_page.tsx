'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { HrmEmployeeSelect } from '@/features/modules/hrm/hrm_employee_select';
import { HrmField, type HrmRow } from '@/features/modules/hrm/hrm_shared';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { getHrNotificationSettings, saveHrNotificationSettings, testHrNotification } from '@/lib/api/hrm';

const CHANNELS = ['in_app', 'sms', 'bale', 'telegram'] as const;
const EVENTS = ['leave_decision', 'shift_assigned', 'payslip_issued', 'payroll_paid', 'request_decision', 'missing_docs', 'offboarding'] as const;

export function HrSettingsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [data, setData] = useState<HrmRow | null>(null);
  const [enabled, setEnabled] = useState(false);
  const [channels, setChannels] = useState<Record<string, boolean>>({});
  const [events, setEvents] = useState<Record<string, boolean>>({});
  const [telegramToken, setTelegramToken] = useState('');
  const [baleToken, setBaleToken] = useState('');
  const [clearTelegram, setClearTelegram] = useState(false);
  const [clearBale, setClearBale] = useState(false);
  const [testEmployee, setTestEmployee] = useState('');
  const [testResults, setTestResults] = useState<Record<string, string> | null>(null);

  const apply = useCallback((res: HrmRow) => {
    setData(res);
    setEnabled(Boolean(res.enabled));
    setChannels({ ...((res.channels ?? {}) as Record<string, boolean>) });
    setEvents({ ...((res.events ?? {}) as Record<string, boolean>) });
  }, []);

  useEffect(() => {
    void getHrNotificationSettings().then(apply).catch(applyAxiosError);
  }, [apply, applyAxiosError]);

  const save = async () => {
    try {
      const res = await saveHrNotificationSettings({
        enabled,
        channels,
        events,
        telegram_bot_token: telegramToken || null,
        bale_bot_token: baleToken || null,
        clear_telegram_token: clearTelegram,
        clear_bale_token: clearBale,
      });
      apply(res);
      setTelegramToken('');
      setBaleToken('');
      setClearTelegram(false);
      setClearBale(false);
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const runTest = async () => {
    try {
      const res = await testHrNotification(Number(testEmployee));
      setTestResults(res?.results ?? {});
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const channelState = (c: string): string | null => {
    if (!data) return null;
    if (c === 'sms') return data.sms_configured ? t('notify.state.ready') : t('notify.state.smsMissing');
    if (c === 'telegram') return data.telegram_token_set || data.telegram_env_token ? t('notify.state.ready') : t('notify.state.tokenMissing');
    if (c === 'bale') return data.bale_token_set || data.bale_integration_token ? t('notify.state.ready') : t('notify.state.tokenMissing');
    return t('notify.state.ready');
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.hrSettings')} description={t('notify.hint')} {...layoutProps}>
      {!data ? <PageLoadingState /> : (
        <div className="space-y-4">
          <Card>
            <CardHeader><CardTitle className="text-base">{t('notify.sections.general')}</CardTitle></CardHeader>
            <CardContent>
              <label className="flex items-center gap-3 text-sm">
                <Switch checked={enabled} onCheckedChange={setEnabled} />
                {t('notify.enabled')}
              </label>
              <p className="mt-2 text-xs text-muted-foreground">{t('notify.enabledHint')}</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">{t('notify.sections.channels')}</CardTitle></CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
              {CHANNELS.map((c) => (
                <div key={c} className="flex items-center justify-between gap-3 rounded border p-3">
                  <div>
                    <div className="font-medium">{t(`notify.channels.${c}`)}</div>
                    <div className="text-xs text-muted-foreground">{t(`notify.channelHints.${c}`)}</div>
                    <Badge variant="outline" className="mt-1">{channelState(c)}</Badge>
                  </div>
                  <Switch checked={Boolean(channels[c])} onCheckedChange={(v) => setChannels((s) => ({ ...s, [c]: v }))} />
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">{t('notify.sections.events')}</CardTitle></CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
              {EVENTS.map((e) => (
                <label key={e} className="flex items-center justify-between gap-3 rounded border p-3 text-sm">
                  <span>{t(`notify.events.${e}`)}</span>
                  <Switch checked={events[e] !== false} onCheckedChange={(v) => setEvents((s) => ({ ...s, [e]: v }))} />
                </label>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader><CardTitle className="text-base">{t('notify.sections.bots')}</CardTitle></CardHeader>
            <CardContent className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <HrmField label={t('notify.telegramToken')} hint={data.telegram_token_set ? t('notify.tokenSaved') : data.telegram_env_token ? t('notify.tokenFromEnv') : t('notify.tokenHint')}>
                  <Input type="password" dir="ltr" autoComplete="off" value={telegramToken} onChange={(e) => setTelegramToken(e.target.value)} />
                </HrmField>
                {data.telegram_token_set ? (
                  <label className="flex items-center gap-2 text-sm"><Checkbox checked={clearTelegram} onCheckedChange={(v) => setClearTelegram(v === true)} />{t('notify.clearToken')}</label>
                ) : null}
              </div>
              <div className="space-y-2">
                <HrmField label={t('notify.baleToken')} hint={data.bale_token_set ? t('notify.tokenSaved') : data.bale_integration_token ? t('notify.tokenFromIntegration') : t('notify.tokenHint')}>
                  <Input type="password" dir="ltr" autoComplete="off" value={baleToken} onChange={(e) => setBaleToken(e.target.value)} />
                </HrmField>
                {data.bale_token_set ? (
                  <label className="flex items-center gap-2 text-sm"><Checkbox checked={clearBale} onCheckedChange={(v) => setClearBale(v === true)} />{t('notify.clearToken')}</label>
                ) : null}
              </div>
              <p className="md:col-span-2 text-xs text-muted-foreground">{t('notify.chatIdHint')}</p>
            </CardContent>
          </Card>

          <Button onClick={() => void save()}>{tNav('common.save')}</Button>

          <Card>
            <CardHeader><CardTitle className="text-base">{t('notify.sections.test')}</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <HrmField label={t('employee')}><HrmEmployeeSelect value={testEmployee} onChange={setTestEmployee} /></HrmField>
              <Button variant="outline" disabled={!testEmployee} onClick={() => void runTest()}>{t('notify.sendTest')}</Button>
              {testResults ? (
                <Table>
                  <TableHeader><TableRow><TableHead>{t('notify.channel')}</TableHead><TableHead>{t('notify.result')}</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {Object.entries(testResults).map(([channel, result]) => (
                      <TableRow key={channel}>
                        <TableCell>{t.has(`notify.channels.${channel}`) ? t(`notify.channels.${channel}`) : t('notify.channels.all')}</TableCell>
                        <TableCell>{t.has(`notify.results.${result}`) ? t(`notify.results.${result}`) : result}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              ) : null}
            </CardContent>
          </Card>
        </div>
      )}
    </HrmPageLayout>
  );
}
