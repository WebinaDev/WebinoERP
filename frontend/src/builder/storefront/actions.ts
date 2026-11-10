"use client"

import { useCallback, useEffect, useState } from "react"

import { addToCart } from "../cart"
import type { ShopProduct } from "../catalog"
import { CART_EVENT, notifyCart, quietApi } from "./session"

export type ServerCartItem = {
  id: number
  quantity: number
  variant_id?: string | null
  variant_name?: string | null
  regular_price_minor?: number | null
  unit_price_minor?: number
  on_sale?: boolean
  stock?: number | null
  product: {
    id: number
    name: string
    price_minor: number
    slug?: string
    image_url?: string | null
    cover_image_url?: string | null
  }
}

export type ServerCartPricing = {
  active?: boolean
  purchase_type?: string
  installment_months?: number | null
  installment_monthly_minor?: number | null
  available_types?: string[]
  installment_plans?: { months: number; interest?: number }[]
  subtotal_minor?: number
  lines?: { id: number; unit_price_minor: number; line_total_minor: number }[]
}

export type ServerCart = {
  id: number
  items: ServerCartItem[]
  pricing?: ServerCartPricing
}

export function useServerCart() {
  const [cart, setCart] = useState<ServerCart | null>(null)
  const [auth, setAuth] = useState<"loading" | "auth" | "guest">("loading")

  const reload = useCallback(async () => {
    const res = await quietApi<ServerCart>("/api/v1/cart")
    if (!res.ok) {
      setAuth(res.status === 401 || res.status === 403 ? "guest" : "guest")
      setCart(null)
      return
    }
    setAuth("auth")
    setCart(res.data)
  }, [])

  useEffect(() => {
    void reload()
    // Never leave the cart on "loading": a hung request falls back to the guest cart.
    const timer = window.setTimeout(() => setAuth((a) => (a === "loading" ? "guest" : a)), 8000)
    return () => window.clearTimeout(timer)
  }, [reload])

  useEffect(() => {
    const onChange = () => void reload()
    window.addEventListener(CART_EVENT, onChange)
    return () => window.removeEventListener(CART_EVENT, onChange)
  }, [reload])

  return { cart, auth, reload }
}

/** Where a shopper picks options for a product that has several variants. */
export function productHref(product: Pick<ShopProduct, "slug">) {
  return `/product/${encodeURIComponent(product.slug)}`
}

/** Variant the server would pick on its own: the default one, or the only one. */
export function implicitVariant(product: Pick<ShopProduct, "variants">) {
  const list = product.variants ?? []
  return list.find((v) => v.isDefault && v.inStock) ?? (list.length === 1 ? list[0] : null)
}

/**
 * Add to the shop cart. Products with several variants need `variantId`; without one (from a card)
 * the shopper is sent to the product page to choose ("choose").
 */
export async function addShopItem(product: ShopProduct, qty = 1, variantId?: string | null): Promise<"server" | "local" | "error" | "choose"> {
  const safeQty = Math.max(1, Math.min(99, Math.round(qty)))
  const chosen = variantId || implicitVariant(product)?.id || null
  if (!chosen && (product.variants?.length ?? 0) > 1) {
    if (typeof window !== "undefined") window.location.assign(productHref(product))
    return "choose"
  }
  if (product.id) {
    const res = await quietApi<ServerCart>("/api/v1/cart/items", {
      method: "POST",
      json: { product_id: product.id, quantity: safeQty, ...(chosen ? { variant_id: chosen } : {}) },
    })
    if (res.ok) {
      notifyCart()
      return "server"
    }
    if (res.status !== 401 && res.status !== 403) return "error"
  }
  const res = await addToCart(
    {
      slug: product.slug,
      name: product.name,
      price: product.price,
      tone: product.tone,
      image: product.image,
      productId: product.id,
      variantId: chosen,
    },
    safeQty,
  )
  notifyCart()
  return res.ok ? "local" : "error"
}

export async function setServerQty(productId: number, quantity: number) {
  const res = await quietApi<ServerCart>(`/api/v1/cart/items/${productId}`, {
    method: "PUT",
    json: { quantity: Math.max(0, Math.min(999, Math.round(quantity))) },
  })
  if (res.ok) notifyCart()
  return res
}

/** Per-line quantity (0 removes the line); keeps variant lines of one product apart. */
export async function setServerLineQty(lineId: number, quantity: number) {
  const q = Math.max(0, Math.min(999, Math.round(quantity)))
  const res = await quietApi<ServerCart>(`/api/v1/cart/lines/${lineId}`, q > 0 ? { method: "PUT", json: { quantity: q } } : { method: "DELETE" })
  if (res.ok) notifyCart()
  return res
}

/** Guest carts live on the server now (X-Cart-Token) and are merged on sign-in; nothing to sync. */
export async function syncGuestCart() {
  return
}
