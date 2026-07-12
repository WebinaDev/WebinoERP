'use client';

import { useLocale as useNextIntlLocale, useTranslations } from 'next-intl';
import {
  formatCurrency,
  formatDate,
  formatDateTime,
  formatDisplayDate,
  formatNumber,
  getCalendarConfig,
  isRtlLocale,
  type AppLocale,
} from '@/lib/locale';

export function useLocale() {
  const t = useTranslations();
  const locale = useNextIntlLocale() as AppLocale;
  const isRtl = isRtlLocale(locale);

  return {
    t,
    locale,
    lang: locale,
    isRtl,
    formatNumber: (n: number) => formatNumber(n, locale),
    formatCurrency: (n: number) => formatCurrency(n, locale),
    formatDate: (iso: string, opts?: { includeTime?: boolean }) =>
      formatDate(iso, { locale, includeTime: opts?.includeTime }),
    formatDateTime: (iso: string) => formatDateTime(iso, locale),
    formatDisplayDate: (iso?: string | null, jalali?: string | null) =>
      formatDisplayDate(iso, jalali, locale),
    getCalendarConfig: () => getCalendarConfig(locale),
  };
}
