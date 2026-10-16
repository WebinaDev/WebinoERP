import { NextIntlClientProvider } from 'next-intl';
import { getLocale, getMessages, getTimeZone } from 'next-intl/server';

import { DashboardShell } from '@/features/shell/layout/DashboardShell';
import { DashboardAuthGuard } from '@/features/shell/auth/DashboardAuthGuard';

// Root layout ships a trimmed message set on public pages. Re-provide the full set
// here so a client-side jump from the site into the dashboard still has every key.
export default async function DashboardLayout({ children }: { children: React.ReactNode }) {
  const [locale, messages, timeZone] = await Promise.all([getLocale(), getMessages(), getTimeZone()]);
  return (
    <NextIntlClientProvider locale={locale} messages={messages} timeZone={timeZone}>
      <DashboardAuthGuard>
        <DashboardShell>{children}</DashboardShell>
      </DashboardAuthGuard>
    </NextIntlClientProvider>
  );
}
