'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageLoadingState } from '@/features/shared/ui/PageStates';
import { expansionApi, type Row } from '@/features/expansion/api';

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';

export function ConnectPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [loading, setLoading] = useState(true);
  const [calendars, setCalendars] = useState<Row[]>([]);
  const [bridges, setBridges] = useState<Row[]>([]);
  const [mailboxes, setMailboxes] = useState<Row[]>([]);
  const [threads, setThreads] = useState<Row[]>([]);
  const [activeMail, setActiveMail] = useState<number | null>(null);
  const [sms, setSms] = useState({ mode: 'highest_only', floor: 'high' });
  const [bridge, setBridge] = useState({ provider: 'telegram', name: '', bot_token: '', channel_id: '' });
  const [mail, setMail] = useState({ provider: 'imap', email: '', imap_host: '', smtp_host: '', username: '' });
  const [compose, setCompose] = useState({ to: '', subject: '', body: '' });
  const [query, setQuery] = useState('');
  const [hits, setHits] = useState<Row[]>([]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [cal, br, box, policy] = await Promise.all([
        expansionApi.calendars(),
        expansionApi.bridges(),
        expansionApi.mailboxes(),
        expansionApi.smsPolicy(),
      ]);
      setCalendars(cal);
      setBridges(br);
      setMailboxes(box);
      setSms({ mode: policy.mode, floor: policy.floor });
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  async function connect(provider: 'google' | 'outlook') {
    try {
      const { url } = await expansionApi.connectUrl(provider);
      window.location.href = url;
    } catch (err) {
      applyAxiosError(err);
    }
  }

  async function openThreads(id: number) {
    setActiveMail(id);
    try {
      setThreads(await expansionApi.threads(id));
    } catch (err) {
      applyAxiosError(err);
    }
  }

  return (
    <CrmPageLayout title={t('connect.title')} description={t('connect.description')} {...layoutProps}>
      {loading ? <PageLoadingState /> : null}
      <Tabs defaultValue="calendar">
        <TabsList className="flex h-auto flex-wrap">
          <TabsTrigger value="calendar">{t('connect.tabs.calendar')}</TabsTrigger>
          <TabsTrigger value="chat">{t('connect.tabs.chat')}</TabsTrigger>
          <TabsTrigger value="mail">{t('connect.tabs.mail')}</TabsTrigger>
          <TabsTrigger value="sms">{t('connect.tabs.sms')}</TabsTrigger>
        </TabsList>
        <TabsContent value="calendar" className="space-y-4">
          <div className="flex flex-wrap gap-2">
            <Button type="button" onClick={() => void connect('google')}>{t('connect.google')}</Button>
            <Button type="button" variant="outline" onClick={() => void connect('outlook')}>{t('connect.outlook')}</Button>
          </div>
          <p className="text-sm text-muted-foreground">{t('connect.calendarHint')}</p>
          <div className="grid gap-3">
            {calendars.map((row) => (
              <Card key={String(row.id)}>
                <CardContent className="flex flex-wrap items-center justify-between gap-3 py-4">
                  <div>
                    <p className="font-medium">{String(row.email || row.provider)}</p>
                    <p className="text-xs text-muted-foreground">{String(row.provider)} · {String(row.calendar_id || 'primary')} · {String(row.status || '')}</p>
                    {row.status === 'needs_reauth' ? <p className="text-xs text-destructive">{t('connect.needsReauth')}</p> : null}
                  </div>
                  <Button type="button" size="sm" variant="outline" onClick={() => void expansionApi.syncCalendar(Number(row.id)).then(() => setSuccess(t('connect.synced'))).catch(applyAxiosError)}>
                    {t('connect.sync')}
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>
        <TabsContent value="chat" className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader><CardTitle className="text-base">{t('connect.newBridge')}</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <select className={selectClass} value={bridge.provider} onChange={(e) => setBridge({ ...bridge, provider: e.target.value })}>
                <option value="slack">Slack</option>
                <option value="bale">{t('connect.bale')}</option>
                <option value="telegram">Telegram</option>
              </select>
              <Input placeholder={t('connect.bridgeName')} value={bridge.name} onChange={(e) => setBridge({ ...bridge, name: e.target.value })} />
              <Input placeholder={t('connect.token')} value={bridge.bot_token} onChange={(e) => setBridge({ ...bridge, bot_token: e.target.value })} />
              <Input placeholder={t('connect.channel')} value={bridge.channel_id} onChange={(e) => setBridge({ ...bridge, channel_id: e.target.value })} />
              <Button
                type="button"
                onClick={() => void expansionApi.saveBridge({ ...bridge, enabled: true, inbound_commands: true }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}
              >
                {t('common.save')}
              </Button>
              <p className="text-xs text-muted-foreground">{t('connect.commandHint')}</p>
            </CardContent>
          </Card>
          <div className="space-y-3">
            {bridges.map((row) => (
              <Card key={String(row.id)}>
                <CardContent className="py-4">
                  <p className="font-medium">{String(row.name)}</p>
                  <p className="text-xs text-muted-foreground">{String(row.provider)}</p>
                  {row.webhook_url ? <p className="break-all text-xs text-muted-foreground" dir="ltr">{t('connect.webhookUrl')}: {String(row.webhook_url)}</p> : null}
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>
        <TabsContent value="mail" className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader><CardTitle className="text-base">{t('connect.newMailbox')}</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              <select className={selectClass} value={mail.provider} onChange={(e) => setMail({ ...mail, provider: e.target.value })}>
                <option value="google">Gmail</option>
                <option value="microsoft">Outlook</option>
                <option value="imap">IMAP / SMTP</option>
              </select>
              <Input type="email" placeholder={t('connect.email')} value={mail.email} onChange={(e) => setMail({ ...mail, email: e.target.value })} />
              <Input placeholder="IMAP host" value={mail.imap_host} onChange={(e) => setMail({ ...mail, imap_host: e.target.value })} />
              <Input placeholder="SMTP host" value={mail.smtp_host} onChange={(e) => setMail({ ...mail, smtp_host: e.target.value })} />
              <Input placeholder={t('connect.username')} value={mail.username} onChange={(e) => setMail({ ...mail, username: e.target.value })} />
              <Button type="button" onClick={() => void expansionApi.saveMailbox(mail).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>
                {t('common.save')}
              </Button>
            </CardContent>
          </Card>
          <div className="space-y-3">
            {mailboxes.map((row) => (
              <Card key={String(row.id)}>
                <CardContent className="flex flex-wrap items-center justify-between gap-2 py-4">
                  <div>
                    <p className="font-medium">{String(row.email)}</p>
                    <p className="text-xs text-muted-foreground">{String(row.provider)}</p>
                  </div>
                  <div className="flex gap-2">
                    <Button type="button" size="sm" variant="outline" onClick={() => void expansionApi.syncMailbox(Number(row.id)).then(() => openThreads(Number(row.id))).catch(applyAxiosError)}>{t('connect.sync')}</Button>
                    <Button type="button" size="sm" variant="outline" onClick={() => void openThreads(Number(row.id))}>{t('connect.threads')}</Button>
                  </div>
                </CardContent>
              </Card>
            ))}
            {activeMail ? (
              <Card>
                <CardHeader><CardTitle className="text-base">{t('connect.compose')}</CardTitle></CardHeader>
                <CardContent className="space-y-2">
                  <Input placeholder={t('connect.to')} value={compose.to} onChange={(e) => setCompose({ ...compose, to: e.target.value })} />
                  <Input placeholder={t('connect.subject')} value={compose.subject} onChange={(e) => setCompose({ ...compose, subject: e.target.value })} />
                  <Input placeholder={t('connect.body')} value={compose.body} onChange={(e) => setCompose({ ...compose, body: e.target.value })} />
                  <Button type="button" size="sm" onClick={() => void expansionApi.sendMail(activeMail, compose).then(() => { setSuccess(t('connect.sent')); return openThreads(activeMail); }).catch(applyAxiosError)}>{t('connect.send')}</Button>
                </CardContent>
              </Card>
            ) : null}
            <Card>
              <CardHeader><CardTitle className="text-base">{t('connect.search')}</CardTitle></CardHeader>
              <CardContent className="space-y-2">
                <p className="text-xs text-muted-foreground">{t('connect.searchHint')}</p>
                <Input value={query} onChange={(event) => setQuery(event.target.value)} />
                <Button type="button" variant="outline" onClick={() => void expansionApi.searchMail(query).then(setHits).catch(applyAxiosError)}>{t('connect.search')}</Button>
                <ul className="space-y-1 text-sm">
                  {hits.map((row) => <li key={String(row.id)}>{String(row.subject || t('connect.noSubject'))} · {String(row.from_email || '')}</li>)}
                </ul>
              </CardContent>
            </Card>
            {threads.map((thread) => (
              <Card key={String(thread.thread_key)}>
                <CardContent className="space-y-2 py-4">
                  <p className="font-medium">{String(thread.subject || t('connect.noSubject'))}</p>
                  {(Array.isArray(thread.messages) ? thread.messages : []).map((message) => {
                    const row = message as Row;
                    return (
                      <div key={String(row.id)} className="rounded-md border border-border/60 p-2 text-sm">
                        <p className="text-xs text-muted-foreground">{String(row.from_email || '')}</p>
                        <p className="whitespace-pre-wrap">{String(row.body || '')}</p>
                        <Button type="button" size="sm" variant="ghost" onClick={() => void expansionApi.linkMail(Number(row.id)).then(() => setSuccess(t('connect.linked'))).catch(applyAxiosError)}>
                          {t('connect.linkCrm')}
                        </Button>
                      </div>
                    );
                  })}
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>
        <TabsContent value="sms" className="max-w-lg space-y-3">
          <p className="text-sm text-muted-foreground">{t('connect.smsHint')}</p>
          <label className="block space-y-1 text-sm">
            <span>{t('connect.smsMode')}</span>
            <select className={selectClass} value={sms.mode} onChange={(e) => setSms({ ...sms, mode: e.target.value })}>
              <option value="off">{t('connect.modeOff')}</option>
              <option value="highest_only">{t('connect.modeHighest')}</option>
              <option value="matched">{t('connect.modeMatched')}</option>
            </select>
          </label>
          <label className="block space-y-1 text-sm">
            <span>{t('connect.smsFloor')}</span>
            <select className={selectClass} value={sms.floor} onChange={(e) => setSms({ ...sms, floor: e.target.value })}>
              <option value="low">{t('priority.low')}</option>
              <option value="normal">{t('priority.normal')}</option>
              <option value="high">{t('priority.high')}</option>
              <option value="urgent">{t('priority.urgent')}</option>
            </select>
          </label>
          <Button type="button" onClick={() => void expansionApi.saveSmsPolicy(sms).then(() => setSuccess(t('common.saved'))).catch(applyAxiosError)}>{t('common.save')}</Button>
        </TabsContent>
      </Tabs>
    </CrmPageLayout>
  );
}
