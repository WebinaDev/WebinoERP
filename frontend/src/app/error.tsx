"use client"

import { useTranslations } from 'next-intl';

export default function Error({
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {

  const t = useTranslations();
  return (
    <div className="mx-auto flex min-h-[50vh] max-w-lg flex-col items-center justify-center gap-4 px-4 py-16 text-center">
      <h1 className="text-xl font-semibold">{t('auto.app_error.s_9969ab17')}</h1>
      <p className="text-muted-foreground text-sm">
        {t('auto.app_error.s_e0429882')}
      </p>
      <div className="flex flex-wrap justify-center gap-3">
        <button
          type="button"
          className="rounded-md bg-[#0066FF] px-4 py-2 text-sm text-white"
          onClick={() => reset()}>
        
          {t('auto.app_error.s_f893dabf')}
        </button>
        <a href="/login" className="rounded-md border px-4 py-2 text-sm">
          {t('auto.app_error.s_8ec1b5c4')}
        </a>
      </div>
    </div>
  )
}
