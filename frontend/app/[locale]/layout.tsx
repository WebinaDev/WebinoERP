import { NextIntlClientProvider } from 'next-intl';
import { getMessages, setRequestLocale } from 'next-intl/server';
import { notFound } from 'next/navigation';
import { locales } from '@/i18n';
import { ThemeProvider } from '@/components/providers/theme-provider';
import { Toaster } from '@/components/ui/sonner';
import '../globals.css';

export function generateStaticParams() {
  return locales.map((locale) => ({ locale }));
}

export default async function LocaleLayout({
  children,
  params: { locale }
}: {
  children: React.ReactNode;
  params: { locale: string };
}) {
  if (!locales.includes(locale as (typeof locales)[number])) {
    notFound();
  }

  setRequestLocale(locale);

  const messages = await getMessages();

  const dir = locale === 'fa' ? 'rtl' : 'ltr';

  return (
    <html lang={locale} dir={dir} suppressHydrationWarning>
      <head>
        <link rel="icon" href="/brand/favicon.png" />
      </head>
      <body>
        <ThemeProvider>
          <NextIntlClientProvider messages={messages}>
            {children}
            <Toaster richColors position={dir === 'rtl' ? 'top-left' : 'top-right'} />
          </NextIntlClientProvider>
        </ThemeProvider>
      </body>
    </html>
  );
}

