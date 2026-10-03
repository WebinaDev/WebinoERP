import { describe, expect, it } from 'vitest';
import { displayDateKey, formatCalendarDate, formatChartAxis, jalaliMonthIsoRange, toGregorian, toJalali } from './calendar-date';

describe('calendar date formatting', () => {
  it('converts Nowruz 1405 to the Gregorian date and back', () => {
    expect(toJalali(2026, 3, 21)).toEqual({ jy: 1405, jm: 1, jd: 1 });
    expect(toGregorian(1405, 1, 1)).toEqual({ gy: 2026, gm: 3, gd: 21 });
    expect(toJalali(2024, 3, 20)).toEqual({ jy: 1403, jm: 1, jd: 1 });
  });

  it('formats Persian UI as Jalali digits and English UI as Gregorian', () => {
    expect(formatCalendarDate('2026-03-21', 'fa')).toBe('۱۴۰۵/۰۱/۰۱');
    expect(formatCalendarDate('2026-03-21', 'en')).toBe('2026-03-21');
  });

  it('shifts UTC instants into Tehran for Persian display only', () => {
    expect(formatCalendarDate('2026-03-20T20:30:00Z', 'fa', true)).toBe('۱۴۰۵/۰۱/۰۱ ۰۰:۰۰');
    expect(formatCalendarDate('2026-03-20T20:30:00Z', 'en', true)).toBe('2026-03-20 20:30');
    expect(displayDateKey('2026-03-20T20:30:00Z', 'fa')).toBe('2026-03-21');
    expect(displayDateKey('2026-03-20T20:30:00Z', 'en')).toBe('2026-03-20');
  });

  it('does not timezone-shift date-only values', () => {
    expect(displayDateKey('2026-03-21', 'fa')).toBe('2026-03-21');
    expect(formatCalendarDate('2026-03-21', 'fa', true)).toBe('۱۴۰۵/۰۱/۰۱');
  });

  it('bounds Farvardin 1405 and labels chart axes in Jalali', () => {
    expect(jalaliMonthIsoRange(0, new Date('2026-03-21T12:00:00Z'))).toEqual({
      from: '2026-03-21',
      to: '2026-04-20',
    });
    expect(formatChartAxis('2026-03', 'fa')).toBe('۱۴۰۴/۱۲');
    expect(formatChartAxis('2026-03-21', 'fa')).toBe('۰۱/۰۱');
    expect(formatChartAxis('2026-03-21', 'en')).toBe('03-21');
  });
});
