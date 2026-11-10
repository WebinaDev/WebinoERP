/* Pure variant combo logic (shared by the picker and tests). */

import type { ShopVariant } from "../catalog"

export type VariantPicks = Record<string, string>

/** Attribute names in first-seen order, with their values. */
export function variantAxes(variants: ShopVariant[]): { name: string; values: string[] }[] {
  const axes = new Map<string, string[]>()
  for (const v of variants) {
    for (const [k, val] of Object.entries(v.attributes ?? {})) {
      const list = axes.get(k) ?? []
      if (!list.includes(val)) list.push(val)
      axes.set(k, list)
    }
  }
  return [...axes.entries()].map(([name, values]) => ({ name, values }))
}

export const matches = (v: ShopVariant, picks: VariantPicks) => Object.entries(picks).every(([k, val]) => !val || v.attributes?.[k] === val)

/** Is `value` for `axis` still possible (in stock) with the other picks? */
export function optionAvailable(variants: ShopVariant[], picks: VariantPicks, axis: string, value: string) {
  const others = { ...picks, [axis]: value }
  return variants.some((v) => v.inStock && matches(v, others))
}

/** The single variant matching every axis, or null while picks are incomplete. */
export function resolveVariant(variants: ShopVariant[], picks: VariantPicks): ShopVariant | null {
  const axes = variantAxes(variants)
  if (!axes.length) return null
  if (axes.some((a) => !picks[a.name])) return null
  return variants.find((v) => axes.every((a) => v.attributes?.[a.name] === picks[a.name])) ?? null
}

export function picksFor(variant: ShopVariant | null | undefined): VariantPicks {
  return variant ? { ...(variant.attributes ?? {}) } : {}
}
