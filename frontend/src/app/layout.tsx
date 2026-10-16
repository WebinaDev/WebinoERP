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

// Admin-only translation namespaces. Public pages never render these client
// components, so they are not shipped in the public HTML (about half of fa.json).
const ADMIN_ONLY_NAMESPACES = [
  "hrm", "aiContent", "marketplace", "modirpayamak", "platform", "pm", "expansion", "crm",
  "bots", "wfcp", "hosting", "siteBuilder", "theme_builder", "builder_collections", "builder_globals",
  "bale", "mfg", "scm", "analytics", "finance", "licenses", "license", "marketing",
]

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
      ? Object.fromEntries(Object.entries(allMessages).filter(([ns]) => !ADMIN_ONLY_NAMESPACES.includes(ns)))
      : allMessages
  const timeZone = await getTimeZone()
  const dir = htmlDir(locale)
  const apiOrigin = getApiOrigin()

  return (
    <html lang={locale} dir={dir} className={yekanBakh.variable} suppressHydrationWarning>
      <head>
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
