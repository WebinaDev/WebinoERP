import { cookies } from "next/headers"
import { getRequestConfig } from "next-intl/server"

import { defaultLocale, isLocale } from "../../i18n"
import enMessages from "../../messages/en.json"
import faMessages from "../../messages/fa.json"

const catalogs = {
  fa: faMessages,
  en: enMessages,
} as const

export default getRequestConfig(async () => {
  const jar = await cookies()
  const value = jar.get("NEXT_LOCALE")?.value ?? jar.get("locale")?.value
  const locale = value && isLocale(value) ? value : defaultLocale

  return {
    locale,
    messages: catalogs[locale],
    timeZone: process.env.NEXT_PUBLIC_DEFAULT_TIMEZONE?.trim() || "Asia/Tehran",
    onError: () => undefined,
    getMessageFallback: ({ key }) => key,
  }
})
