'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AiContentShell } from '../components/AiContentShell';
import {
  type CalendarSlot,
  bulkCalendar,
  createCalendarSlot,
  deleteCalendarSlot,
  fetchCalendar,
  runCalendarDue,
} from '../lib/ai-content-api';

export function AiCalendarPage() {
  const t = useTranslations('aiContent');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [items, setItems] = useState<CalendarSlot[]>([]);
  const [topic, setTopic] = useState('');
  const [focus, setFocus] = useState('');
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [type, setType] = useState<'blog' | 'product'>('blog');
  const [bulk, setBulk] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await fetchCalendar();
      setItems(res.items ?? []);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.aiContent.calendar')} {...layoutProps}>
      <AiContentShell active="calendar">
        <div className="flex flex-wrap gap-2">
          <Button
            size="sm"
            disabled={busy}
            onClick={() =>
              void (async () => {
                setBusy(true);
                try {
                  await runCalendarDue();
                  setSuccess(t('dueQueued'));
                  await load();
                } catch (err) {
                  applyAxiosError(err);
                } finally {
                  setBusy(false);
                }
              })()
            }
          >
            {t('runDue')}
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('addSlot')}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 md:grid-cols-2">
            <div className="space-y-1">
              <Label>{t('fieldDate')}</Label>
              <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label>{t('fieldType')}</Label>
              <select
                className="flex h-9 w-full rounded-md border bg-background px-3 text-sm"
                value={type}
                onChange={(e) => setType(e.target.value as 'blog' | 'product')}
              >
                <option value="blog">{t('typeBlog')}</option>
                <option value="product">{t('typeProduct')}</option>
              </select>
            </div>
            <div className="space-y-1 md:col-span-2">
              <Label>{t('fieldTopic')}</Label>
              <Input value={topic} onChange={(e) => setTopic(e.target.value)} />
            </div>
            <div className="space-y-1 md:col-span-2">
              <Label>{t('fieldFocus')}</Label>
              <Input value={focus} onChange={(e) => setFocus(e.target.value)} />
            </div>
            <Button
              disabled={!topic.trim() || busy}
              onClick={() =>
                void (async () => {
                  setBusy(true);
                  try {
                    await createCalendarSlot({
                      slot_date: date,
                      content_type: type,
                      topic,
                      focus_keyword: focus || topic,
                    });
                    setTopic('');
                    setFocus('');
                    setSuccess(tNav('common.saved'));
                    await load();
                  } catch (err) {
                    applyAxiosError(err);
                  } finally {
                    setBusy(false);
                  }
                })()
              }
            >
              {tNav('common.save')}
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('bulkTopics')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-sm text-muted-foreground">{t('bulkHint')}</p>
            <Textarea rows={6} value={bulk} onChange={(e) => setBulk(e.target.value)} />
            <Button
              disabled={!bulk.trim() || busy}
              onClick={() =>
                void (async () => {
                  setBusy(true);
                  try {
                    const res = await bulkCalendar({
                      topics: bulk,
                      start_date: date,
                      content_type: type,
                      focus_keyword: focus,
                    });
                    setBulk('');
                    setSuccess(t('bulkCreated', { count: res.created }));
                    await load();
                  } catch (err) {
                    applyAxiosError(err);
                  } finally {
                    setBusy(false);
                  }
                })()
              }
            >
              {t('bulkCreate')}
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('calendarList')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {items.map((slot) => (
              <div key={slot.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
                <div>
                  <div className="font-medium">
                    {slot.slot_date} · {slot.content_type} · {slot.status}
                  </div>
                  <div>{slot.topic}</div>
                  <div className="text-muted-foreground">{slot.focus_keyword}</div>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={busy}
                  onClick={() =>
                    void (async () => {
                      setBusy(true);
                      try {
                        await deleteCalendarSlot(slot.id);
                        setSuccess(tNav('common.deleted'));
                        await load();
                      } catch (err) {
                        applyAxiosError(err);
                      } finally {
                        setBusy(false);
                      }
                    })()
                  }
                >
                  {tNav('common.delete')}
                </Button>
              </div>
            ))}
            {!items.length ? <p className="text-sm text-muted-foreground">{t('noSlots')}</p> : null}
          </CardContent>
        </Card>
      </AiContentShell>
    </CrmPageLayout>
  );
}
