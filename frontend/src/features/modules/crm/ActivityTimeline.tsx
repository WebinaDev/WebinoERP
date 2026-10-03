'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { useLocale } from '@/hooks/use-locale-next';

type Activity = {
  id: number;
  type: string;
  subject: string;
  description?: string | null;
  remind_at?: string | null;
  completed_at?: string | null;
  created_at?: string | null;
};

type Props = {
  accountId?: string;
  dealId?: string;
};

const TYPES = ['call', 'email', 'note', 'meeting', 'sms'] as const;

export function ActivityTimeline({ accountId, dealId }: Props) {
  const t = useTranslations('suite');
  const { formatDateTime } = useLocale();
  const [rows, setRows] = useState<Activity[]>([]);
  const [type, setType] = useState<(typeof TYPES)[number]>('note');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [remind, setRemind] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);

  const load = useCallback(async () => {
    const params = dealId ? { deal_id: dealId } : { account_id: accountId };
    const res = await apiClient.get('/v1/crm/timeline', { params });
    const data = (res.data as { data?: Activity[] }).data ?? [];
    setRows(data);
    setLoaded(true);
  }, [accountId, dealId]);

  useEffect(() => {
    void load().catch(() => setLoaded(true));
  }, [load]);

  async function add() {
    if (!subject.trim()) return;
    await apiClient.post('/v1/crm/timeline', {
      type,
      subject: subject.trim(),
      description: body,
      related_type: dealId ? 'deal' : 'account',
      related_id: Number(dealId || accountId),
      remind_at: remind ? `${remind}T09:00:00` : null,
    });
    setSubject('');
    setBody('');
    setRemind(null);
    await load();
  }

  async function complete(id: number) {
    await apiClient.post(`/v1/crm/timeline/${id}/complete`);
    await load();
  }

  const typeLabel = (value: string) => {
    const key = value as 'call' | 'email' | 'note' | 'meeting' | 'sms';
    return TYPES.includes(key as (typeof TYPES)[number]) ? t(key) : value;
  };

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{t('timelineHint')}</p>
      <form
        className="grid gap-3 rounded-lg border p-3 sm:grid-cols-2"
        onSubmit={(event) => {
          event.preventDefault();
          void add();
        }}
      >
        <div className="space-y-1">
          <Label htmlFor="activity-type">{t('activityType')}</Label>
          <select
            id="activity-type"
            className="flex h-9 w-full rounded-md border bg-transparent px-3 text-sm"
            value={type}
            onChange={(event) => setType(event.target.value as (typeof TYPES)[number])}
          >
            {TYPES.map((item) => (
              <option key={item} value={item}>{t(item)}</option>
            ))}
          </select>
        </div>
        <div className="space-y-1">
          <Label htmlFor="activity-subject">{t('subject')}</Label>
          <Input id="activity-subject" value={subject} onChange={(event) => setSubject(event.target.value)} />
        </div>
        <div className="space-y-1 sm:col-span-2">
          <Label htmlFor="activity-body">{t('body')}</Label>
          <Textarea id="activity-body" rows={2} value={body} onChange={(event) => setBody(event.target.value)} />
        </div>
        <div className="space-y-1">
          <Label>{t('reminder')}</Label>
          <LocaleDatePicker value={remind} onChange={setRemind} />
        </div>
        <div className="flex items-end">
          <Button type="submit" size="sm" disabled={!subject.trim()}>{t('add')}</Button>
        </div>
      </form>
      {loaded && rows.length === 0 ? (
        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">{t('noActivity')}</p>
      ) : null}
      <ol className="space-y-2">
        {rows.map((row) => (
          <li key={row.id} className="rounded-md border p-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="font-medium">{row.subject}</p>
              <span className="text-xs text-muted-foreground">{typeLabel(row.type)}</span>
            </div>
            {row.description ? <p className="mt-1 text-muted-foreground">{row.description}</p> : null}
            <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
              {row.created_at ? <span>{formatDateTime(row.created_at)}</span> : null}
              {row.remind_at ? <span>{t('reminder')}: {formatDateTime(row.remind_at)}</span> : null}
              {!row.completed_at ? (
                <Button type="button" size="sm" variant="outline" onClick={() => void complete(row.id)}>
                  {t('markDone')}
                </Button>
              ) : (
                <span>{t('markDone')}</span>
              )}
            </div>
          </li>
        ))}
      </ol>
    </div>
  );
}
