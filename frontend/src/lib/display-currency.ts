"use client"

import { useEffect, useState } from "react"

import {
  normalizeCurrencySymbol,
  normalizeStoreCurrency,
  type CurrencySymbolId,
  type StoreCurrencyCode,
} from "@/lib/currencies"
import { setDisplayCurrency, setFxRates } from "@/lib/money"

export type DisplayCurrencyState = {
  currency: StoreCurrencyCode
  symbol: CurrencySymbolId
  label: string
  base: string
  settlement: string
}

const DEFAULT: DisplayCurrencyState = {
  currency: "IRT",
  symbol: "default",
  label: "تومان",
  base: "IRR",
  settlement: "IRR",
}

let memory: DisplayCurrencyState = DEFAULT
const listeners = new Set<() => void>()

function publish(next: DisplayCurrencyState) {
  memory = next
  setDisplayCurrency(next.currency)
  listeners.forEach((l) => l())
}

/** Apply currency_display blob from public catalog / settings APIs. */
export function applyCurrencyDisplay(raw: unknown): DisplayCurrencyState {
  // Catalog payloads may send a bare code ("IRT"); keep the current symbol in that case.
  const row: Record<string, unknown> = typeof raw === "string" && raw.trim()
    ? { currency: raw, currency_symbol: getDisplayCurrencyState().symbol }
    : raw && typeof raw === "object" ? (raw as Record<string, unknown>) : {}
  const currency = normalizeStoreCurrency(String(row.currency ?? row.currency_display ?? "IRT"))
  const symbol = normalizeCurrencySymbol(currency, String(row.currency_symbol ?? "default"))
  const next: DisplayCurrencyState = {
    currency,
    symbol,
    label: String(row.label ?? row.label_fa ?? ""),
    base: String(row.base ?? "IRR"),
    settlement: String(row.settlement ?? "IRR"),
  }
  if (row.fx_rates && typeof row.fx_rates === "object") {
    setFxRates(row.fx_rates as Record<string, number>)
  }
  publish(next)
  return next
}

export function getDisplayCurrencyState(): DisplayCurrencyState {
  return memory
}

/** Subscribe to display currency for storefront widgets. */
export function useDisplayCurrency(): DisplayCurrencyState {
  const [state, setState] = useState(memory)
  useEffect(() => {
    const onChange = () => setState(memory)
    listeners.add(onChange)
    return () => {
      listeners.delete(onChange)
    }
  }, [])
  return state
}
