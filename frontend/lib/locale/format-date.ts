import moment from 'moment-jalaali';
import type { Locale } from '@/i18n';

moment.loadPersian({ dialect: 'persian-modern', usePersianDigits: true });

export function formatDate(
  iso: string,
  opts: { locale: Locale; includeTime?: boolean } = { locale: 'fa' }
): string {
  if (!iso) return '—';
  const m = moment(iso);
  if (opts.locale === 'fa') {
    return opts.includeTime
      ? m.format('jYYYY/jMM/jDD HH:mm')
      : m.format('jYYYY/jMM/jDD');
  }
  return opts.includeTime
    ? m.locale('en').format('YYYY-MM-DD HH:mm')
    : m.locale('en').format('YYYY-MM-DD');
}

export function formatDateTime(iso: string, locale: Locale): string {
  return formatDate(iso, { locale, includeTime: true });
}

export function formatDisplayDate(
  iso?: string | null,
  jalali?: string | null,
  locale: Locale = 'fa'
): string {
  if (locale === 'fa' && jalali) return jalali;
  if (iso) return formatDate(iso, { locale });
  return '—';
}

export function getCalendarConfig(locale: Locale) {
  return locale === 'fa'
    ? { calendar: 'jalali' as const, locale: 'fa' }
    : { calendar: 'gregorian' as const, locale: 'en' };
}

export function isRtlLocale(locale: Locale): boolean {
  return locale === 'fa';
}
