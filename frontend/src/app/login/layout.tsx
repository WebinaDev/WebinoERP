import type { ReactNode } from 'react';
import { NextIntlClientProvider } from 'next-intl';
import { getLocale, getMessages, getTimeZone } from 'next-intl/server';

// Full message set (root layout trims admin namespaces on public pages).
export default async function LoginLayout({ children }: { children: ReactNode }) {
  const [locale, messages, timeZone] = await Promise.all([getLocale(), getMessages(), getTimeZone()]);
  return (
    <NextIntlClientProvider locale={locale} messages={messages} timeZone={timeZone}>
      {children}
    </NextIntlClientProvider>
  );
}
