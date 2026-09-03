'use client';

import { useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { useLocale } from '@/hooks/use-locale';

export type CalendarAppointment = {
  id: number;
  title: string;
  starts_at: string;
  ends_at?: string | null;
  status?: string;
};

type Props = {
  events: CalendarAppointment[];
  viewMonth: Date;
  onPrevMonth: () => void;
  onNextMonth: () => void;
  onDayClick: (isoDate: string) => void;
  onEventClick: (id: number) => void;
  onEventDragStart: (id: number) => void;
  onDayDrop: (isoDate: string) => void;
  draggingEventId: number | null;
};

function pad2(n: number): string {
  return n < 10 ? `0${n}` : `${n}`;
}

function toYmd(d: Date): string {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

const STATUS_COLORS: Record<string, string> = {
  scheduled: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
  pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
  confirmed: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
  completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
};

export function AppointmentsCalendarPanel({
  events,
  viewMonth,
  onPrevMonth,
  onNextMonth,
  onDayClick,
  onEventClick,
  onEventDragStart,
  onDayDrop,
  draggingEventId,
}: Props) {
  const t = useTranslations('pm.appointments');
  const { formatDisplayDate } = useLocale();
  const [selectedKey, setSelectedKey] = useState(() => toYmd(new Date()));

  const weekdayLabels = useMemo(
    () => [
      t('weekdaySat'),
      t('weekdaySun'),
      t('weekdayMon'),
      t('weekdayTue'),
      t('weekdayWed'),
      t('weekdayThu'),
      t('weekdayFri'),
    ],
    [t],
  );

  const calendarWeeks = useMemo(() => {
    const y = viewMonth.getFullYear();
    const m = viewMonth.getMonth();
    const first = new Date(y, m, 1);
    const startWeekday = (first.getDay() + 1) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const cells: (number | null)[] = [];
    for (let i = 0; i < startWeekday; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);
    while (cells.length % 7 !== 0) cells.push(null);
    const weeks: (number | null)[][] = [];
    for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));
    return weeks;
  }, [viewMonth]);

  const eventsByDate = useMemo(() => {
    const map: Record<string, CalendarAppointment[]> = {};
    for (const ev of events) {
      const d = String(ev.starts_at ?? '').slice(0, 10);
      if (!d) continue;
      if (!map[d]) map[d] = [];
      map[d].push(ev);
    }
    return map;
  }, [events]);

  const dayEvents = eventsByDate[selectedKey] ?? [];
  const monthTitle = viewMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <Button type="button" variant="outline" size="sm" onClick={onPrevMonth}>
          {t('prevMonth')}
        </Button>
        <p className="text-sm font-medium">{monthTitle}</p>
        <Button type="button" variant="outline" size="sm" onClick={onNextMonth}>
          {t('nextMonth')}
        </Button>
      </div>

      <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <Card>
          <CardContent className="space-y-1 pt-6">
            <div className="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
              {weekdayLabels.map((d) => (
                <div key={d} className="py-1 font-medium">
                  {d}
                </div>
              ))}
            </div>
            <div className="space-y-1">
              {calendarWeeks.map((week, wi) => (
                <div key={wi} className="grid grid-cols-7 gap-1">
                  {week.map((day, di) => {
                    if (!day) {
                      return (
                        <div
                          key={`${wi}-${di}`}
                          className="min-h-[72px] rounded-md border border-transparent bg-transparent"
                        />
                      );
                    }
                    const y = viewMonth.getFullYear();
                    const m = viewMonth.getMonth();
                    const iso = toYmd(new Date(y, m, day));
                    const dayAppts = eventsByDate[iso] ?? [];
                    const isSelected = iso === selectedKey;
                    return (
                      <div
                        key={`${wi}-${di}`}
                        role="button"
                        tabIndex={0}
                        className={cn(
                          'min-h-[72px] cursor-pointer rounded-md border border-border/60 bg-muted/20 p-1 text-start align-top',
                          isSelected && 'ring-2 ring-primary',
                        )}
                        onClick={() => {
                          setSelectedKey(iso);
                          onDayClick(iso);
                        }}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            setSelectedKey(iso);
                            onDayClick(iso);
                          }
                        }}
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={(e) => {
                          e.preventDefault();
                          setSelectedKey(iso);
                          onDayDrop(iso);
                        }}
                      >
                        <div className="mb-1 text-xs font-medium text-muted-foreground">{day}</div>
                        <div className="flex max-h-[48px] flex-col gap-0.5 overflow-y-auto">
                          {dayAppts.slice(0, 3).map((ap) => (
                            <button
                              key={ap.id}
                              type="button"
                              draggable
                              className={cn(
                                'truncate rounded bg-primary/15 px-1 py-0.5 text-[10px] leading-tight hover:bg-primary/25',
                                draggingEventId === ap.id && 'opacity-50 ring-1 ring-primary',
                              )}
                              title={ap.title}
                              onClick={(e) => {
                                e.stopPropagation();
                                onEventClick(ap.id);
                              }}
                              onDragStart={(e) => {
                                e.stopPropagation();
                                onEventDragStart(ap.id);
                              }}
                            >
                              {ap.title.slice(0, 18)}
                            </button>
                          ))}
                          {dayAppts.length > 3 ? (
                            <span className="text-[10px] text-muted-foreground">+{dayAppts.length - 3}</span>
                          ) : null}
                        </div>
                      </div>
                    );
                  })}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="space-y-2 pt-6">
            <h3 className="mb-3 text-sm font-medium text-muted-foreground">
              {formatDisplayDate?.(selectedKey) ?? selectedKey}
            </h3>
            {dayEvents.length === 0 ? (
              <button
                type="button"
                className="text-sm text-muted-foreground underline hover:text-foreground"
                onClick={() => onDayClick(selectedKey)}
              >
                {t('addOnDay')}
              </button>
            ) : (
              dayEvents.map((ev) => (
                <div
                  key={ev.id}
                  draggable
                  onDragStart={() => onEventDragStart(ev.id)}
                  className={cn(
                    'cursor-grab rounded-md border p-2 text-sm active:cursor-grabbing',
                    draggingEventId === ev.id && 'opacity-50 ring-2 ring-primary',
                  )}
                  onClick={() => onEventClick(ev.id)}
                >
                  <span className="font-medium">{ev.title}</span>
                  <Badge
                    className={cn('ms-2', STATUS_COLORS[ev.status ?? 'scheduled'] ?? 'bg-muted')}
                    variant="secondary"
                  >
                    {String(ev.starts_at).slice(11, 16) || '—'}
                  </Badge>
                </div>
              ))
            )}
            <div
              className="mt-4 min-h-[48px] rounded border border-dashed p-2 text-xs text-muted-foreground"
              onDragOver={(e) => e.preventDefault()}
              onDrop={(e) => {
                e.preventDefault();
                onDayDrop(selectedKey);
              }}
            >
              {draggingEventId !== null ? t('dropHint') : t('dragHint')}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
