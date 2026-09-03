"use client"

import { useTranslations } from 'next-intl';

export default function GlobalError({
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {

  const t = useTranslations();
  return (
    <html lang="fa" dir="rtl">
      <body className="min-h-svh bg-background p-8 text-center font-sans text-foreground">
        <h1 className="text-xl font-semibold">{t('auto.app_global_error.s_8a73becf')}</h1>
        <p className="mt-2 text-sm opacity-70">{t('auto.app_global_error.s_7855138f')}</p>
        <div className="mt-6 flex justify-center gap-3">
          <button type="button" onClick={() => reset()}>
            {t('auto.app_global_error.s_f893dabf')}
          </button>
          <a href="/login">{t('auto.app_global_error.s_06c7b601')}</a>
        </div>
      </body>
    </html>
  )
}
