'use client';

import * as React from 'react';
import { ThemeProvider as NextThemesProvider } from 'next-themes';
import { normalizeAccent } from '@/lib/accent';

type Props = React.ComponentProps<typeof NextThemesProvider>;

export function ThemeProvider({ children, ...props }: Props) {
  React.useEffect(() => {
    const stored = localStorage.getItem('webino-accent');
    document.documentElement.setAttribute('data-accent', normalizeAccent(stored));
  }, []);

  return (
    <NextThemesProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange {...props}>
      {children}
    </NextThemesProvider>
  );
}

export function setAccent(accent: string) {
  const normalized = normalizeAccent(accent);
  localStorage.setItem('webino-accent', normalized);
  document.documentElement.setAttribute('data-accent', normalized);
}
