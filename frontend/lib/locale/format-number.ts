import {
  formatCurrency as formatCurrencyShared,
  formatNumber as formatNumberShared,
  type UiLocale,
} from '@webina/ui';
import type { Locale } from '@/i18n';

export function formatNumber(value: number, locale: Locale): string {
  return formatNumberShared(value, locale);
}

export function formatCurrency(value: number, locale: Locale, currency = 'IRR'): string {
  return formatCurrencyShared(value, locale as UiLocale, currency);
}
