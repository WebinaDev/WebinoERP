"use client"

import { useLocale } from "@/hooks/use-locale"

/**
 * Returns the text direction ("rtl" | "ltr") based on the current locale.
 * Used by UI components (e.g. Popover, Select, Tooltip) to set the `dir` attribute.
 */
export function useTextDirection(): "rtl" | "ltr" {
  const { isRtl } = useLocale()
  return isRtl ? "rtl" : "ltr"
}
