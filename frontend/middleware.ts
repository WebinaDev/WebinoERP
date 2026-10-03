import { NextResponse } from "next/server"
import type { NextRequest } from "next/server"

import {
  DASHBOARD_BASE,
  isDashboardPathname,
  isLegacyAdminPathname,
  legacyAdminToDashboardPath,
} from "@/lib/paths"
import { getServerApiBase } from "@/lib/server-api-base"

const LOCALES = ["fa", "en"] as const

type GateData = {
  authenticated?: boolean
  setup_completed?: boolean | null
}

async function fetchGate(request: NextRequest): Promise<GateData | null> {
  const origins: string[] = []
  const apiBase = getServerApiBase()
  if (apiBase) origins.push(apiBase)
  const self = request.nextUrl.origin
  if (self && !origins.includes(self)) origins.push(self)

  for (const origin of origins) {
    try {
      const res = await fetch(`${origin}/api/v1/core/auth/gate`, {
        headers: {
          Accept: "application/json",
          Cookie: request.headers.get("cookie") ?? "",
        },
        cache: "no-store",
      })
      if (!res.ok) continue
      const json = (await res.json()) as GateData & { data?: GateData }
      const data: GateData = json.data ?? {
        authenticated: json.authenticated,
        setup_completed: json.setup_completed,
      }
      if (data && typeof data.authenticated === "boolean") {
        return { authenticated: data.authenticated, setup_completed: data.setup_completed }
      }
    } catch {
      /* try next origin */
    }
  }
  return null
}

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl

  // Legacy /admin → /dashboard (bookmarks & old links)
  if (isLegacyAdminPathname(pathname)) {
    const url = request.nextUrl.clone()
    url.pathname = legacyAdminToDashboardPath(pathname)
    return NextResponse.redirect(url)
  }

  const requestHeaders = new Headers(request.headers)
  requestHeaders.set("x-webino-path", `${pathname}${request.nextUrl.search}`)
  const res = NextResponse.next({ request: { headers: requestHeaders } })

  const cookie =
    request.cookies.get("NEXT_LOCALE")?.value ??
    request.cookies.get("locale")?.value
  const locale =
    cookie && LOCALES.includes(cookie as (typeof LOCALES)[number])
      ? cookie
      : "fa"
  if (!request.cookies.get("NEXT_LOCALE")) {
    res.cookies.set("NEXT_LOCALE", locale, {
      path: "/",
      maxAge: 60 * 60 * 24 * 365,
    })
  }
  res.headers.set("x-webina-locale", locale)

  const isLogin = pathname === "/login"
  const isDashboard = isDashboardPathname(pathname)
  const isPublicAsset =
    pathname.startsWith("/_next") ||
    pathname.startsWith("/api") ||
    pathname.includes(".")

  if (isPublicAsset) {
    return res
  }

  if (isDashboard) {
    const gate = await fetchGate(request)
    if (!gate?.authenticated) {
      const loginUrl = request.nextUrl.clone()
      loginUrl.pathname = "/login"
      loginUrl.searchParams.set("next", pathname)
      return NextResponse.redirect(loginUrl)
    }
  }

  if (isLogin) {
    const gate = await fetchGate(request)
    if (gate?.authenticated) {
      const rawNext = request.nextUrl.searchParams.get("next")
      const dest =
        rawNext && rawNext.startsWith("/")
          ? legacyAdminToDashboardPath(rawNext)
          : DASHBOARD_BASE
      const redirectUrl = request.nextUrl.clone()
      redirectUrl.pathname = dest.startsWith("/") ? dest : DASHBOARD_BASE
      redirectUrl.search = ""
      return NextResponse.redirect(redirectUrl)
    }
  }

  return res
}

export const config = {
  matcher: ["/((?!api|_next|_vercel|.*\\..*).*)"],
}
