'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { getAxiosMessage, unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmViewToggle } from '@/features/shared/pm';
import { AccountSelect } from '@/features/shared/crm/AccountSelect';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { useLocale } from '@/hooks/use-locale-next';
import { shiftMonth } from '@/lib/locale/month-grid';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import {
  AppointmentsCalendarPanel,
  type CalendarAppointment,
} from '@/components/dashboard/pages/AppointmentsCalendarPanel';

type Row = Record<string, unknown>;

function pad2(n: number): string {
  return n < 10 ? `0${n}` : `${n}`;
}

function toYmd(d: Date): string {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function parseIsoDatePart(iso: string): string {
  return String(iso ?? '').slice(0, 10);
}

function parseIsoTimePart(iso: string): string {
  const t = String(iso ?? '').slice(11, 16);
  return t || '09:00';
}

function toCalendarEvent(r: Row): CalendarAppointment | null {
  const id = Number(r.id);
  if (!Number.isFinite(id)) return null;
  return {
    id,
    title: String(r.title ?? '—'),
    starts_at: String(r.starts_at ?? ''),
    ends_at: r.ends_at != null ? String(r.ends_at) : null,
    status: r.status != null ? String(r.status) : undefined,
  };
}

export function AppointmentsListPage() {
  const t = useTranslations('pm.appointments');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const { locale } = useLocale();
  const { layoutProps, setError, setSuccess, applyAxiosError } = useCrmFeedback();

  const [view, setView] = useState<'list' | 'calendar'>('calendar');
  const [rows, setRows] = useState<Row[]>([]);
  const [calendarEvents, setCalendarEvents] = useState<CalendarAppointment[]>([]);
  const [loading, setLoading] = useState(true);
  const [draggingEventId, setDraggingEventId] = useState<number | null>(null);

  const [viewMonth, setViewMonth] = useState(() => {
    const n = new Date();
    return new Date(n.getFullYear(), n.getMonth(), 1);
  });

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [title, setTitle] = useState('');
  const [notes, setNotes] = useState('');
  const [status, setStatus] = useState('scheduled');
  const [startDay, setStartDay] = useState<string | null>(null);
  const [startTime, setStartTime] = useState('09:00');
  const [endDay, setEndDay] = useState<string | null>(null);
  const [endTime, setEndTime] = useState('10:00');
  const [customerAccountId, setCustomerAccountId] = useState('');
  const [formErr, setFormErr] = useState<string | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const loadList = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/projects/appointments', { params: { per_page: 500 } });
      const raw = res.data as { data?: unknown };
      setRows(normalizeListPayload(raw.data ?? raw));
    } catch (e) {
      applyAxiosError(e);
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  const loadCalendar = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/projects/appointments/calendar');
      const list = normalizeListPayload(unwrapData(res));
      setCalendarEvents(
        list.map(toCalendarEvent).filter((x): x is CalendarAppointment => x !== null),
      );
    } catch {
      setCalendarEvents([]);
    }
  }, []);

  useEffect(() => {
    void loadList();
  }, [loadList]);

  useEffect(() => {
    if (view === 'calendar') void loadCalendar();
  }, [view, loadCalendar]);

  function openCreate(prefillDate?: string) {
    setEditingId(null);
    setTitle('');
    setNotes('');
    setStatus('scheduled');
    const day = prefillDate ?? toYmd(new Date());
    setStartDay(day);
    setStartTime('09:00');
    setEndDay(day);
    setEndTime('10:00');
    setCustomerAccountId('');
    setFormErr(null);
    setDialogOpen(true);
  }

  function openEdit(r: Row) {
    const id = Number(r.id);
    setEditingId(Number.isFinite(id) ? id : null);
    setTitle(String(r.title ?? ''));
    setNotes(String(r.notes ?? ''));
    setStatus(String(r.status ?? 'scheduled'));
    const st = String(r.starts_at ?? '');
    setStartDay(parseIsoDatePart(st) || toYmd(new Date()));
    setStartTime(parseIsoTimePart(st));
    const en = r.ends_at ? String(r.ends_at) : '';
    if (en) {
      setEndDay(parseIsoDatePart(en));
      setEndTime(parseIsoTimePart(en));
    } else {
      setEndDay(parseIsoDatePart(st) || toYmd(new Date()));
      setEndTime('10:00');
    }
    setCustomerAccountId(r.customer_account_id ? String(r.customer_account_id) : '');
    setFormErr(null);
    setDialogOpen(true);
  }

  function openEditById(id: number) {
    const match = rows.find((r) => Number(r.id) === id);
    if (match) {
      openEdit(match);
      return;
    }
    const ev = calendarEvents.find((e) => e.id === id);
    if (ev) {
      openEdit({
        id: ev.id,
        title: ev.title,
        starts_at: ev.starts_at,
        ends_at: ev.ends_at,
        status: ev.status,
      });
    }
  }

  function combineDateTime(day: string | null, time: string): string | null {
    if (!day || !time) return null;
    return `${day} ${time}:00`;
  }

  async function saveAppointment() {
    setFormErr(null);
    const starts = combineDateTime(startDay, startTime);
    if (!starts) {
      setFormErr(t('dateRequired'));
      return;
    }
    const ends = combineDateTime(endDay || startDay, endTime);
    setSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        title: title || t('title'),
        starts_at: starts,
        ends_at: ends,
        status,
        notes: notes || null,
        customer_account_id: customerAccountId ? Number(customerAccountId) : null,
      };
      if (editingId) {
        payload.id = editingId;
        await apiClient.put('/v1/projects/appointments', payload);
      } else {
        await apiClient.post('/v1/projects/appointments', payload);
      }
      setDialogOpen(false);
      setSuccess(tCommon('saved'));
      await loadList();
      if (view === 'calendar') await loadCalendar();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
      applyAxiosError(e);
    } finally {
      setSubmitting(false);
    }
  }

  async function confirmDelete() {
    if (!deleteId) return;
    try {
      await apiClient.delete(`/v1/projects/appointments/${deleteId}`);
      setDeleteId(null);
      setSuccess(tCommon('deleted'));
      await loadList();
      if (view === 'calendar') await loadCalendar();
    } catch (e) {
      applyAxiosError(e);
    }
  }

  async function handleReschedule(appointmentId: number, isoDate: string) {
    const source =
      calendarEvents.find((e) => e.id === appointmentId) ??
      (() => {
        const r = rows.find((x) => Number(x.id) === appointmentId);
        return r ? toCalendarEvent(r) : null;
      })();
    if (!source) return;

    const timePart = parseIsoTimePart(source.starts_at);
    const startsAt = `${isoDate} ${timePart}:00`;
    let endsAt: string | null = null;
    if (source.ends_at) {
      const endTimePart = parseIsoTimePart(source.ends_at);
      const oldStartDay = parseIsoDatePart(source.starts_at);
      const oldEndDay = parseIsoDatePart(source.ends_at);
      if (oldStartDay && oldEndDay && oldStartDay === oldEndDay) {
        endsAt = `${isoDate} ${endTimePart}:00`;
      } else {
        endsAt = `${isoDate} ${endTimePart}:00`;
      }
    }

    setSubmitting(true);
    setError(null);
    try {
      await apiClient.patch(`/v1/projects/appointments/${appointmentId}/date`, {
        starts_at: startsAt,
        ends_at: endsAt,
      });
      setSuccess(t('rescheduled'));
      await loadList();
      await loadCalendar();
    } catch (e) {
      applyAxiosError(e);
    } finally {
      setSubmitting(false);
      setDraggingEventId(null);
    }
  }

  return (
    <CrmPageLayout
      title={tNav('nav.erp.pm.appointments')}
      description={t('listDescription')}
      {...layoutProps}
      actions={
        <>
          <PmViewToggle
            value={view}
            onChange={(id) => setView(id as 'list' | 'calendar')}
            options={[
              { id: 'calendar', label: t('viewCalendar') },
              { id: 'list', label: t('viewList') },
            ]}
          />
          <Button type="button" size="sm" onClick={() => openCreate()}>
            {t('newAppointment')}
          </Button>
        </>
      }
    >
      {view === 'calendar' ? (
        <div className="space-y-2">
          <p className="text-xs text-muted-foreground">{t('dragHint')}</p>
          {loading ? <p className="text-sm text-muted-foreground">{tCommon('loading')}</p> : null}
          <AppointmentsCalendarPanel
            events={calendarEvents}
            viewMonth={viewMonth}
            onPrevMonth={() => setViewMonth((d) => shiftMonth(d, -1, locale))}
            onNextMonth={() => setViewMonth((d) => shiftMonth(d, 1, locale))}
            onDayClick={(iso) => openCreate(iso)}
            onEventClick={openEditById}
            onEventDragStart={setDraggingEventId}
            onDayDrop={(iso) => {
              if (draggingEventId == null) return;
              void handleReschedule(draggingEventId, iso);
            }}
            draggingEventId={draggingEventId}
          />
        </div>
      ) : (
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                <th className="px-2 py-2 text-start">{t('subject')}</th>
                <th className="px-2 py-2 text-start">{t('startsAt')}</th>
                <th className="px-2 py-2 text-start">{t('status')}</th>
                <th className="px-2 py-2 text-start"> </th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={4} className="py-6 text-center text-muted-foreground">
                    {tCommon('loading')}
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={4} className="py-6 text-center text-muted-foreground">
                    {t('empty')}
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={String(r.id)} className="border-b">
                    <td className="px-2 py-2">{String(r.title ?? '')}</td>
                    <td className="px-2 py-2" dir="ltr">
                      {String(r.starts_at ?? '')}
                    </td>
                    <td className="px-2 py-2">
                      <Badge variant="outline">{String(r.status ?? '—')}</Badge>
                    </td>
                    <td className="px-2 py-2">
                      <div className="flex flex-wrap gap-1">
                        <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(r)}>
                          {tCommon('edit')}
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="text-destructive"
                          onClick={() => setDeleteId(Number(r.id))}
                        >
                          {tCommon('delete')}
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{editingId ? t('editAppointment') : t('newAppointment')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <Input placeholder={t('subject')} value={title} onChange={(e) => setTitle(e.target.value)} />
            <div className="grid gap-2 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{t('startDate')}</p>
                <LocaleDatePicker value={startDay} onChange={setStartDay} />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{t('startTime')}</p>
                <Input
                  type="time"
                  value={startTime}
                  onChange={(e) => setStartTime(e.target.value)}
                  dir="ltr"
                />
              </div>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{t('endDate')}</p>
                <LocaleDatePicker value={endDay} onChange={setEndDay} />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{t('endTime')}</p>
                <Input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} dir="ltr" />
              </div>
            </div>
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger>
                <SelectValue placeholder={t('status')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="scheduled">{t('statusScheduled')}</SelectItem>
                <SelectItem value="completed">{t('statusCompleted')}</SelectItem>
                <SelectItem value="cancelled">{t('statusCancelled')}</SelectItem>
              </SelectContent>
            </Select>
            <AccountSelect
              value={customerAccountId || 'none'}
              onChange={(v) => setCustomerAccountId(v === 'none' ? '' : v)}
              allowEmpty
              placeholder={t('client')}
            />
            <Textarea
              placeholder={t('notes')}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
            />
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void saveAppointment()} disabled={submitting}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('deleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{tCommon('confirmDelete')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tCommon('cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>{tCommon('delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </CrmPageLayout>
  );
}
