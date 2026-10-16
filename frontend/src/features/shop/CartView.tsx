"use client"

import Link from "next/link"
import { useState } from "react"
import { Minus, Package, Plus, ShoppingBag, Tag, Trash2 } from "lucide-react"

import { applyCoupon, removeShopItem, setShopQty, tomanFromMinor, useShopCart } from "./shop-api"

const fa = (n: number) => new Intl.NumberFormat("fa-IR").format(n)
const SINGLE = ["course", "license", "package", "digital"]

export function CartSummary({ cta, compact }: { cta?: React.ReactNode; compact?: boolean }) {
  const { cart } = useShopCart()
  const p = cart?.pricing
  if (!p) return null
  return (
    <aside className="h-fit rounded-3xl border border-[#e6e9f2] bg-white p-5 lg:sticky lg:top-24">
      <h2 className="font-black">خلاصه سفارش</h2>
      <dl className="mt-4 grid gap-2 text-sm">
        <div className="flex justify-between"><dt className="text-[#6b7080]">جمع کالاها ({fa(p.items_count)})</dt><dd>{tomanFromMinor(p.subtotal_minor)}</dd></div>
        {p.discount_minor ? <div className="flex justify-between text-[#16a34a]"><dt>تخفیف{p.coupon ? ` (${p.coupon.code})` : ""}</dt><dd>{tomanFromMinor(p.discount_minor)}-</dd></div> : null}
        {p.requires_shipping ? <div className="flex justify-between"><dt className="text-[#6b7080]">هزینه ارسال</dt><dd>{p.shipping_minor ? tomanFromMinor(p.shipping_minor) : "رایگان"}</dd></div> : null}
        <div className="mt-2 flex justify-between border-t border-[#eef1f6] pt-3 text-base"><dt className="font-bold">مبلغ قابل پرداخت</dt><dd className="font-black text-[#0054ff]">{tomanFromMinor(p.total_minor)}</dd></div>
      </dl>
      {!compact ? <CouponBox /> : null}
      {cta}
    </aside>
  )
}

function CouponBox() {
  const { cart, busy } = useShopCart()
  const [code, setCode] = useState(cart?.coupon_code ?? "")
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)
  return (
    <form className="mt-4 flex gap-2" onSubmit={async (e) => {
      e.preventDefault()
      const res = await applyCoupon(code.trim())
      setMsg(res.ok ? { ok: true, text: code.trim() ? "کد تخفیف اعمال شد." : "کد تخفیف حذف شد." } : { ok: false, text: res.message })
    }}>
      <label className="relative flex-1">
        <Tag className="pointer-events-none absolute right-3 top-3 size-4 text-[#9aa0ad]" />
        <input value={code} onChange={(e) => setCode(e.target.value)} placeholder="کد تخفیف" className="h-10 w-full rounded-xl border border-[#e2e6f0] pr-9 pl-3 text-sm outline-none focus:border-[#0054ff]" />
      </label>
      <button disabled={busy} className="h-10 rounded-xl border border-[#0054ff] px-3 text-sm font-bold text-[#0054ff] disabled:opacity-50">اعمال</button>
      {msg ? <p className={`basis-full text-xs ${msg.ok ? "text-[#16a34a]" : "text-[#ff4d4f]"}`}>{msg.text}</p> : null}
    </form>
  )
}

export function CartView({ title = "سبد خرید", emptyText = "سبد خرید شما خالی است.", note }: { title?: string; emptyText?: string; note?: string }) {
  const { cart, loading, busy, error } = useShopCart()
  const items = cart?.items ?? []
  return (
    <div className="mx-auto max-w-6xl px-4 py-10" dir="rtl">
      <h1 className="text-2xl font-black text-[#22242a]">{title}</h1>
      {loading ? (
        <div className="mt-6 h-40 animate-pulse rounded-3xl bg-[#f3f5f9]" />
      ) : !items.length ? (
        <div className="mt-6 rounded-3xl border border-dashed border-[#d8deeb] p-10 text-center">
          <ShoppingBag className="mx-auto size-10 text-[#9aa0ad]" />
          <p className="mt-3 text-[#4a4e5a]">{emptyText}</p>
          <div className="mt-4 flex flex-wrap justify-center gap-2">
            <Link href="/products" className="inline-flex rounded-2xl bg-[#0054ff] px-5 py-3 text-sm font-bold text-white">مشاهده محصولات</Link>
            <Link href="/academy" className="inline-flex rounded-2xl border border-[#d8deeb] px-5 py-3 text-sm font-bold">دوره‌های آموزشی</Link>
          </div>
        </div>
      ) : (
        <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
          <ul className={`grid gap-3 ${busy ? "opacity-70" : ""}`}>
            {items.map((l) => {
              const single = SINGLE.includes(l.product.kind ?? "")
              const img = l.product.cover_image_url || l.product.image_url
              return (
                <li key={l.id} className="flex flex-wrap items-center gap-4 rounded-3xl border border-[#e6e9f2] bg-white p-3 sm:flex-nowrap">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  {img ? <img src={img} alt="" className="h-20 w-28 rounded-2xl object-cover" /> : <span className="flex h-20 w-28 items-center justify-center rounded-2xl bg-[#eef4ff] text-[#0054ff]"><Package className="size-6" /></span>}
                  <div className="min-w-0 flex-1">
                    {l.product.url ? <Link href={l.product.url} className="line-clamp-2 font-bold text-[#22242a] hover:text-[#0054ff]">{l.product.name}</Link> : <p className="font-bold">{l.product.name}</p>}
                    <p className="mt-1 text-sm text-[#6b7080]">{tomanFromMinor(l.unit_price_minor)}{l.product.kind === "course" ? " · دوره آموزشی" : ""}</p>
                  </div>
                  {single ? <span className="rounded-xl bg-[#f3f5f9] px-3 py-1 text-xs text-[#6b7080]">۱ عدد</span> : (
                    <div className="flex items-center gap-1 rounded-xl border border-[#e6e9f2] p-1">
                      <button type="button" aria-label="افزایش" onClick={() => void setShopQty(l.product_id, l.quantity + 1)} className="p-1"><Plus className="size-4" /></button>
                      <span className="w-7 text-center font-bold">{fa(l.quantity)}</span>
                      <button type="button" aria-label="کاهش" onClick={() => void setShopQty(l.product_id, l.quantity - 1)} className="p-1"><Minus className="size-4" /></button>
                    </div>
                  )}
                  <b className="w-32 text-left text-sm">{tomanFromMinor(l.line_total_minor)}</b>
                  <button type="button" aria-label="حذف" onClick={() => void removeShopItem(l.product_id)} className="rounded-full p-2 text-[#ff4d4f] hover:bg-[#fff1f1]"><Trash2 className="size-4" /></button>
                </li>
              )
            })}
            {error ? <li className="text-sm text-[#ff4d4f]">{error}</li> : null}
          </ul>
          <CartSummary cta={<>
            {note ? <p className="mt-3 text-xs leading-6 text-[#6b7080]">{note}</p> : null}
            <Link href="/checkout" className="mt-4 flex h-12 items-center justify-center rounded-2xl bg-[#0054ff] font-bold text-white hover:bg-[#0045d1]">ادامه و تسویه حساب</Link>
            <Link href="/products" className="mt-2 block text-center text-sm text-[#6b7080] hover:text-[#0054ff]">ادامه خرید</Link>
          </>} />
        </div>
      )}
    </div>
  )
}
