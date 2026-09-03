"use client"

import { useTranslations } from 'next-intl'

type LoginThemeControlsProps = {
  isRtl?: boolean
}

export function LoginThemeControls({ isRtl = false }: LoginThemeControlsProps) {
  const t = useTranslations()
  const siteName =
    (typeof window !== 'undefined' &&
      (window as unknown as { webinoDashboard?: { siteName?: string } })
        .webinoDashboard?.siteName?.trim()) ||
    t('app.title')

  return (
    <div className="absolute top-4 z-20">
      <div className={`text-xs text-muted-foreground ${isRtl ? 'end-4' : 'start-4'}`}>
        {siteName}
      </div>
    </div>
  )
}
