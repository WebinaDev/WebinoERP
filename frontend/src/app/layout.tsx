import type { Metadata, Viewport } from "next"
import { NextIntlClientProvider } from "next-intl"
import { getLocale, getMessages, getTimeZone } from "next-intl/server"
import { headers } from "next/headers"

import { yekanBakh } from "@/lib/fonts/yekan-bakh"
import { htmlDir } from "@/lib/locale"
import { getApiOrigin } from "@/lib/api-origin"
import { Toaster } from "@/components/ui/sonner"
import { AppProviders } from "@/providers/AppProviders"
import { QueryProvider } from "@/providers/QueryProvider"
import type { Locale } from "../../i18n"

import "./globals.css"
import "@webina/ui/styles/hide-on.css"

// Public pages only ship the namespaces their client components use (found by
// walking the import graph of app/(site)); the dashboard and login layouts
// re-provide the full catalogue. Cuts ~100 KB from every public page's HTML.
const PUBLIC_NAMESPACES = [
  "auto", "builder", "cart", "checkout", "date", "enums", "settings", "site", "storefront",
  "storefront_badges", "common", "ui", "errors", "app",
]

// "auto" holds per-file strings; public client code only needs these files' strings.
const PUBLIC_AUTO = ["app_error", "date_picker_jalali", "_industry___slug__page", "_site___slug__page", "magazine__slug__page", "services__slug__page", "solutions__industry__page"]

function publicMessages(all: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {}
  for (const ns of PUBLIC_NAMESPACES) {
    if (!(ns in all)) continue
    if (ns === "auto" && all.auto && typeof all.auto === "object") {
      const auto = all.auto as Record<string, unknown>
      out.auto = Object.fromEntries(PUBLIC_AUTO.filter((k) => k in auto).map((k) => [k, auto[k]]))
    } else {
      out[ns] = all[ns]
    }
  }
  return out
}

function isAdminPath(path: string): boolean {
  return path.startsWith("/dashboard") || path.startsWith("/login") || path.startsWith("/admin")
}

export const metadata: Metadata = {
  title: "Webino ERP",
  description: "Integrated business management platform",
  icons: {
    icon: [{ url: "/brand/favicon.png", type: "image/png" }],
    apple: "/brand/apple-touch-icon.png",
  },
  manifest: "/manifest.webmanifest",
  generator: "Webina ERP",
  appleWebApp: { capable: true, title: "Webino ERP" },
}

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#ffffff" },
    { media: "(prefers-color-scheme: dark)", color: "#09090b" },
  ],
}

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  const locale = (await getLocale()) as Locale
  const allMessages = await getMessages()
  const requestPath = (await headers()).get("x-webino-path") ?? ""
  const messages =
    requestPath && !isAdminPath(requestPath)
      ? (publicMessages(allMessages as Record<string, unknown>) as typeof allMessages)
      : allMessages
  const timeZone = await getTimeZone()
  const dir = htmlDir(locale)
  const apiOrigin = getApiOrigin()

  return (
    <html lang={locale} dir={dir} className={yekanBakh.variable} suppressHydrationWarning>
      <head>
        {/* Only the two weights used above the fold; the rest load on demand via @font-face (display: swap). */}
        <link rel="preload" href="/fonts/yekan-bakh/woff2/YekanBakh-Regular.woff2" as="font" type="font/woff2" crossOrigin="anonymous" />
        <link rel="preload" href="/fonts/yekan-bakh/woff2/YekanBakh-Bold.woff2" as="font" type="font/woff2" crossOrigin="anonymous" />
        {/* Hero H1 (the mobile LCP element) is weight 800; without this it was found only after CSS parse and its swap repaint set LCP. */}
        <link rel="preload" href="/fonts/yekan-bakh/woff2/YekanBakh-ExtraBold.woff2" as="font" type="font/woff2" crossOrigin="anonymous" />
        <script dangerouslySetInnerHTML={{ __html: 'window.__WEBINA__={product:"erp"};window.Webina=window.__WEBINA__' }} />
        {apiOrigin ? (
          <>
            <link rel="preconnect" href={apiOrigin} />
            <link rel="dns-prefetch" href={apiOrigin} />
          </>
        ) : null}
      </head>
      <body className="min-h-svh font-sans antialiased">
        <NextIntlClientProvider locale={locale} messages={messages} timeZone={timeZone}>
          <QueryProvider>
            <AppProviders>
              {children}
              <Toaster richColors position={dir === "rtl" ? "top-left" : "top-right"} />
            </AppProviders>
          </QueryProvider>
        </NextIntlClientProvider>
      </body>
    </html>
  )
}
