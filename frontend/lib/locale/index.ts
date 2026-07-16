export * from './format-number';
export * from './format-date';

import type { Locale } from '@/i18n';
import { normalizeUiLocale, type UiLocale } from '@webina/ui';

export type AppLocale = Locale;

export function getLocale(): AppLocale {
  if (typeof document !== 'undefined') {
    return normalizeUiLocale(document.documentElement.lang) as AppLocale;
  }
  return 'fa';
}

export type { UiLocale };
export {
  formatCurrency as formatCurrencyShared,
  formatDate as formatDateIntl,
  formatNumber as formatNumberShared,
  isRtlLocale as isRtlLocaleShared,
  toLatinDigits,
  toLocaleDigits,
} from '@webina/ui';
