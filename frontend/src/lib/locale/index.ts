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
  normalizeUiLocale,
  toLatinDigits,
  toLocaleDigits,
} from '@webina/ui';

/** Document direction for the locale (`fa` → RTL). */
export function htmlDir(locale?: string | null): "rtl" | "ltr" {
  return isRtlLocale(locale) ? "rtl" : "ltr"
}

/**
 * Physical side for libraries that only accept left/right (e.g. shadcn Sidebar).
 * Prefer logical CSS elsewhere.
 */
export function physicalAlign(locale?: string | null): "left" | "right" {
  return isRtlLocale(locale) ? "right" : "left"
}

/** @deprecated Use physicalAlign */
export const sidebarSide = physicalAlign;
