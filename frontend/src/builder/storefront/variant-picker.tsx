"use client"

/* Product option picker. Variants with attributes become one row of choices per attribute; a choice
 * that cannot make an in-stock variant together with the other picks is disabled. Variants without
 * attributes are shown as one list of chips (out of stock ones disabled). */

import { useTranslations } from "next-intl"
import { useEffect, useMemo, useState } from "react"

import type { ShopVariant } from "../catalog"

import { matches, optionAvailable, picksFor, resolveVariant, variantAxes, type VariantPicks } from "./variant-logic"

export { optionAvailable, picksFor, resolveVariant, variantAxes, type VariantPicks }

export function VariantPicker({
  variants,
  value,
  onChange,
  className,
}: {
  variants: ShopVariant[]
  /** Selected variant id (string key from the API). */
  value: string | null
  onChange: (variantId: string | null, picks: VariantPicks) => void
  className?: string
}) {
  const t = useTranslations("storefront")
  const axes = useMemo(() => variantAxes(variants), [variants])
  const selected = variants.find((v) => v.id === value) ?? null
  // Partial picks (e.g. colour chosen, size not yet) live here; a chosen variant overrides them.
  const [picks, setPicks] = useState<VariantPicks>(() => picksFor(selected))
  useEffect(() => {
    if (selected) setPicks(picksFor(selected))
  }, [selected])
  if (!variants.length) return null

  if (!axes.length) {
    return (
      <div className={className} role="radiogroup" aria-label={t("select_options")}>
        <div className="flex flex-wrap gap-2">
          {variants.map((v) => (
            <button
              key={v.id}
              type="button"
              role="radio"
              aria-checked={value === v.id}
              disabled={!v.inStock}
              title={!v.inStock ? t("variant_unavailable") : undefined}
              onClick={() => onChange(v.id, {})}
              className={`rounded-full border px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40 disabled:line-through ${value === v.id ? "border-primary bg-primary text-primary-foreground" : "border-border bg-card hover:border-primary"}`}
            >
              {v.swatch?.color ? <span className="me-2 inline-block size-3 rounded-full align-middle" style={{ background: v.swatch.color }} /> : null}
              {v.name}
            </button>
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className={`grid gap-3 ${className ?? ""}`}>
      {axes.map((axis) => (
        <div key={axis.name} role="radiogroup" aria-label={t("variant_pick", { name: axis.name })}>
          <div className="mb-1.5 text-sm font-bold">
            {axis.name}
            {picks[axis.name] ? <span className="ms-1 font-normal text-muted-foreground">{picks[axis.name]}</span> : null}
          </div>
          <div className="flex flex-wrap gap-2">
            {axis.values.map((val) => {
              const on = picks[axis.name] === val
              const ok = optionAvailable(variants, { ...picks, [axis.name]: "" }, axis.name, val)
              const swatch = variants.find((v) => v.attributes?.[axis.name] === val && v.swatch?.color)?.swatch?.color
              return (
                <button
                  key={val}
                  type="button"
                  role="radio"
                  aria-checked={on}
                  disabled={!ok}
                  title={!ok ? t("variant_unavailable") : undefined}
                  onClick={() => {
                    const next = { ...picks, [axis.name]: val }
                    // Drop picks that no longer fit, so the shopper is never stuck on an impossible combo.
                    for (const other of axes) {
                      if (other.name !== axis.name && next[other.name] && !optionAvailable(variants, next, other.name, next[other.name])) delete next[other.name]
                    }
                    // A single remaining in-stock match for the other axes is picked for them.
                    const candidates = variants.filter((v) => v.inStock && matches(v, next))
                    if (candidates.length === 1) Object.assign(next, candidates[0].attributes)
                    const hit = resolveVariant(variants, next)
                    setPicks(next)
                    onChange(hit && hit.inStock ? hit.id : null, next)
                  }}
                  className={`rounded-full border px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40 disabled:line-through ${on ? "border-primary bg-primary text-primary-foreground" : "border-border bg-card hover:border-primary"}`}
                >
                  {swatch ? <span className="me-2 inline-block size-3 rounded-full align-middle" style={{ background: swatch }} /> : null}
                  {val}
                </button>
              )
            })}
          </div>
        </div>
      ))}
    </div>
  )
}
