"use client"

/* Real shop (Sales storefront) client: AJAX cart (guest token or signed-in), checkout, account.
 * One shared store so the header cart, product buttons and the cart page stay in sync. */

import { useSyncExternalStore } from "react"

const API = process.env.NEXT_PUBLIC_API_URL ?? ""
const TOKEN_KEY = "webino.cart.token"

export type ShopProductLite = {
  id: number
  slug: string
  name: string
  image_url?: string | null
  cover_image_url?: string | null
  price_minor: number
  sale_price_minor?: number | null
  kind?: string
  url?: string | null
}
export type ShopCartLine = { id: number; product_id: number; quantity: number; unit_price_minor: number; line_total_minor: number; product: ShopProductLite }
export type ShopPricing = {
  items_count: number
  subtotal_minor: number
  discount_minor: number
  shipping_minor: number
  total_minor: number
  coupon: { code: string } | null
  coupon_error: string | null
  requires_shipping?: boolean
}
export type ShopCart = { id: number | null; token: string | null; authenticated: boolean; coupon_code: string | null; items: ShopCartLine[]; pricing: ShopPricing }
export type ShopUser = { id: number; name: string; email: string; roles?: string[]; dashboard_role?: string }

export type ApiResult<T> = { ok: true; data: T } | { ok: false; status: number; message: string; errors?: Record<string, string[]> }

/** IRR minor units → «۱۲٬۰۰۰ تومان». */
export const tomanFromMinor = (minor: number) => `${new Intl.NumberFormat("fa-IR").format(Math.round((minor || 0) / 10))} تومان`

function token(): string | null {
  if (typeof window === "undefined") return null
  try { return window.localStorage.getItem(TOKEN_KEY) } catch { return null }
}
function setToken(t: string | null) {
  try { if (t) window.localStorage.setItem(TOKEN_KEY, t); else window.localStorage.removeItem(TOKEN_KEY) } catch { /* private mode */ }
}

export async function shopApi<T>(path: string, opts: { method?: string; json?: unknown } = {}): Promise<ApiResult<T>> {
  try {
    const t = token()
    const res = await fetch(`${API}${path}`, {
      method: opts.method ?? "GET",
      credentials: "include",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...(t ? { "X-Cart-Token": t } : {}),
        ...(opts.json !== undefined ? { "Content-Type": "application/json" } : {}),
      },
      body: opts.json !== undefined ? JSON.stringify(opts.json) : undefined,
    })
    const text = await res.text()
    let body: Record<string, unknown> | null = null
    try { body = text ? (JSON.parse(text) as Record<string, unknown>) : null } catch { body = null }
    if (!res.ok) {
      const errors = (body?.errors && typeof body.errors === "object" ? body.errors : undefined) as Record<string, string[]> | undefined
      const first = errors ? Object.values(errors).flat().find((v) => typeof v === "string") : undefined
      return { ok: false, status: res.status, message: (typeof body?.message === "string" && body.message) || first || "خطایی رخ داد. دوباره تلاش کنید.", errors }
    }
    const data = body && "data" in body ? body.data : body
    return { ok: true, data: data as T }
  } catch {
    return { ok: false, status: 0, message: "ارتباط با سرور برقرار نشد." }
  }
}

/* ---------------- cart store ---------------- */
const EMPTY_PRICING: ShopPricing = { items_count: 0, subtotal_minor: 0, discount_minor: 0, shipping_minor: 0, total_minor: 0, coupon: null, coupon_error: null }
type CartState = { cart: ShopCart | null; loading: boolean; busy: boolean; open: boolean; lastAdded: string | null; error: string | null }
let state: CartState = { cart: null, loading: true, busy: false, open: false, lastAdded: null, error: null }
const listeners = new Set<() => void>()
let started = false
function emit(patch: Partial<CartState>) { state = { ...state, ...patch }; listeners.forEach((l) => l()) }
function subscribe(l: () => void) {
  listeners.add(l)
  if (!started && typeof window !== "undefined") { started = true; void refreshCart() }
  return () => { listeners.delete(l) }
}
const SERVER_STATE: CartState = { cart: null, loading: true, busy: false, open: false, lastAdded: null, error: null }
export function useShopCart() { return useSyncExternalStore(subscribe, () => state, () => SERVER_STATE) }
export function cartState() { return state }
export function subscribeShopCart(l: () => void) { return subscribe(l) }

function accept(res: ApiResult<ShopCart>) {
  if (res.ok) {
    const cart = { ...res.data, pricing: res.data.pricing ?? EMPTY_PRICING, items: res.data.items ?? [] }
    if (cart.token) setToken(cart.token)
    else if (cart.authenticated) setToken(null)
    emit({ cart, loading: false, busy: false, error: null })
  } else {
    emit({ loading: false, busy: false, error: res.message })
  }
  return res
}

export async function refreshCart() { return accept(await shopApi<ShopCart>("/api/v1/cart")) }

export async function addToShopCart(ref: { slug?: string; productId?: number; name?: string }, quantity = 1) {
  emit({ busy: true })
  const res = accept(await shopApi<ShopCart>("/api/v1/cart/items", { method: "POST", json: { ...(ref.productId ? { product_id: ref.productId } : { slug: ref.slug }), quantity } }))
  if (res.ok) emit({ open: true, lastAdded: ref.name ?? ref.slug ?? null })
  return res
}
export async function setShopQty(productId: number, quantity: number) {
  emit({ busy: true })
  return accept(await shopApi<ShopCart>(`/api/v1/cart/items/${productId}`, { method: "PUT", json: { quantity: Math.max(0, Math.min(99, quantity)) } }))
}
export async function removeShopItem(productId: number) {
  emit({ busy: true })
  return accept(await shopApi<ShopCart>(`/api/v1/cart/items/${productId}`, { method: "DELETE" }))
}
export async function applyCoupon(code: string) {
  emit({ busy: true })
  return accept(await shopApi<ShopCart>("/api/v1/cart/coupon", { method: "POST", json: { coupon_code: code } }))
}
export function setCartPanel(open: boolean) { emit({ open, lastAdded: open ? state.lastAdded : null }) }
export function inShopCart(slug: string) { return !!state.cart?.items.some((l) => l.product.slug === slug) }

/* ---------------- auth store ---------------- */
type AuthState = { user: ShopUser | null; loading: boolean }
let auth: AuthState = { user: null, loading: true }
const authListeners = new Set<() => void>()
let authStarted = false
function emitAuth(next: AuthState) { auth = next; authListeners.forEach((l) => l()) }
export async function refreshUser() {
  const res = await shopApi<{ user?: ShopUser; roles?: string[]; dashboard_role?: string }>("/api/v1/core/auth/user")
  const user = res.ok && res.data?.user ? { ...res.data.user, roles: res.data.roles ?? [], dashboard_role: res.data.dashboard_role } : null
  emitAuth({ user: user && user.id ? user : null, loading: false })
  return user
}
const AUTH_SERVER: AuthState = { user: null, loading: true }
export function useShopUser() {
  return useSyncExternalStore(
    (l) => { authListeners.add(l); if (!authStarted && typeof window !== "undefined") { authStarted = true; void refreshUser() } return () => { authListeners.delete(l) } },
    () => auth,
    () => AUTH_SERVER,
  )
}
export async function shopLogin(email: string, password: string) {
  const res = await shopApi<unknown>("/api/v1/core/auth/login", { method: "POST", json: { email, password } })
  if (res.ok) { await refreshUser(); await refreshCart() }
  return res
}
export async function shopRegister(body: { name: string; email: string; password: string; password_confirmation: string }) {
  const res = await shopApi<unknown>("/api/v1/core/auth/register", { method: "POST", json: body })
  if (res.ok) { await refreshUser(); await refreshCart() }
  return res
}
export async function shopLogout() {
  await shopApi<unknown>("/api/v1/core/auth/logout", { method: "POST" })
  setToken(null)
  emitAuth({ user: null, loading: false })
  await refreshCart()
}
export function isStaff(user: ShopUser | null) {
  if (!user) return false
  const roles = (user.roles ?? []).map((r) => String(r).toLowerCase())
  return roles.some((r) => r !== "client" && r !== "customer") || (!!user.dashboard_role && !["client", "customer"].includes(user.dashboard_role))
}
