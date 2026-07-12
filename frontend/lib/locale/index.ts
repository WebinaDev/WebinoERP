export * from './format-number';
export * from './format-date';

import type { Locale } from '@/i18n';

export type AppLocale = Locale;

export function getLocale(): AppLocale {
  if (typeof document !== 'undefined') {
    const lang = document.documentElement.lang;
    if (lang === 'en') return 'en';
  }
  return 'fa';
}
