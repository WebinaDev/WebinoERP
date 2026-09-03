export * from './format-number';
export * from './format-date';

import type { Locale } from '../../../i18n';
import { isRtlLocale, normalizeUiLocale, type UiLocale } from '@webina/ui';

export type AppLocale = Locale;

export function getLocale(): AppLocale {
  if (typeof document !== 'undefined') {
    return normalizeUiLocale(document.documentElement.lang) as AppLocale;
  }
  return 'fa';
}

export type { UiLocale };
export {
  formatCurrency,
  formatCurrency as formatCurrencyShared,
  formatDate as formatDateIntl,
  formatNumber,
  formatNumber as formatNumberShared,
  isRtlLocale,
  isRtlLocale as isRtlLocaleShared,
  normalizeUiLocale,
  toLatinDigits,
  toLocaleDigits,
} from '@webina/ui';

/** Document direction for the locale (`fa` → RTL). */
export function htmlDir(locale?: string | null): "rtl" | "ltr" {
  return isRtlLocale(locale) ? "rtl" : "ltr"
}

/**
 * Physical side for shadcn Sidebar / Sheet / dropdowns.
 * Farsi: right. English: left.
 */
export function sidebarSide(locale?: string | null): "left" | "right" {
  return isRtlLocale(locale) ? "right" : "left"
}
