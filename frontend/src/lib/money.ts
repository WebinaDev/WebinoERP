/**
 * Single frontend price handler (parity with App\Support\Money).
 *
 * ERP shop `*_minor` / catalog prices are canonical IRR (rial) integers.
 * Convert for display via {@link formatMoney} / {@link fromRialAmount}.
 * Gateways must receive {@link toGatewayRial} amounts (always IRR).
 */

import {
  BASE_CURRENCY,
  currencyLabel,
  fromRial,
  isForeignCurrency,
  normalizeStoreCurrency,
  SETTLEMENT_CURRENCY,
  toGatewayRial as toGatewayRialShared,
  toRial,
  type StoreCurrencyCode,
} from "@/lib/currencies"
import { formatNumber, normalizeUiLocale } from "@/lib/locale"

export { BASE_CURRENCY, SETTLEMENT_CURRENCY, toGatewayRialShared as toGatewayRial }

let cachedDisplay: StoreCurrencyCode = "IRT"
let cachedFx: Partial<Record<StoreCurrencyCode, number>> = {}

/** Update runtime display currency (from settings / currency_display API). */
export function setDisplayCurrency(code: string | null | undefined): StoreCurrencyCode {
  cachedDisplay = normalizeStoreCurrency(code)
  return cachedDisplay
}

export function getDisplayCurrency(): StoreCurrencyCode {
  return cachedDisplay
}

export function setFxRates(rates: Partial<Record<string, number>>): void {
  const next: Partial<Record<StoreCurrencyCode, number>> = {}
  for (const [k, v] of Object.entries(rates)) {
    const code = normalizeStoreCurrency(k)
    if (isForeignCurrency(code) && typeof v === "number" && v > 0) next[code] = v
  }
  cachedFx = next
}

function fxFor(code: StoreCurrencyCode): number | undefined {
  return cachedFx[code]
}

/** IRR → display units. */
export function fromRialAmount(
  rial: number,
  displayCurrency: string | null | undefined = cachedDisplay
): number {
  const code = normalizeStoreCurrency(displayCurrency)
  try {
    return fromRial(rial || 0, code, fxFor(code))
  } catch {
    // Missing FX: fall back to toman so UI never crashes.
    return fromRial(rial || 0, "IRT")
  }
}

/** Display / arbitrary unit → IRR. */
export function toRialAmount(
  amount: number,
  fromCurrency: string | null | undefined = cachedDisplay
): number {
  const code = normalizeStoreCurrency(fromCurrency)
  return toRial(amount || 0, code, fxFor(code))
}

export type FormatMoneyOptions = {
  /** Amount is already in display units (skip IRR→display). Default false = amount is IRR. */
  alreadyDisplay?: boolean
  currency?: string | null
  locale?: string | null
  /** Omit currency label (number only). */
  bare?: boolean
}

/** Format a money amount for UI. Default: `amount` is IRR (ERP shop convention). */
export function formatMoney(
  amount: number | string | null | undefined,
  opts: FormatMoneyOptions = {}
): string {
  const raw = typeof amount === "string" ? Number(amount) : Number(amount ?? 0)
  const n = Number.isFinite(raw) ? raw : 0
  const code = normalizeStoreCurrency(opts.currency ?? cachedDisplay)
  const display = opts.alreadyDisplay ? n : fromRialAmount(n, code)
  const decimals = isForeignCurrency(code) ? 2 : 0
  const rounded = decimals > 0 ? Number(display.toFixed(decimals)) : Math.round(display)
  const locale = normalizeUiLocale(opts.locale)
  const formatted = formatNumber(rounded, locale)
  if (opts.bare) return formatted
  return `${formatted} ${currencyLabel(code, locale)}`
}

/** Alias used across shop / builder — IRR minor → display string. */
export function formatPriceFromMinor(
  minorRial: number | string | null | undefined,
  locale?: string | null,
  currency?: string | null
): string {
  return formatMoney(minorRial, { locale, currency })
}
