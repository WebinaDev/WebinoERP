"use client"

/* Builder-facing cart API. Backed by the real Shop (Sales storefront) cart on the server, via AJAX.
 * Kept the old function names so product, course and theme widgets keep working.
 * `price` is canonical IRR (rial) — format via `@/lib/money`. */

import { addToShopCart, cartState, refreshCart, removeLine, setLineQty, subscribeShopCart, type ShopCart } from "@/features/shop/shop-api"

export type CartLine = {
  slug: string
  name: string
  /** IRR (rial) unit price. */
  price: number
  qty: number
  tone: string
  image?: string | null
  productId?: number
  /** Chosen variant key; required by the server for products with several variants. */
  variantId?: string | null
  variantName?: string | null
  /** Server cart line id (per product and variant) and a stable key for lists and qty updates. */
  lineId?: number
  key?: string
  /** Regular unit price when the line is on sale (IRR), for strike-through. */
  compare?: number
}

let lastCart: ShopCart | null | undefined
let lastLines: CartLine[] = []

function lines(): CartLine[] {
  const cart = cartState().cart
  if (cart === lastCart) return lastLines
  lastCart = cart
  lastLines = (cart?.items ?? []).map((l) => ({
    slug: l.product.slug,
    name: l.product.name,
    price: Math.round(l.unit_price_minor),
    qty: l.quantity,
    tone: "#0054ff",
    image: l.product.cover_image_url || l.product.image_url || null,
    productId: l.product_id,
    variantId: l.variant_id || null,
    variantName: l.variant_name || null,
    lineId: l.id,
    key: `line:${l.id}`,
    compare: l.regular_price_minor && l.regular_price_minor > l.unit_price_minor ? Math.round(l.regular_price_minor) : undefined,
  }))
  return lastLines
}

export function cartSnapshot(): CartLine[] {
  return lines()
}

const EMPTY_CART: CartLine[] = []

/** Server snapshot for useSyncExternalStore: a constant reference (an inline `() => []` loops, React #185). */
export function cartServerSnapshot(): CartLine[] {
  return EMPTY_CART
}

export function cartCount(): number {
  return lines().reduce((sum, line) => sum + line.qty, 0)
}

export function subscribeCart(listener: () => void): () => void {
  return subscribeShopCart(listener)
}

/** AJAX add: the server resolves the shop product by slug (or id) and owns the price. */
/** Products with several variants need a choice: the server answers 422 on `variant_id` and the shopper goes to the product page. */
export async function addToCart(line: Omit<CartLine, "qty">, qty = 1) {
  const res = await addToShopCart({ slug: line.slug, productId: line.productId, name: line.name, variantId: line.variantId }, qty)
  if (!res.ok && res.status === 422 && !line.variantId && res.errors?.variant_id && line.slug && typeof window !== "undefined") {
    window.location.assign(`/product/${encodeURIComponent(line.slug)}`)
  }
  return res
}

/** `key` is a line key (`line:{id}`) or, for older callers, a product slug (first matching line). */
export function setQty(key: string, qty: number) {
  const found = lines().find((l) => l.key === key) ?? lines().find((l) => l.slug === key)
  if (!found?.lineId) return
  void (qty > 0 ? setLineQty(found.lineId, qty) : removeLine(found.lineId))
}

export function clearCart() {
  void Promise.all(lines().map((l) => (l.lineId ? removeLine(l.lineId) : null))).then(() => refreshCart())
}
