import type { Locale } from '@/i18n';

export function formatNumber(value: number, locale: Locale): string {
  return new Intl.NumberFormat(locale === 'fa' ? 'fa-IR' : 'en-US').format(value);
}

export function formatCurrency(value: number, locale: Locale, currency = 'IRR'): string {
  return new Intl.NumberFormat(locale === 'fa' ? 'fa-IR' : 'en-US', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value);
}
