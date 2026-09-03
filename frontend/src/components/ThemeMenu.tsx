import { useQueryClient } from '@tanstack/react-query'
import { Check, Laptop, Moon, Sun } from 'lucide-react'
import { useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { apiFetch } from '@/lib/api'
import { patchBootstrapQuery } from '@/lib/bootstrapQuery'
import { useTheme } from '@/theme/ThemeProvider'

const THEME_MENU_ITEMS = [
  { value: 'light' as const, labelKey: 'settings.themeLight', Icon: Sun },
  { value: 'dark' as const, labelKey: 'settings.themeDark', Icon: Moon },
  { value: 'system' as const, labelKey: 'settings.themeSystem', Icon: Laptop },
]

export function ThemeMenu() {
  const { t, i18n } = useTranslation()
  const { theme, setTheme, resolvedTheme } = useTheme()
  const qc = useQueryClient()
  const themeValue = theme ?? 'system'
  const dir = i18n.dir()

  const themeIcon = useMemo(() => {
    if (resolvedTheme === 'dark') return <Moon className="size-4" />
    return <Sun className="size-4" />
  }, [resolvedTheme])

  const saveTheme = useCallback(
    (value: 'light' | 'dark' | 'system') => {
      setTheme(value)
      patchBootstrapQuery(qc, { uiTheme: value })
      void apiFetch('settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ui_theme: value }),
      }).catch(() => {})
    },
    [setTheme, qc],
  )

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="outline" size="icon" aria-label={t('settings.theme')}>
          {themeIcon}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-44">
        <div dir={dir} className="text-start">
          {THEME_MENU_ITEMS.map(({ value, labelKey, Icon }) => (
            <DropdownMenuItem
              key={value}
              className="gap-2"
              onSelect={() => saveTheme(value)}
            >
              <Icon className="size-4 shrink-0" />
              <span className="flex-1 text-start">{t(labelKey)}</span>
              {themeValue === value ? <Check className="ms-auto size-4 shrink-0" /> : null}
            </DropdownMenuItem>
          ))}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
