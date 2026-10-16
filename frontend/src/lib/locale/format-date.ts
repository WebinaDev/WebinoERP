import type { Locale } from '@/i18n';
import { isRtlLocale, toLocaleDigits } from "@/lib/webina-ui-core";
import { formatCalendarDate, type AppDateLocale } from './calendar-date';

export function formatDate(
  iso: string,
  opts: string | { locale: string; includeTime?: boolean } = { locale: 'fa' }
): string {
  const o = typeof opts === 'string' ? { locale: opts } : opts
  return formatCalendarDate(iso, o.locale as AppDateLocale, Boolean(o.includeTime));
}

export function formatDateTime(iso: string, locale: Locale): string {
  return formatDate(iso, { locale, includeTime: true });
}

/** Display helper — ISO is the only source of truth (jalali arg ignored). */
export function formatDisplayDate(
  iso?: string | null,
  _jalali?: string | null,
  locale: Locale = 'fa'
): string {
  if (iso) return formatDate(iso, { locale });
  return '';
}

export function getCalendarConfig(locale: Locale) {
  return locale === 'fa'
    ? { calendar: 'jalali' as const, locale: 'fa' }
    : { calendar: 'gregorian' as const, locale: 'en' };
}

export { isRtlLocale, toLocaleDigits, toLatinDigits } from "@/lib/webina-ui-core";
