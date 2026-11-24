"use client"

import { useLocale } from "next-intl"
import type { ReactNode } from "react"

import { CurrencyMark } from "@/components/CurrencyMark"
import {
  currencyLabel,
  currencySymbolText,
  defaultSymbolForCurrency,
  isForeignCurrency,
  normalizeCurrencySymbol,
  normalizeStoreCurrency,
  type CurrencySymbolId,
} from "@/lib/currencies"
import { formatMoney, fromRialAmount } from "@/lib/money"
import { useDisplayCurrency } from "@/lib/display-currency"
import { formatNumber, normalizeUiLocale } from "@/lib/locale"
import { cn } from "@/lib/utils"

type MoneyDisplayProps = {
  /** Amount in canonical IRR (rial) unless `alreadyDisplay` is set. */
  amount: number | string | null | undefined
  /** Override display currency (defaults to settings cache). */
  currency?: string | null
  symbol?: string | null
  alreadyDisplay?: boolean
  className?: string
  amountClassName?: string
  prefix?: ReactNode
  bare?: boolean
}

export function formatMoneyText(
  amount: MoneyDisplayProps["amount"],
  locale: string,
  currency?: string | null,
  alreadyDisplay = false
): string {
  return formatMoney(amount, { locale, currency, alreadyDisplay })
}

export function MoneyDisplay({
  amount,
  currency,
  symbol,
  alreadyDisplay = false,
  className,
  amountClassName,
  prefix,
  bare = false,
}: MoneyDisplayProps) {
  const locale = normalizeUiLocale(useLocale())
  const settings = useDisplayCurrency()
  const code = normalizeStoreCurrency(currency ?? settings.currency)
  const display = alreadyDisplay
    ? Number(amount ?? 0) || 0
    : fromRialAmount(Number(amount ?? 0) || 0, code)
  if (!Number.isFinite(display)) return <span className={className}>-</span>
  const decimals = isForeignCurrency(code) ? 2 : code === "IRT" ? 1 : code === "IRHT" ? 4 : 0
  const symbolId: CurrencySymbolId | null = isForeignCurrency(code)
    ? null
    : symbol
      ? normalizeCurrencySymbol(code, symbol)
      : code === settings.currency
        ? normalizeCurrencySymbol(code, settings.symbol)
        : defaultSymbolForCurrency(code)

  return (
    <span className={cn("inline-flex items-baseline gap-1 whitespace-nowrap", className)} dir="ltr">
      {prefix}
      {symbolId ? (
        <CurrencyMark symbol={symbolId} alt={currencyLabel(code, locale)} />
      ) : bare ? null : (
        <span className="text-[0.85em] text-muted-foreground">{currencySymbolText(code)}</span>
      )}
      <span className={cn("tabular-nums", amountClassName)}>{formatNumber(display, locale, { maximumFractionDigits: decimals })}</span>
      {!symbolId && !bare ? null : null}
    </span>
  )
}
