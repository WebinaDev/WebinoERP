'use client';

import { useTranslations } from 'next-intl';

export function Footer() {
  const t = useTranslations();

  const currentYear = new Date().getFullYear();

  return (
    <footer className="w-full border-t bg-background py-4 text-center text-sm">
      <div className="container flex flex-col items-center justify-between gap-4 md:flex-row">
        <p className="text-muted-foreground">
          © {currentYear} Webina. All rights reserved.
        </p>
        <p className="font-medium text-primary">
          {t('common.developedBy')}{' '}
          <span className="font-bold">{t('auto.layout_Footer.s_349ad4d2')}</span>
        </p>
      </div>
    </footer>
  );
}

