'use client';

import { useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { TaskCalendarEvent } from './types';

type Props = {
  events: TaskCalendarEvent[];
  month: Date;
  onMonthChange: (d: Date) => void;
  onSelectTask: (id: number) => void;
};

function toYmd(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function parseDueKey(dueAt: string): string {
  return dueAt.slice(0, 10);
}

export function TaskCalendarPanel({ events, month, onMonthChange, onSelectTask }: Props) {
  const t = useTranslations('pm.tasks');
  const [selectedDay, setSelectedDay] = useState<number | null>(null);

  const weeks = useMemo(() => {
    const y = month.getFullYear();
    const m = month.getMonth();
    const first = new Date(y, m, 1);
    const startWeekday = (first.getDay() + 6) % 7; // Mon-first
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const cells: (number | null)[] = [];
    for (let i = 0; i < startWeekday; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(d);
    while (cells.length % 7 !== 0) cells.push(null);
    const out: (number | null)[][] = [];
    for (let i = 0; i < cells.length; i += 7) out.push(cells.slice(i, i + 7));
    return out;
  }, [month]);

  const eventsByDay = useMemo(() => {
    const map = new Map<string, TaskCalendarEvent[]>();
    for (const ev of events) {
      const key = parseDueKey(ev.due_at);
      const arr = map.get(key) ?? [];
      arr.push(ev);
      map.set(key, arr);
    }
    return map;
  }, [events]);

  function dayKey(day: number): string {
    return toYmd(new Date(month.getFullYear(), month.getMonth(), day));
  }

  function eventsForDay(day: number): TaskCalendarEvent[] {
    return eventsByDay.get(dayKey(day)) ?? [];
  }

  const selectedEvents =
    selectedDay != null ? eventsForDay(selectedDay) : [];

  const monthTitle = month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  const weekdays = [
    t('weekdayMon'),
    t('weekdayTue'),
    t('weekdayWed'),
    t('weekdayThu'),
    t('weekdayFri'),
    t('weekdaySat'),
    t('weekdaySun'),
  ];

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
      <Card>
        <CardContent className="space-y-3 pt-6">
          <div className="flex items-center justify-between gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => onMonthChange(new Date(month.getFullYear(), month.getMonth() - 1, 1))}
            >
              {t('prevMonth')}
            </Button>
            <p className="text-sm font-medium">{monthTitle}</p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => onMonthChange(new Date(month.getFullYear(), month.getMonth() + 1, 1))}
            >
              {t('nextMonth')}
            </Button>
          </div>
          <div className="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
            {weekdays.map((d) => (
              <div key={d} className="py-1 font-medium">
                {d}
              </div>
            ))}
          </div>
          <div className="space-y-1">
            {weeks.map((week, wi) => (
              <div key={wi} className="grid grid-cols-7 gap-1">
                {week.map((day, di) => {
                  const dayEvents = day ? eventsForDay(day) : [];
                  const isSelected = day != null && selectedDay === day;
                  return (
                    <button
                      key={`${wi}-${di}`}
                      type="button"
                      disabled={!day}
                      className={cn(
                        'min-h-[88px] rounded-md border border-border/60 bg-muted/20 p-1 text-start align-top',
                        isSelected && 'ring-2 ring-primary/40',
                        !day && 'opacity-40'
                      )}
                      onClick={() => day && setSelectedDay(day)}
                    >
                      {day ? (
                        <>
                          <div className="mb-1 text-xs font-medium text-muted-foreground">{day}</div>
                          <div className="flex max-h-[56px] flex-col gap-0.5 overflow-y-auto">
                            {dayEvents.slice(0, 3).map((ev) => (
                              <span
                                key={ev.id}
                                className="truncate rounded bg-primary/15 px-1 py-0.5 text-[10px] leading-tight"
                                title={ev.title}
                              >
                                {ev.title.slice(0, 18)}
                              </span>
                            ))}
                            {dayEvents.length > 3 ? (
                              <span className="text-[10px] text-muted-foreground">+{dayEvents.length - 3}</span>
                            ) : null}
                          </div>
                        </>
                      ) : null}
                    </button>
                  );
                })}
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
      <Card>
        <CardContent className="space-y-2 pt-6">
          <h3 className="mb-2 text-sm font-medium text-muted-foreground">
            {selectedDay != null
              ? dayKey(selectedDay)
              : t('selectDay')}
          </h3>
          {selectedDay == null ? (
            <p className="text-sm text-muted-foreground">{t('selectDayHint')}</p>
          ) : selectedEvents.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('noTasks')}</p>
          ) : (
            selectedEvents.map((ev) => (
              <button
                key={ev.id}
                type="button"
                className="w-full rounded-md border p-3 text-start transition-colors hover:bg-muted/50"
                onClick={() => onSelectTask(ev.id)}
              >
                <span className="font-medium">{ev.title}</span>
              </button>
            ))
          )}
        </CardContent>
      </Card>
    </div>
  );
}
