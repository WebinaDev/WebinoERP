import { useTranslation } from 'react-i18next'

type LoginThemeControlsProps = {
  isRtl?: boolean
}

export function LoginThemeControls({ isRtl = false }: LoginThemeControlsProps) {
  const { t } = useTranslation()
  const siteName = window.webinoDashboard?.siteName?.trim() || t('app.title')

  return (
    <div className="absolute top-4 z-20">
      <div className={`text-xs text-muted-foreground ${isRtl ? "right-4" : "left-4"}`}>
        {siteName}
      </div>
    </div>
  )
}
