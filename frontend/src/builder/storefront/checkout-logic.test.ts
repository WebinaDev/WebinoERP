import assert from "node:assert/strict"
import { test } from "vitest"

import { nextStepAfter, visibleSteps } from "./checkout-logic"

const steps = [
  { key: "address" as const, enabled: true, order: 1 },
  { key: "delivery_time" as const, enabled: true, order: 2 },
  { key: "shipping" as const, enabled: true, order: 3 },
  { key: "payment" as const, enabled: true, order: 4 },
]

test("digital carts skip shipping and delivery time", () => {
  assert.deepEqual(visibleSteps({ steps }, { requires_shipping: false }), ["address", "payment"])
})

test("delivery time shows only when the picked method needs it", () => {
  assert.deepEqual(visibleSteps({ steps }, { requires_shipping: true, delivery_time_required: false }), ["address", "shipping", "payment"])
  assert.deepEqual(visibleSteps({ steps }, { requires_shipping: true, delivery_time_required: true }), ["address", "delivery_time", "shipping", "payment"])
})

test("after shipping, an open delivery time step comes before payment", () => {
  const visible = visibleSteps({ steps }, { requires_shipping: true, delivery_time_required: true })
  assert.equal(nextStepAfter(visible, ["address", "shipping"], "shipping", "payment"), "delivery_time")
  assert.equal(nextStepAfter(visible, ["address", "shipping", "delivery_time"], "delivery_time", "shipping"), "payment")
  assert.equal(nextStepAfter(["address", "payment"], [], "address", "payment"), "payment")
})
