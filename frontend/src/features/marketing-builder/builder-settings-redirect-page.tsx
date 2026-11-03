"use client"

import { useEffect } from "react"
import { useRouter } from "next/navigation"

import { builderGlobalsHref } from "@/lib/page-builder-nav"

export function BuilderSettingsRedirectPage() {
  const router = useRouter()
  useEffect(() => {
    router.replace(builderGlobalsHref())
  }, [router])
  return <p className="text-muted-foreground p-6 text-sm">…</p>
}
