'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Template = { id: number; channel: string; name: string; subject?: string | null; body: string };
type Sequence = { id: number; name: string; channel: string; steps?: { id: number }[] };

export function CrmOutreachPage() {
  const t = useTranslations('suite');
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [templates, setTemplates] = useState<Template[]>([]);
  const [sequences, setSequences] = useState<Sequence[]>([]);
  const [name, setName] = useState('');
  const [channel, setChannel] = useState<'email' | 'sms'>('email');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [sendChannel, setSendChannel] = useState<'email' | 'sms'>('sms');
  const [relatedType, setRelatedType] = useState('lead');
  const [relatedId, setRelatedId] = useState('');
  const [sendBody, setSendBody] = useState('');
  const [sequenceName, setSequenceName] = useState('');
  const [sequenceTemplate, setSequenceTemplate] = useState('');
  const [enrollId, setEnrollId] = useState('');

  const load = useCallback(async () => {
    const [templateRes, sequenceRes] = await Promise.all([
      apiClient.get('/v1/crm/templates'),
      apiClient.get('/v1/crm/sequences'),
    ]);
    setTemplates(unwrapData<Template[]>(templateRes) ?? []);
    setSequences(unwrapData<Sequence[]>(sequenceRes) ?? []);
  }, []);

  useEffect(() => {
    void load().catch((error) => applyAxiosError(error));
  }, [load, applyAxiosError]);

  async function saveTemplate() {
    if (!name.trim() || !body.trim()) return;
    try {
      await apiClient.post('/v1/crm/templates', { name: name.trim(), channel, subject, body });
      setName('');
      setSubject('');
      setBody('');
      setSuccess(t('saveTemplate'));
      await load();
    } catch (error) {
      applyAxiosError(error);
    }
  }

  async function send() {
    if (!relatedId || !sendBody.trim()) return;
    try {
      await apiClient.post('/v1/crm/messages', {
        channel: sendChannel,
        related_type: relatedType,
        related_id: Number(relatedId),
        body: sendBody,
        subject: subject || undefined,
      });
      setSendBody('');
      setSuccess(t('sent'));
    } catch (error) {
      applyAxiosError(error);
    }
  }

  async function saveSequence() {
    if (!sequenceName.trim() || !sequenceTemplate) return;
    try {
      await apiClient.post('/v1/crm/sequences', {
        name: sequenceName.trim(),
        channel,
        steps: [{ template_id: Number(sequenceTemplate), delay_days: 0 }],
      });
      setSequenceName('');
      setSuccess(t('sequences'));
      await load();
    } catch (error) {
      applyAxiosError(error);
    }
  }

  async function enroll(sequenceId: number) {
    if (!enrollId) return;
    try {
      await apiClient.post(`/v1/crm/sequences/${sequenceId}/enroll`, {
        related_type: relatedType,
        related_id: Number(enrollId),
      });
      setSuccess(t('enroll'));
    } catch (error) {
      applyAxiosError(error);
    }
  }

  return (
    <CrmPageLayout title={t('outreachTitle')} description={t('outreachHint')} {...layoutProps}>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('templates')}</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {templates.length === 0 ? <p className="text-sm text-muted-foreground">{t('noTemplates')}</p> : templates.map((item) => (
              <p key={item.id} className="text-sm">{item.name} · {item.channel === 'sms' ? t('sms') : t('email')}</p>
            ))}
            <form className="space-y-2" onSubmit={(event) => { event.preventDefault(); void saveTemplate(); }}>
              <Label htmlFor="tpl-name">{t('templateName')}</Label>
              <Input id="tpl-name" value={name} onChange={(event) => setName(event.target.value)} />
              <Label htmlFor="tpl-channel">{t('channel')}</Label>
              <select id="tpl-channel" className="flex h-9 w-full rounded-md border bg-transparent px-3 text-sm" value={channel} onChange={(event) => setChannel(event.target.value as 'email' | 'sms')}>
                <option value="email">{t('email')}</option>
                <option value="sms">{t('sms')}</option>
              </select>
              <Label htmlFor="tpl-subject">{t('subject')}</Label>
              <Input id="tpl-subject" value={subject} onChange={(event) => setSubject(event.target.value)} />
              <Label htmlFor="tpl-body">{t('body')}</Label>
              <Textarea id="tpl-body" rows={3} value={body} onChange={(event) => setBody(event.target.value)} />
              <Button type="submit" size="sm">{t('saveTemplate')}</Button>
            </form>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('send')}</CardTitle></CardHeader>
          <CardContent>
            <form className="space-y-2" onSubmit={(event) => { event.preventDefault(); void send(); }}>
              <Label htmlFor="send-channel">{t('channel')}</Label>
              <select id="send-channel" className="flex h-9 w-full rounded-md border bg-transparent px-3 text-sm" value={sendChannel} onChange={(event) => setSendChannel(event.target.value as 'email' | 'sms')}>
                <option value="sms">{t('sms')}</option>
                <option value="email">{t('email')}</option>
              </select>
              <Label htmlFor="send-related">{t('related')}</Label>
              <select id="send-related" className="flex h-9 w-full rounded-md border bg-transparent px-3 text-sm" value={relatedType} onChange={(event) => setRelatedType(event.target.value)}>
                <option value="lead">{t('lead')}</option>
                <option value="contact">{t('contact')}</option>
                <option value="account">{t('account')}</option>
              </select>
              <Label htmlFor="send-id">{t('relatedId')}</Label>
              <Input id="send-id" inputMode="numeric" value={relatedId} onChange={(event) => setRelatedId(event.target.value)} />
              <Label htmlFor="send-body">{t('body')}</Label>
              <Textarea id="send-body" rows={3} value={sendBody} onChange={(event) => setSendBody(event.target.value)} />
              <Button type="submit" size="sm">{t('send')}</Button>
            </form>
          </CardContent>
        </Card>
      </div>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('sequences')}</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          {sequences.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : sequences.map((item) => (
            <div key={item.id} className="flex flex-wrap items-center gap-2 text-sm">
              <span className="font-medium">{item.name}</span>
              <Button type="button" size="sm" variant="outline" onClick={() => void enroll(item.id)}>{t('enroll')}</Button>
            </div>
          ))}
          <div className="grid gap-2 sm:grid-cols-2">
            <Input value={enrollId} onChange={(event) => setEnrollId(event.target.value)} placeholder={t('relatedId')} />
            <Input value={sequenceName} onChange={(event) => setSequenceName(event.target.value)} placeholder={t('sequenceName')} />
            <select className="flex h-9 w-full rounded-md border bg-transparent px-3 text-sm" value={sequenceTemplate} onChange={(event) => setSequenceTemplate(event.target.value)}>
              <option value="">{t('templates')}</option>
              {templates.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
            <Button type="button" size="sm" onClick={() => void saveSequence()}>{t('add')}</Button>
          </div>
          <Button type="button" size="sm" variant="secondary" onClick={() => void apiClient.post('/v1/crm/sequences/run-due').then(() => setSuccess(t('runDue'))).catch(applyAxiosError)}>
            {t('runDue')}
          </Button>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
