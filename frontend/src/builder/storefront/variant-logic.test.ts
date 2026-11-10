import assert from "node:assert/strict"
import { test } from "vitest"

import type { ShopVariant } from "../catalog"
import { optionAvailable, resolveVariant, variantAxes } from "./variant-logic"

const v = (id: string, color: string, size: string, inStock = true): ShopVariant => ({ id, name: `${color} ${size}`, price: 1000, attributes: { رنگ: color, سایز: size }, stock: inStock ? 3 : 0, inStock })
const variants = [v("red-s", "قرمز", "S"), v("red-l", "قرمز", "L", false), v("blue-l", "آبی", "L")]

test("axes keep first seen order", () => {
  assert.deepEqual(variantAxes(variants), [
    { name: "رنگ", values: ["قرمز", "آبی"] },
    { name: "سایز", values: ["S", "L"] },
  ])
})

test("combos without an in-stock variant are disabled", () => {
  assert.equal(optionAvailable(variants, { رنگ: "قرمز" }, "سایز", "L"), false)
  assert.equal(optionAvailable(variants, { رنگ: "قرمز" }, "سایز", "S"), true)
  assert.equal(optionAvailable(variants, { رنگ: "آبی" }, "سایز", "S"), false)
  assert.equal(optionAvailable(variants, {}, "سایز", "L"), true)
})

test("resolves a variant only when every axis is picked", () => {
  assert.equal(resolveVariant(variants, { رنگ: "آبی" }), null)
  assert.equal(resolveVariant(variants, { رنگ: "آبی", سایز: "L" })?.id, "blue-l")
})
