'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
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

type Row = Record<string, unknown>;
type AccountOpt = { id: number; name: string; type?: string };

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

export function AppointmentsListPage() {
  const t = useTranslations('pm.appointments');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const [rows, setRows] = useState<Row[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const [viewMonth, setViewMonth] = useState(() => {
    const n = new Date();
    return new Date(n.getFullYear(), n.getMonth(), 1);
  });

  const [accounts, setAccounts] = useState<AccountOpt[]>([]);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [title, setTitle] = useState('');
  const [notes, setNotes] = useState('');
  const [status, setStatus] = useState('scheduled');
  const [startDay, setStartDay] = useState<string | null>(null);
  const [startTime, setStartTime] = useState('09:00');
  const [endDay, setEndDay] = useState<string | null>(null);
  const [endTime, setEndTime] = useState('10:00');
  const [customerAccountId, setCustomerAccountId] = useState<string>('');
  const [formErr, setFormErr] = useState<string | null>(null);

  const [deleteId, setDeleteId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/appointments', { params: { per_page: 500 } });
      const raw = res.data as { data?: unknown };
      const inner = raw.data;
      setRows(normalizeListPayload(inner));
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void (async () => {
      try {
        const res = await apiClient.get('/v1/crm/accounts/list');
        const body = res.data as { data?: AccountOpt[] };
        setAccounts(Array.isArray(body.data) ? body.data : []);
      } catch {
        setAccounts([]);
      }
    })();
  }, []);

  const calendarWeeks = useMemo(() => {
    const y = viewMonth.getFullYear();
    const m = viewMonth.getMonth();
    const first = new Date(y, m, 1);
    const startWeekday = (first.getDay() + 1) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const cells: (number | null)[] = [];
    for (let i = 0; i < startWeekday; i++) {
      cells.push(null);
    }
    for (let d = 1; d <= daysInMonth; d++) {
      cells.push(d);
    }
    while (cells.length % 7 !== 0) {
      cells.push(null);
    }
    const weeks: (number | null)[][] = [];
    for (let i = 0; i < cells.length; i += 7) {
      weeks.push(cells.slice(i, i + 7));
    }
    return weeks;
  }, [viewMonth]);

  function appointmentsForDay(day: number): Row[] {
    const y = viewMonth.getFullYear();
    const m = viewMonth.getMonth();
    const key = toYmd(new Date(y, m, day));
    return rows.filter((r) => parseIsoDatePart(String(r.starts_at ?? '')) === key);
  }

  function openCreate() {
    setEditingId(null);
    setTitle('');
    setNotes('');
    setStatus('scheduled');
    const today = toYmd(new Date());
    setStartDay(today);
    setStartTime('09:00');
    setEndDay(today);
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

  function combineDateTime(day: string | null, time: string): string | null {
    if (!day || !time) {
      return null;
    }
    return `${day} ${time}:00`;
  }

  async function saveAppointment() {
    setFormErr(null);
    const starts = combineDateTime(startDay, startTime);
    if (!starts) {
      setFormErr('تاریخ و زمان شروع را کامل کنید.');
      return;
    }
    const ends = combineDateTime(endDay || startDay, endTime);
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
      await load();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    }
  }

  async function confirmDelete() {
    if (!deleteId) {
      return;
    }
    try {
      await apiClient.delete(`/v1/projects/appointments/${deleteId}`);
      setDeleteId(null);
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  function prevMonth() {
    setViewMonth((d) => new Date(d.getFullYear(), d.getMonth() - 1, 1));
  }

  function nextMonth() {
    setViewMonth((d) => new Date(d.getFullYear(), d.getMonth() + 1, 1));
  }

  const monthTitle = viewMonth.toLocaleDateString('fa-IR', { month: 'long', year: 'numeric' });

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle>{tNav('nav.erp.pm.appointments')}</CardTitle>
        <Button type="button" size="sm" onClick={() => openCreate()}>
          قرار جدید
        </Button>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="calendar">
          <TabsList>
            <TabsTrigger value="calendar">تقویم</TabsTrigger>
            <TabsTrigger value="list">لیست</TabsTrigger>
          </TabsList>

          <TabsContent value="calendar" className="space-y-4 pt-4">
            {error ? <p className="text-sm text-destructive">{error}</p> : null}
            <div className="flex items-center justify-between gap-2">
              <Button type="button" variant="outline" size="sm" onClick={prevMonth}>
                ماه قبل
              </Button>
              <p className="text-sm font-medium">{monthTitle}</p>
              <Button type="button" variant="outline" size="sm" onClick={nextMonth}>
                ماه بعد
              </Button>
            </div>
            <div className="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
              {['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'].map((d) => (
                <div key={d} className="py-1 font-medium">
                  {d}
                </div>
              ))}
            </div>
            <div className="space-y-1">
              {calendarWeeks.map((week, wi) => (
                <div key={wi} className="grid grid-cols-7 gap-1">
                  {week.map((day, di) => (
                    <div
                      key={`${wi}-${di}`}
                      className="min-h-[88px] rounded-md border border-border/60 bg-muted/20 p-1 text-start align-top"
                    >
                      {day ? (
                        <>
                          <div className="mb-1 text-xs font-medium text-muted-foreground">{day}</div>
                          <div className="flex max-h-[56px] flex-col gap-0.5 overflow-y-auto">
                            {appointmentsForDay(day).map((ap) => (
                              <button
                                key={String(ap.id)}
                                type="button"
                                className="truncate rounded bg-primary/15 px-1 py-0.5 text-[10px] leading-tight hover:bg-primary/25"
                                title={String(ap.title ?? '')}
                                onClick={() => openEdit(ap)}
                              >
                                {String(ap.title ?? '—').slice(0, 18)}
                              </button>
                            ))}
                          </div>
                        </>
                      ) : (
                        <span className="text-transparent">.</span>
                      )}
                    </div>
                  ))}
                </div>
              ))}
            </div>
            {loading ? <p className="text-sm text-muted-foreground">بارگذاری…</p> : null}
          </TabsContent>

          <TabsContent value="list" className="space-y-2 pt-4">
            {error ? <p className="text-destructive text-sm">{error}</p> : null}
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start">عنوان</th>
                    <th className="px-2 py-2 text-start">شروع</th>
                    <th className="px-2 py-2 text-start">وضعیت</th>
                    <th className="px-2 py-2 text-start"> </th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr>
                      <td colSpan={4} className="py-6 text-center">
                        …
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
                              ویرایش
                            </Button>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              className="text-destructive"
                              onClick={() => setDeleteId(Number(r.id))}
                            >
                              حذف
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{editingId ? 'ویرایش قرار' : 'قرار جدید'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <Input placeholder="عنوان" value={title} onChange={(e) => setTitle(e.target.value)} />
            <div className="grid gap-2 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">تاریخ شروع</p>
                <LocaleDatePicker value={startDay} onChange={setStartDay} />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">ساعت شروع</p>
                <Input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} dir="ltr" />
              </div>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">تاریخ پایان</p>
                <LocaleDatePicker value={endDay} onChange={setEndDay} />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">ساعت پایان</p>
                <Input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} dir="ltr" />
              </div>
            </div>
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger>
                <SelectValue placeholder="وضعیت" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="scheduled">scheduled</SelectItem>
                <SelectItem value="completed">completed</SelectItem>
                <SelectItem value="cancelled">cancelled</SelectItem>
              </SelectContent>
            </Select>
            <Select value={customerAccountId || '__none'} onValueChange={(v) => setCustomerAccountId(v === '__none' ? '' : v)}>
              <SelectTrigger>
                <SelectValue placeholder="مشتری (حساب)" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">—</SelectItem>
                {accounts.map((a) => (
                  <SelectItem key={a.id} value={String(a.id)}>
                    {a.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Textarea placeholder="یادداشت" value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} />
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void saveAppointment()}>
              ذخیره
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف قرار؟</AlertDialogTitle>
            <AlertDialogDescription>این عمل قابل بازگشت نیست.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>انصراف</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>حذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}
