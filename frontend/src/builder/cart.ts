"use client"

/* Builder-facing cart API. Backed by the real Shop (Sales storefront) cart on the server, via AJAX.
 * Kept the old function names so product, course and theme widgets keep working. `price` is in toman. */

import { addToShopCart, cartState, refreshCart, removeShopItem, setShopQty, subscribeShopCart, type ShopCart } from "@/features/shop/shop-api"

export type CartLine = {
  slug: string
  name: string
  price: number
  qty: number
  tone: string
  image?: string | null
  productId?: number
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
    price: Math.round(l.unit_price_minor / 10),
    qty: l.quantity,
    tone: "#0054ff",
    image: l.product.cover_image_url || l.product.image_url || null,
    productId: l.product_id,
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
export function addToCart(line: Omit<CartLine, "qty">, qty = 1) {
  void addToShopCart({ slug: line.slug, productId: line.productId, name: line.name }, qty)
}

export function setQty(slug: string, qty: number) {
  const found = lines().find((l) => l.slug === slug)
  if (!found?.productId) return
  void (qty > 0 ? setShopQty(found.productId, qty) : removeShopItem(found.productId))
}

export function clearCart() {
  void Promise.all(lines().map((l) => (l.productId ? removeShopItem(l.productId) : null))).then(() => refreshCart())
}
