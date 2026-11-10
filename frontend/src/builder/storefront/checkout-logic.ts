/* Pure stepped-checkout helpers (shared by the UI and tests). */

export type StepKey = "address" | "delivery_time" | "shipping" | "payment"

type StepRow = { key: StepKey; enabled: boolean; order: number }

/** Steps the shopper actually walks for this cart (digital carts skip shipping and delivery time). */
export function visibleSteps(config: { steps: StepRow[] }, totals: { requires_shipping: boolean; delivery_time_required?: boolean } | null): StepKey[] {
  const physical = totals ? totals.requires_shipping : true
  return [...config.steps]
    .filter((s) => s.enabled)
    .sort((a, b) => a.order - b.order)
    .map((s) => s.key)
    .filter((k) => (physical ? true : k !== "shipping" && k !== "delivery_time"))
    .filter((k) => k !== "delivery_time" || !totals || totals.delivery_time_required !== false)
}

/**
 * Where to go after saving `current`: the earliest visible step not done yet (e.g. delivery time that
 * became required after picking a shipping method), else the server's `next_step`, else the next visible step.
 */
export function nextStepAfter(visible: StepKey[], done: StepKey[], current: StepKey, serverNext: StepKey | null): StepKey | null {
  const open = visible.find((k) => k !== current && !done.includes(k))
  if (open) return open
  if (serverNext && visible.includes(serverNext)) return serverNext
  const i = visible.indexOf(current)
  return i >= 0 && i + 1 < visible.length ? visible[i + 1] : null
}
