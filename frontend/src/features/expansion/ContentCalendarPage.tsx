'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { expansionApi, type Row } from '@/features/expansion/api';
import { AiAssistPanel } from '@/features/expansion/AiAssistPanel';

const selectClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';
const NETWORKS = ['blog', 'instagram', 'telegram', 'linkedin', 'x', 'bale'];
const STATUSES = ['idea', 'draft', 'review', 'scheduled', 'published'];

export function ContentCalendarPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Row[]>([]);
  const [cal, setCal] = useState({ name: '', kind: 'social', network: 'instagram', account_id: '' });
  const [item, setItem] = useState({ calendar_id: '', title: '', status: 'draft', publish_start: '', publish_end: '', remind_at: '' });

  const load = useCallback(async () => {
    try {
      setRows(await expansionApi.contentCalendars());
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={t('content.title')} description={t('content.description')} {...layoutProps}>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('content.newCalendar')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('content.name')} value={cal.name} onChange={(e) => setCal({ ...cal, name: e.target.value })} />
            <select className={selectClass} value={cal.kind} onChange={(e) => setCal({ ...cal, kind: e.target.value })}>
              <option value="blog">{t('content.blog')}</option>
              <option value="social">{t('content.social')}</option>
            </select>
            <select className={selectClass} value={cal.network} onChange={(e) => setCal({ ...cal, network: e.target.value })}>
              {NETWORKS.map((network) => <option key={network} value={network}>{t(`content.networks.${network}`)}</option>)}
            </select>
            <Input placeholder={t('content.account')} value={cal.account_id} onChange={(e) => setCal({ ...cal, account_id: e.target.value })} />
            <Button type="button" onClick={() => void expansionApi.saveContentCalendar({ ...cal, account_id: cal.account_id ? Number(cal.account_id) : null }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-base">{t('content.newItem')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('content.calendarId')} value={item.calendar_id} onChange={(e) => setItem({ ...item, calendar_id: e.target.value })} />
            <Input placeholder={t('content.itemTitle')} value={item.title} onChange={(e) => setItem({ ...item, title: e.target.value })} />
            <select className={selectClass} value={item.status} onChange={(e) => setItem({ ...item, status: e.target.value })}>
              {STATUSES.map((status) => <option key={status} value={status}>{t(`content.status.${status}`)}</option>)}
            </select>
            <Input type="datetime-local" value={item.publish_start} onChange={(e) => setItem({ ...item, publish_start: e.target.value })} />
            <Input type="datetime-local" value={item.publish_end} onChange={(e) => setItem({ ...item, publish_end: e.target.value })} />
            <Input type="datetime-local" value={item.remind_at} onChange={(e) => setItem({ ...item, remind_at: e.target.value })} />
            <Button type="button" onClick={() => void expansionApi.saveContentItem(Number(item.calendar_id), item).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
          </CardContent>
        </Card>
      </div>
      <div className="grid gap-3">
        {rows.map((row) => {
          const items = Array.isArray(row.items) ? (row.items as Row[]) : [];
          return (
            <Card key={String(row.id)}>
              <CardHeader>
                <CardTitle className="text-base">#{String(row.id)} {String(row.name)} · {String(row.network)}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                {items.map((entry) => (
                  <div key={String(entry.id)} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border/60 px-3 py-2 text-sm">
                    <span>{String(entry.title)}</span>
                    <span className="text-xs text-muted-foreground">{t(`content.status.${String(entry.status)}` as 'content.status.draft')}</span>
                  </div>
                ))}
              </CardContent>
            </Card>
          );
        })}
      </div>
      <AiAssistPanel purpose="content_brief" context={{ network: cal.network }} />
    </CrmPageLayout>
  );
}
