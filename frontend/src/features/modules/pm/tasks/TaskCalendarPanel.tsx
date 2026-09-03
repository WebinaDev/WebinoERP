'use client';

import { useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { TaskCalendarEvent } from './types';
import { useLocale } from '@/hooks/use-locale-next';
import { buildMonthGrid, shiftMonth } from '@/lib/locale/month-grid';

type Props = {
  events: TaskCalendarEvent[];
  month: Date;
  onMonthChange: (d: Date) => void;
  onSelectTask: (id: number) => void;
};

function parseDueKey(dueAt: string): string {
  return dueAt.slice(0, 10);
}

export function TaskCalendarPanel({ events, month, onMonthChange, onSelectTask }: Props) {
  const t = useTranslations('pm.tasks');
  const { formatDate, locale } = useLocale();
  const [selectedIso, setSelectedIso] = useState<string | null>(null);

  const weeks = useMemo(() => buildMonthGrid(month, locale), [month, locale]);

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

  const selectedEvents = selectedIso ? eventsByDay.get(selectedIso) ?? [] : [];
  const monthTitle = formatDate(
    weeks.flat().find((c) => c.day === 15)?.iso || weeks.flat().find((c) => c.day > 0)?.iso || '',
  );

  const weekdays =
    locale === 'fa'
      ? [t('weekdaySat'), t('weekdaySun'), t('weekdayMon'), t('weekdayTue'), t('weekdayWed'), t('weekdayThu'), t('weekdayFri')]
      : [t('weekdayMon'), t('weekdayTue'), t('weekdayWed'), t('weekdayThu'), t('weekdayFri'), t('weekdaySat'), t('weekdaySun')];

  return (
    <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
      <Card>
        <CardContent className="space-y-3 pt-6">
          <div className="flex items-center justify-between gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => onMonthChange(shiftMonth(month, -1, locale))}>
              {t('prevMonth')}
            </Button>
            <p className="text-sm font-medium">{monthTitle}</p>
            <Button type="button" variant="outline" size="sm" onClick={() => onMonthChange(shiftMonth(month, 1, locale))}>
              {t('nextMonth')}
            </Button>
          </div>
          <div className="text-muted-foreground grid grid-cols-7 gap-1 text-center text-xs">
            {weekdays.map((d) => (
              <div key={d} className="py-1 font-medium">
                {d}
              </div>
            ))}
          </div>
          <div className="space-y-1">
            {weeks.map((week, wi) => (
              <div key={wi} className="grid grid-cols-7 gap-1">
                {week.map((cell, di) => {
                  const dayEvents = cell.iso ? eventsByDay.get(cell.iso) ?? [] : [];
                  const isSelected = cell.iso !== '' && selectedIso === cell.iso;
                  return (
                    <button
                      key={`${wi}-${di}`}
                      type="button"
                      disabled={!cell.day}
                      className={cn(
                        'border-border/60 bg-muted/20 min-h-[88px] rounded-md border p-1 text-start align-top',
                        isSelected && 'ring-primary/40 ring-2',
                        !cell.day && 'opacity-40',
                      )}
                      onClick={() => cell.iso && setSelectedIso(cell.iso)}
                    >
                      {cell.day ? (
                        <>
                          <div className="text-muted-foreground mb-1 text-xs font-medium">{cell.day}</div>
                          <div className="flex max-h-[56px] flex-col gap-0.5 overflow-y-auto">
                            {dayEvents.slice(0, 3).map((ev) => (
                              <span
                                key={ev.id}
                                className="bg-primary/15 truncate rounded px-1 py-0.5 text-[10px] leading-tight"
                                title={ev.title}
                              >
                                {ev.title.slice(0, 18)}
                              </span>
                            ))}
                            {dayEvents.length > 3 ? (
                              <span className="text-muted-foreground text-[10px]">+{dayEvents.length - 3}</span>
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
          <h3 className="text-muted-foreground mb-2 text-sm font-medium">
            {selectedIso ? formatDate(selectedIso) : t('selectDay')}
          </h3>
          {selectedIso == null ? (
            <p className="text-muted-foreground text-sm">{t('selectDayHint')}</p>
          ) : selectedEvents.length === 0 ? (
            <p className="text-muted-foreground text-sm">{t('noTasks')}</p>
          ) : (
            selectedEvents.map((ev) => (
              <button
                key={ev.id}
                type="button"
                className="hover:bg-muted/50 w-full rounded-md border p-3 text-start transition-colors"
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
