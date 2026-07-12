import { useTranslation } from "react-i18next"
import {
  formatCurrency,
  formatDate,
  formatDateTime,
  formatDisplayDate,
  formatNumber,
  getCalendarConfig,
  getLocale,
  isRtlLocale,
  type AppLocale,
} from "@/lib/locale"
import { getConfigOrNull } from "@/api/client"

export function useLocale() {
  const { t, i18n } = useTranslation()
  const config = getConfigOrNull()
  const lang: AppLocale = (i18n.language === "en" ? "en" : "fa") as AppLocale
  const isRtl = config?.isRtl ?? isRtlLocale(lang)

  return {
    t,
    i18n,
    lang,
    isRtl,
    formatNumber: (n: number) => formatNumber(n, lang),
    formatCurrency: (n: number) => formatCurrency(n, lang),
    formatDate: (iso: string, opts?: { includeTime?: boolean }) =>
      formatDate(iso, { lang, includeTime: opts?.includeTime }),
    formatDateTime: (iso: string) => formatDateTime(iso, lang),
    formatDisplayDate: (iso?: string | null, jalali?: string | null) =>
      formatDisplayDate(iso, jalali, lang),
    getCalendarConfig: () => getCalendarConfig(lang),
    getLocale: () => getLocale(),
  }
}
