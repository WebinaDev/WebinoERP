"use client"

import { useEffect } from "react"
import { useRouter } from "next/navigation"

/** The legacy «منوها» page was retired; its data now lives in «منوهای ناوبری». */
export default function LegacyMenusRedirect() {
  const router = useRouter()
  useEffect(() => {
    router.replace("/dashboard/marketing/nav-menus")
  }, [router])
  return <div dir="rtl" className="p-6 text-sm text-muted-foreground">انتقال به منوهای ناوبری…</div>
}
