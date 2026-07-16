import type { Metadata } from 'next';
import { NextIntlClientProvider } from 'next-intl';
import { getMessages, setRequestLocale } from 'next-intl/server';
import { notFound } from 'next/navigation';
import { locales } from '@/i18n';
import { ThemeProvider } from '@/components/providers/theme-provider';
import { Toaster } from '@/components/ui/sonner';
import { yekanBakh } from '@/src/lib/fonts/yekan-bakh';
import { getApiOrigin } from '@/lib/api-origin';
import '../globals.css';

export const metadata: Metadata = {
  title: 'Webino ERP',
  description: 'Integrated business management platform',
  icons: {
    icon: [{ url: '/brand/favicon.png', type: 'image/png' }],
    apple: '/brand/apple-touch-icon.png',
  },
};

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
  const apiOrigin = getApiOrigin();

  return (
    <html lang={locale} dir={dir} suppressHydrationWarning>
      <head>
        {apiOrigin ? (
          <>
            <link rel="preconnect" href={apiOrigin} />
            <link rel="dns-prefetch" href={apiOrigin} />
          </>
        ) : null}
      </head>
      <body className={`${yekanBakh.variable} min-h-svh font-sans`}>
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
