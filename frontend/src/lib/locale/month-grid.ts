import DateObject from 'react-date-object';
import gregorian from 'react-date-object/calendars/gregorian';
import persian from 'react-date-object/calendars/persian';
import gregorian_en from 'react-date-object/locales/gregorian_en';
import persian_fa from 'react-date-object/locales/persian_fa';
import type { Locale } from '@/i18n';

export type MonthDayCell = {
  /** Visible day number; 0 = empty spacer. */
  day: number;
  /** ISO Gregorian YYYY-MM-DD (empty for spacer). */
  iso: string;
};

function pad2(n: number): string {
  return n < 10 ? `0${n}` : `${n}`;
}

export function toYmdLocal(d: Date): string {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function chunkWeeks(cells: MonthDayCell[]): MonthDayCell[][] {
  const weeks: MonthDayCell[][] = [];
  for (let i = 0; i < cells.length; i += 7) {
    weeks.push(cells.slice(i, i + 7));
  }
  return weeks;
}

/**
 * Month grid for dashboard calendars.
 * fa → Jalali month + Saturday-first; en → Gregorian (+ optional Sat-first).
 */
export function buildMonthGrid(
  cursor: Date,
  locale: Locale,
  opts?: { weekStartsOnSat?: boolean },
): MonthDayCell[][] {
  const weekStartsOnSat = opts?.weekStartsOnSat ?? locale === 'fa';
  const empty: MonthDayCell = { day: 0, iso: '' };

  if (locale === 'fa') {
    const first = new DateObject({ date: cursor, calendar: gregorian, locale: gregorian_en })
      .convert(persian)
      .setLocale(persian_fa)
      .setDay(1);
    const daysInMonth = first.month.length;
    const startOffset = (first.toDate().getDay() + 1) % 7;
    const cells: MonthDayCell[] = [];
    for (let i = 0; i < startOffset; i++) cells.push(empty);
    for (let d = 1; d <= daysInMonth; d++) {
      const cell = new DateObject(first).setDay(d);
      const g = cell.convert(gregorian);
      cells.push({
        day: d,
        iso: `${g.year}-${pad2(g.month.number)}-${pad2(g.day)}`,
      });
    }
    while (cells.length % 7 !== 0) cells.push(empty);
    return chunkWeeks(cells);
  }

  const y = cursor.getFullYear();
  const m = cursor.getMonth();
  const first = new Date(y, m, 1);
  const startOffset = weekStartsOnSat ? (first.getDay() + 1) % 7 : (first.getDay() + 6) % 7;
  const daysInMonth = new Date(y, m + 1, 0).getDate();
  const cells: MonthDayCell[] = [];
  for (let i = 0; i < startOffset; i++) cells.push(empty);
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push({ day: d, iso: toYmdLocal(new Date(y, m, d)) });
  }
  while (cells.length % 7 !== 0) cells.push(empty);
  return chunkWeeks(cells);
}

/** Shift calendar cursor by one month in the active calendar system. */
export function shiftMonth(cursor: Date, delta: number, locale: Locale): Date {
  if (locale === 'fa') {
    const d = new DateObject({ date: cursor, calendar: gregorian, locale: gregorian_en })
      .convert(persian)
      .add(delta, 'month');
    return d.convert(gregorian).toDate();
  }
  return new Date(cursor.getFullYear(), cursor.getMonth() + delta, 1);
}
