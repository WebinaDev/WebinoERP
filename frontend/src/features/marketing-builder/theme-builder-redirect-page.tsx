"use client"

import { useEffect } from "react"
import { useRouter } from "next/navigation"

import { builderThemeSettingsHref } from "@/lib/page-builder-nav"

export function ThemeBuilderRedirectPage() {
  const router = useRouter()
  useEffect(() => {
    router.replace(builderThemeSettingsHref())
  }, [router])
  return <p className="text-muted-foreground p-6 text-sm">…</p>
}
