"use client"

import { useQueryClient } from "@tanstack/react-query"
import { Check, Palette } from "lucide-react"
import { useTranslations } from "next-intl"
import { useCallback, useEffect, useState } from "react"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useBootstrapQuery } from "@/hooks/useBootstrapQuery"
import { apiFetch } from "@/lib/api"
import {
  ACCENT_MENU_ITEMS,
  ACCENT_SWATCH,
  isBusinessAccent,
  normalizeAccent,
  type AccentPreset,
} from "@/lib/accent"
import { patchBootstrapQuery } from "@/lib/bootstrapQuery"
import { useTextDirection } from "@/hooks/use-text-direction"
import { cn } from "@/lib/utils"

function AccentSwatch({ value, className }: { value: AccentPreset; className?: string }) {
  return (
    <span
      className={cn("shrink-0 rounded-full ring-1 ring-border", className)}
      style={{ background: ACCENT_SWATCH[value] }}
      aria-hidden
    />
  )
}

export function AccentMenu() {
  const t = useTranslations()
  const dir = useTextDirection()
  const qc = useQueryClient()
  const bq = useBootstrapQuery()

  const [accent, setAccent] = useState<AccentPreset>("colorful")

  useEffect(() => {
    setAccent(normalizeAccent(bq.data?.uiAccent))
  }, [bq.data?.uiAccent])

  const saveAccent = useCallback(
    (value: AccentPreset) => {
      setAccent(value)
      document.documentElement.setAttribute("data-accent", value)
      try {
        localStorage.setItem("theme_accent", value)
      } catch {
        /* ignore */
      }
      patchBootstrapQuery(qc, { uiAccent: value })
      void apiFetch("settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ui_accent: value }),
      }).catch(() => {})
    },
    [qc],
  )

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="relative"
          aria-label={t("settings.accent")}
        >
          <Palette className="size-4" />
          <AccentSwatch value={accent} className="absolute end-1.5 bottom-1.5 size-2" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-48">
        <div dir={dir} className="text-start">
          {ACCENT_MENU_ITEMS.map((item) => (
            <DropdownMenuItem
              key={item.value}
              className="gap-2"
              onSelect={() => saveAccent(item.value)}
            >
              <AccentSwatch
                value={item.value}
                className={cn(
                  "size-3.5",
                  isBusinessAccent(item.value) && "ring-primary/30 shadow-sm",
                )}
              />
              <span className="flex-1 text-start">{t(item.labelKey)}</span>
              {accent === item.value ? <Check className="ms-auto size-4 shrink-0" /> : null}
            </DropdownMenuItem>
          ))}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
