import createIntlMiddleware from 'next-intl/middleware';
import { locales } from './i18n';

export default createIntlMiddleware({
  locales,
  defaultLocale: 'fa',
  localePrefix: 'never',
});

export const config = {
  matcher: ['/((?!api|_next|_vercel|.*\\..*).*)'],
};
