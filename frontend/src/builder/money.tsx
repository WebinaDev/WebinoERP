"use client"

/* Shared storefront price for every builder widget: dashboard currency + symbol glyph (same API as
 * WebinoDashboard builder/money.tsx). ERP API amounts are IRR: pass from="IRR"; manual widget
 * prices are typed in the display currency (no `from`). */

import { createContext, createElement, useContext, useEffect, useState, type ReactNode } from "react"

import { CurrencyMark } from "@/components/CurrencyMark"
import { currencyLabel, isForeignCurrency, normalizeCurrencySymbol, normalizeStoreCurrency, rialFactor, type CurrencySymbolId, type StoreCurrencyCode } from "@/lib/currencies"
import { applyCurrencyDisplay, getDisplayCurrencyState, useDisplayCurrency } from "@/lib/display-currency"
import { fromRialAmount } from "@/lib/money"

export type BuilderCurrency = {
  currency: StoreCurrencyCode
  symbol: CurrencySymbolId
  thousandSeparator: string
}

export type PublicCurrencyDisplay = {
  currency?: string | null
  currency_symbol?: string | null
  thousand_separator?: string | null
}

export function resolveBuilderCurrency(raw?: PublicCurrencyDisplay | null): BuilderCurrency {
  const currency = normalizeStoreCurrency(raw?.currency ?? "IRT")
  return {
    currency,
    symbol: normalizeCurrencySymbol(currency, raw?.currency_symbol),
    thousandSeparator: typeof raw?.thousand_separator === "string" && raw.thousand_separator ? raw.thousand_separator : ",",
  }
}

const BuilderCurrencyContext = createContext<BuilderCurrency | null>(null)

export function BuilderCurrencyProvider({ value, children }: { value: BuilderCurrency | null; children: ReactNode }) {
  return createElement(BuilderCurrencyContext.Provider, { value }, children)
}

let pending: Promise<void> | null = null
let separator = ","

/** Loads the dashboard display currency (GET /api/v1/public/currency) once per page. */
function ensurePublicCurrency(): Promise<void> {
  if (!pending) {
    pending = fetch("/api/v1/public/currency", { credentials: "same-origin" })
      .then((res) => (res.ok ? res.json() : null))
      .then((json: { data?: PublicCurrencyDisplay } | null) => {
        if (json?.data) {
          if (typeof json.data.thousand_separator === "string" && json.data.thousand_separator) separator = json.data.thousand_separator
          applyCurrencyDisplay(json.data)
        }
      })
      .catch(() => undefined)
  }
  return pending
}

/** Context (SSR seed) first, then the shared display-currency store (filled from the public API). */
export function useBuilderCurrency(): BuilderCurrency {
  const ctx = useContext(BuilderCurrencyContext)
  const state = useDisplayCurrency()
  useEffect(() => {
    if (!ctx) void ensurePublicCurrency()
  }, [ctx])
  if (ctx) return ctx
  return { currency: state.currency, symbol: state.symbol, thousandSeparator: separator }
}

const FA = "۰۱۲۳۴۵۶۷۸۹"

/** Latin or Persian digits / separators in, number out (0 when not a positive amount). */
export function parseAmount(raw: string | number | null | undefined): number {
  if (raw == null || raw === "") return 0
  if (typeof raw === "number") return Number.isFinite(raw) && raw > 0 ? raw : 0
  const latin = raw.replace(/[۰-۹]/g, (d) => String(FA.indexOf(d))).replace(/[٠-٩]/g, (d) => String("٠١٢٣٤٥٦٧٨٩".indexOf(d)))
  const n = Number(latin.replace(/[^\d]/g, ""))
  return Number.isFinite(n) && n > 0 ? n : 0
}

/** Converts between units: IRR/IRT/... via rial factors, foreign codes via the dashboard FX rates. */
export function convertAmount(amount: number, from: string | null | undefined, to: string): number {
  if (!from) return amount
  const f = normalizeStoreCurrency(from)
  const t = normalizeStoreCurrency(to)
  if (f === t) return amount
  if (isForeignCurrency(t) || isForeignCurrency(f)) {
    if (f !== "IRR" && !isForeignCurrency(f)) return fromRialAmount(amount * rialFactor(f), t)
    return f === "IRR" ? fromRialAmount(amount, t) : amount
  }
  return (amount * rialFactor(f)) / rialFactor(t)
}

export function formatAmountDigits(amount: number, thousandSeparator = ","): string {
  const grouped = Math.round(Math.max(0, amount))
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator === "," ? "٬" : thousandSeparator)
  return grouped.replace(/\d/g, (d) => FA[Number(d)])
}

/** Plain-text fallback for attributes and translated strings (no glyph possible). */
export function formatMoneyPlain(amount: number, cur: BuilderCurrency, from?: string | null): string {
  const n = convertAmount(amount, from, cur.currency)
  if (!(n > 0)) return ""
  return `${formatAmountDigits(n, cur.thousandSeparator)} ${currencyLabel(cur.currency, "fa")}`
}

export function BuilderPrice({
  amount,
  original,
  from,
  className,
}: {
  /** Amount in `from` units (defaults to the store currency, i.e. no conversion). Strings may carry Persian digits. */
  amount: number | string | null | undefined
  /** Price before discount: rendered struck through when it is higher than `amount`. */
  original?: number | string | null
  from?: string | null
  className?: string
}) {
  const cur = useBuilderCurrency()
  const n = convertAmount(parseAmount(amount), from, cur.currency)
  if (!(n > 0)) return null
  const o = convertAmount(parseAmount(original), from, cur.currency)
  const price = (
    <span className={["wb-price", className].filter(Boolean).join(" ")} dir="ltr" style={{ display: "inline-flex", alignItems: "baseline", gap: "0.25em", whiteSpace: "nowrap" }}>
      <CurrencyMark symbol={cur.symbol} alt={currencyLabel(cur.currency, "fa")} />
      <span style={{ fontVariantNumeric: "tabular-nums" }}>{formatAmountDigits(n, cur.thousandSeparator)}</span>
    </span>
  )
  if (!(o > n)) return price
  return (
    <span className="wb-price-group" style={{ display: "inline-flex", alignItems: "baseline", gap: "0.5em", flexWrap: "wrap" }}>
      <s className="wb-price-original" aria-label="قیمت قبل از تخفیف" style={{ opacity: 0.55, fontSize: "0.85em", whiteSpace: "nowrap" }} dir="ltr">
        {formatAmountDigits(o, cur.thousandSeparator)}
      </s>
      {price}
    </span>
  )
}

/** Sale price and the regular price it replaces, from any API product/line shape. */
export function salePair(p: Record<string, unknown> | null | undefined): { amount: number; original: number } {
  if (!p) return { amount: 0, original: 0 }
  const num = (v: unknown) => parseAmount(v as string | number | null | undefined)
  const amount =
    num(p.unit_price_minor) || num(p.sale_price_minor) || num(p.discounted_price_minor) || num(p.final_price_minor) || num(p.price_minor) || num(p.price)
  const original = num(p.regular_price_minor) || num(p.compare_at_price_minor) || num(p.original_price_minor) || num(p.price_minor)
  return { amount, original: original > amount ? original : 0 }
}

/** `money(amount)` for JSX (glyph) and `money.text(amount)` for string-only slots. */
export function useBuilderMoney(from?: string | null) {
  const cur = useBuilderCurrency()
  const money = (amount: number | string | null | undefined, original?: number | string | null): ReactNode => (
    <BuilderPrice amount={amount} original={original} from={from} />
  )
  money.text = (amount: number | string | null | undefined) => formatMoneyPlain(parseAmount(amount), cur, from)
  money.currency = cur
  return money
}

/** Manual price fields: a bare number renders as a priced amount, anything else (e.g. «تماس بگیرید») stays text. */
export function PriceOrText({ value, from, className }: { value: string | number | null | undefined; from?: string | null; className?: string }) {
  if (value == null || value === "") return null
  const raw = String(value).trim()
  if (/^[\d۰-۹٠-٩\s,٬.،]+$/.test(raw) && parseAmount(raw) > 0) return <BuilderPrice amount={raw} from={from} className={className} />
  return <>{raw}</>
}

/** Widget label field: empty or the old default «تومان» follows the dashboard currency. */
export function currencyText(label: string, currency: StoreCurrencyCode): string {
  const v = label.trim()
  return !v || v === "تومان" ? currencyLabel(currency, "fa") : v
}

/** Current display currency outside React (e.g. string-only helpers). */
export function builderCurrencySnapshot(): BuilderCurrency {
  const s = getDisplayCurrencyState()
  return { currency: s.currency, symbol: s.symbol, thousandSeparator: separator }
}
