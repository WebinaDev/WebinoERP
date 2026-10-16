"use client"

import Link from "next/link"
import { useEffect, useRef, useState } from "react"
import { BookOpen, ChevronDown, LayoutDashboard, LogIn, LogOut, Package, ShoppingBag, Trash2, User, UserPlus } from "lucide-react"

import { isStaff, removeShopItem, setCartPanel, shopLogout, tomanFromMinor, useShopCart, useShopUser } from "./shop-api"

function useOutside(ref: React.RefObject<HTMLElement | null>, onOut: () => void, active: boolean) {
  useEffect(() => {
    if (!active) return
    const h = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) onOut() }
    const k = (e: KeyboardEvent) => { if (e.key === "Escape") onOut() }
    document.addEventListener("mousedown", h)
    document.addEventListener("keydown", k)
    return () => { document.removeEventListener("mousedown", h); document.removeEventListener("keydown", k) }
  }, [ref, onOut, active])
}

const fa = (n: number) => new Intl.NumberFormat("fa-IR").format(n)

/** Header cart: empty/filled state, count badge, dropdown with items. Opens automatically after an AJAX add. */
export function HeaderCart() {
  const { cart, open, busy, lastAdded } = useShopCart()
  const ref = useRef<HTMLDivElement>(null)
  useOutside(ref, () => setCartPanel(false), open)
  const count = cart?.pricing?.items_count ?? 0
  const items = cart?.items ?? []
  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setCartPanel(!open)}
        aria-label={count ? `سبد خرید، ${fa(count)} کالا` : "سبد خرید خالی است"}
        aria-expanded={open}
        className={`relative inline-flex size-10 items-center justify-center rounded-full border transition ${count ? "border-[#0054ff] bg-[#0054ff] text-white" : "border-[#e2e6f0] bg-white text-[#22242a] hover:border-[#0054ff] hover:text-[#0054ff]"}`}
      >
        <ShoppingBag className="size-5" />
        {count ? <span className="absolute -top-1 -left-1 min-w-5 rounded-full bg-[#ff4d4f] px-1 text-center text-[11px] font-black leading-5 text-white ring-2 ring-white">{fa(count)}</span> : null}
      </button>
      {open ? (
        <div className="absolute left-0 top-12 z-50 w-[min(92vw,360px)] overflow-hidden rounded-3xl border border-[#e6e9f2] bg-white text-[#22242a] shadow-[0_24px_60px_-20px_rgba(0,30,90,.35)]" dir="rtl">
          <div className="flex items-center justify-between border-b border-[#eef1f6] px-4 py-3">
            <b>سبد خرید</b>
            <span className="text-xs text-[#6b7080]">{count ? `${fa(count)} کالا` : "خالی"}</span>
          </div>
          {lastAdded ? <p className="bg-[#eef4ff] px-4 py-2 text-xs font-bold text-[#0054ff]">«{lastAdded}» به سبد اضافه شد.</p> : null}
          {!items.length ? (
            <div className="px-4 py-8 text-center">
              <ShoppingBag className="mx-auto size-9 text-[#c3c8d4]" />
              <p className="mt-2 text-sm text-[#6b7080]">سبد خرید شما خالی است.</p>
              <Link href="/products" onClick={() => setCartPanel(false)} className="mt-3 inline-flex rounded-full bg-[#0054ff] px-4 py-2 text-sm font-bold text-white">مشاهده محصولات</Link>
            </div>
          ) : (
            <>
              <ul className={`max-h-80 divide-y divide-[#f0f2f7] overflow-auto ${busy ? "opacity-60" : ""}`}>
                {items.map((l) => (
                  <li key={l.id} className="flex items-center gap-3 px-4 py-3">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    {l.product.cover_image_url || l.product.image_url ? <img src={l.product.cover_image_url || l.product.image_url || ""} alt="" className="size-12 rounded-xl object-cover" /> : <span className="flex size-12 items-center justify-center rounded-xl bg-[#eef4ff] text-[#0054ff]"><Package className="size-5" /></span>}
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-bold">{l.product.name}</p>
                      <p className="text-xs text-[#6b7080]">{fa(l.quantity)} × {tomanFromMinor(l.unit_price_minor)}</p>
                    </div>
                    <button type="button" aria-label="حذف" onClick={() => void removeShopItem(l.product_id)} className="rounded-full p-2 text-[#9aa0ad] hover:bg-[#fff1f1] hover:text-[#ff4d4f]"><Trash2 className="size-4" /></button>
                  </li>
                ))}
              </ul>
              <div className="border-t border-[#eef1f6] p-4">
                <div className="flex items-center justify-between text-sm"><span className="text-[#6b7080]">جمع کل</span><b className="text-base">{tomanFromMinor(cart?.pricing?.total_minor ?? 0)}</b></div>
                <div className="mt-3 grid grid-cols-2 gap-2">
                  <Link href="/cart" onClick={() => setCartPanel(false)} className="flex h-11 items-center justify-center rounded-2xl border border-[#d8deeb] text-sm font-bold hover:border-[#0054ff] hover:text-[#0054ff]">مشاهده سبد</Link>
                  <Link href="/checkout" onClick={() => setCartPanel(false)} className="flex h-11 items-center justify-center rounded-2xl bg-[#0054ff] text-sm font-bold text-white hover:bg-[#0045d1]">تسویه حساب</Link>
                </div>
              </div>
            </>
          )}
        </div>
      ) : null}
    </div>
  )
}

/** Header account: login/register for guests, dropdown with account links for customers. */
export function HeaderAccount() {
  const { user, loading } = useShopUser()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  useOutside(ref, () => setOpen(false), open)
  if (loading) return <span className="inline-block size-10 animate-pulse rounded-full bg-[#eef1f6]" aria-hidden />
  if (!user) {
    return (
      <div className="flex items-center gap-1">
        <Link href="/account/login" className="inline-flex h-10 items-center gap-1 rounded-full px-3 text-sm font-bold text-[#22242a] hover:text-[#0054ff]"><LogIn className="size-4" /><span className="hidden sm:inline">ورود</span></Link>
        <Link href="/account/login?tab=register" className="hidden h-10 items-center gap-1 rounded-full border border-[#e2e6f0] px-3 text-sm font-bold text-[#22242a] hover:border-[#0054ff] hover:text-[#0054ff] md:inline-flex"><UserPlus className="size-4" />ثبت‌نام</Link>
      </div>
    )
  }
  const initial = (user.name || user.email || "?").trim().charAt(0)
  const link = "flex items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-[#f3f6fc] hover:text-[#0054ff]"
  return (
    <div ref={ref} className="relative">
      <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="inline-flex h-10 items-center gap-1 rounded-full border border-[#e2e6f0] bg-white ps-1 pe-2 text-sm font-bold text-[#22242a] hover:border-[#0054ff]">
        <span className="flex size-8 items-center justify-center rounded-full bg-[#0054ff] text-white">{initial}</span>
        <ChevronDown className="size-4" />
      </button>
      {open ? (
        <div className="absolute left-0 top-12 z-50 w-64 rounded-3xl border border-[#e6e9f2] bg-white p-2 text-[#22242a] shadow-[0_24px_60px_-20px_rgba(0,30,90,.35)]" dir="rtl">
          <div className="px-3 py-2">
            <p className="truncate font-bold">{user.name}</p>
            <p className="truncate text-xs text-[#6b7080]" dir="ltr">{user.email}</p>
          </div>
          <div className="my-1 h-px bg-[#eef1f6]" />
          <Link href="/account" onClick={() => setOpen(false)} className={link}><User className="size-4" />حساب کاربری</Link>
          <Link href="/account?tab=courses" onClick={() => setOpen(false)} className={link}><BookOpen className="size-4" />دوره‌های من</Link>
          <Link href="/account?tab=orders" onClick={() => setOpen(false)} className={link}><Package className="size-4" />سفارشات من</Link>
          {isStaff(user) ? <Link href="/dashboard" onClick={() => setOpen(false)} className={link}><LayoutDashboard className="size-4" />پنل مدیریت</Link> : null}
          <div className="my-1 h-px bg-[#eef1f6]" />
          <button type="button" onClick={() => { setOpen(false); void shopLogout() }} className={`${link} w-full text-[#ff4d4f]`}><LogOut className="size-4" />خروج</button>
        </div>
      ) : null}
    </div>
  )
}
