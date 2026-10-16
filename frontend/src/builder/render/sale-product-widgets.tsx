"use client"

/* Sellable product widgets (page builder): product hero with gallery + price box + add to cart,
 * tabbed details, catalog grid with category filter, cart. All content/prices live in builder props. */

import Link from "next/link"
import { useEffect, useMemo, useState, useSyncExternalStore, type ReactNode } from "react"
import {
  BadgeCheck, CalendarClock, CheckCircle2, ChevronLeft, Download, Headphones, History, Layers, MessageSquareText,
  Minus, Plus, RefreshCw, ShieldCheck, ShoppingCart, Sparkles, Star, Tag, Trash2, Users,
} from "lucide-react"

import { addToCart, cartSnapshot, setQty, subscribeCart, type CartLine } from "../cart"
import { propStr } from "../props"
import type { WidgetNode } from "../types"

const rows = (t: string) => t.split("\n").map((l) => l.trim()).filter(Boolean).map((l) => l.split("|").map((c) => c.trim()))
const lines = (t: string) => t.split("\n").map((l) => l.trim()).filter(Boolean)
const safe = (h: string) => (/^(https?:|mailto:|tel:|\/|#)/i.test(h.trim()) ? h.trim() : h.trim() ? `/${h.trim().replace(/^\/+/, "")}` : "")
const num = (v: string) => Number(String(v).replace(/[^\d]/g, "")) || 0
export const toman = (n: number) => `${new Intl.NumberFormat("fa-IR").format(n)} تومان`
const EMPTY: CartLine[] = []
let cache: CartLine[] = EMPTY
let cacheKey = ""
function snap() {
  const s = cartSnapshot()
  const k = JSON.stringify(s)
  if (k !== cacheKey) { cacheKey = k; cache = s }
  return cache
}
function useCart() {
  return useSyncExternalStore(subscribeCart, snap, () => EMPTY)
}

function Stars({ value }: { value: number }) {
  return (
    <span className="inline-flex items-center gap-0.5" aria-label={`${value} از ۵`}>
      {[1, 2, 3, 4, 5].map((i) => <Star key={i} className={`h-4 w-4 ${i <= Math.round(value) ? "fill-amber-400 text-amber-400" : "text-slate-300"}`} />)}
    </span>
  )
}

function AddButton({ slug, name, price, image, label, cartHref, big }: { slug: string; name: string; price: number; image?: string; label: string; cartHref: string; big?: boolean }) {
  const cart = useCart()
  const [flash, setFlash] = useState(false)
  const inCart = cart.some((l) => l.slug === slug)
  useEffect(() => { if (!flash) return; const t = setTimeout(() => setFlash(false), 2200); return () => clearTimeout(t) }, [flash])
  return (
    <div className="flex flex-col gap-2">
      <button
        type="button"
        onClick={() => { addToCart({ slug, name, price, tone: "#0054ff", image: image || null }); setFlash(true) }}
        className={`inline-flex items-center justify-center gap-2 rounded-2xl bg-[#0054ff] font-bold text-white shadow-[0_10px_30px_-12px_#0054ff] transition hover:bg-[#0045d1] ${big ? "h-14 text-base" : "h-11 px-4 text-sm"}`}
      >
        <ShoppingCart className="h-5 w-5" />{flash ? "به سبد اضافه شد" : label}
      </button>
      {inCart ? <Link href={cartHref} className="inline-flex items-center justify-center gap-1 text-sm font-bold text-[#0054ff] hover:underline">مشاهده سبد خرید<ChevronLeft className="h-4 w-4" /></Link> : null}
    </div>
  )
}

function Gallery({ images, alt }: { images: string[]; alt: string }) {
  const [i, setI] = useState(0)
  if (!images.length) return null
  return (
    <div>
      <div className="overflow-hidden rounded-3xl border border-[#e6e9f2] bg-[#f5f7fc]">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={images[i]} alt={`${alt} ${i + 1}`} className="aspect-[16/10] w-full object-cover" />
      </div>
      {images.length > 1 ? (
        <div className="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-5">
          {images.map((src, k) => (
            <button key={src + k} type="button" onClick={() => setI(k)} className={`overflow-hidden rounded-xl border-2 ${k === i ? "border-[#0054ff]" : "border-transparent opacity-70 hover:opacity-100"}`}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={src} alt="" className="aspect-[16/10] w-full object-cover" loading="lazy" />
            </button>
          ))}
        </div>
      ) : null}
    </div>
  )
}

/** Hero: gallery + title + key features on one side, sticky price box on the other. */
export function SaleProductHeroWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const title = propStr(p, "title", "")
  const slug = propStr(p, "slug", title)
  const price = num(propStr(p, "price", "0"))
  const old = num(propStr(p, "oldPrice", ""))
  const images = rows(propStr(p, "images", "")).map((r) => r[0]).filter(Boolean)
  const rating = Number(propStr(p, "rating", "0")) || 0
  const cartHref = safe(propStr(p, "cartHref", "/pages/cart"))
  const demoHref = safe(propStr(p, "demoHref", ""))
  const off = old > price && price > 0 ? Math.round(((old - price) / old) * 100) : 0
  const meta = [
    { icon: Layers, label: "نسخه", value: propStr(p, "version", "") },
    { icon: CalendarClock, label: "آخرین به‌روزرسانی", value: propStr(p, "updated", "") },
    { icon: Users, label: "فروش", value: propStr(p, "sales", "") },
    { icon: Tag, label: "دسته", value: propStr(p, "category", "") },
  ].filter((m) => m.value)
  return (
    <div className="mx-auto grid max-w-7xl gap-8 px-4 py-8 lg:grid-cols-[minmax(0,1fr)_360px]">
      <div className="min-w-0">
        <nav className="mb-3 flex flex-wrap items-center gap-1 text-xs text-[#6b7080]">
          <Link href="/products" className="hover:text-[#0054ff]">محصولات</Link>
          {propStr(p, "category", "") ? <><ChevronLeft className="h-3 w-3" /><span>{propStr(p, "category", "")}</span></> : null}
          <ChevronLeft className="h-3 w-3" /><span className="text-[#22242a]">{title}</span>
        </nav>
        <div className="flex flex-wrap items-center gap-2">
          {propStr(p, "badge", "") ? <span className="rounded-full bg-[#eaf1ff] px-3 py-1 text-xs font-bold text-[#0054ff]">{propStr(p, "badge", "")}</span> : null}
          {rating ? <span className="inline-flex items-center gap-1 text-sm text-[#6b7080]"><Stars value={rating} />{propStr(p, "ratingCount", "")}</span> : null}
        </div>
        <h1 className="mt-3 text-2xl font-black leading-[1.6] text-[#22242a] sm:text-3xl">{title}</h1>
        {propStr(p, "subtitle", "") ? <p className="mt-2 text-base leading-8 text-[#4a4e5a]">{propStr(p, "subtitle", "")}</p> : null}
        <div className="mt-6"><Gallery images={images} alt={title} /></div>
        {lines(propStr(p, "features", "")).length ? (
          <ul className="mt-6 grid gap-3 sm:grid-cols-2">
            {lines(propStr(p, "features", "")).map((f) => (
              <li key={f} className="flex items-start gap-2 rounded-2xl border border-[#eef0f5] bg-white p-3 text-sm leading-7 text-[#22242a]"><CheckCircle2 className="mt-1 h-4 w-4 shrink-0 text-[#0054ff]" />{f}</li>
            ))}
          </ul>
        ) : null}
      </div>
      <aside className="lg:sticky lg:top-24 lg:self-start">
        <div className="rounded-3xl border border-[#e6e9f2] bg-white p-6 shadow-[0_20px_60px_-30px_rgba(34,36,42,.35)]">
          {propStr(p, "license", "") ? <p className="mb-3 text-sm font-bold text-[#22242a]">{propStr(p, "license", "")}</p> : null}
          <div className="flex items-end justify-between gap-2">
            <div>
              {off ? <p className="text-sm text-[#9aa0ad] line-through">{toman(old)}</p> : null}
              <p className="text-2xl font-black text-[#22242a]">{price ? toman(price) : "تماس بگیرید"}</p>
            </div>
            {off ? <span className="rounded-xl bg-[#ff4d4f] px-2.5 py-1 text-sm font-black text-white">{off}٪</span> : null}
          </div>
          {propStr(p, "priceNote", "") ? <p className="mt-2 text-xs leading-6 text-[#6b7080]">{propStr(p, "priceNote", "")}</p> : null}
          <div className="mt-5"><AddButton big slug={slug} name={title} price={price} image={images[0]} label={propStr(p, "buttonLabel", "افزودن به سبد خرید")} cartHref={cartHref} /></div>
          {demoHref ? <Link href={demoHref} className="mt-3 flex h-12 items-center justify-center rounded-2xl border border-[#d8deeb] text-sm font-bold text-[#22242a] hover:border-[#0054ff] hover:text-[#0054ff]">{propStr(p, "demoLabel", "درخواست دمو")}</Link> : null}
          {meta.length ? (
            <dl className="mt-5 divide-y divide-[#eef0f5] text-sm">
              {meta.map((m) => (
                <div key={m.label} className="flex items-center justify-between py-2.5">
                  <dt className="flex items-center gap-2 text-[#6b7080]"><m.icon className="h-4 w-4 text-[#0054ff]" />{m.label}</dt>
                  <dd className="font-bold text-[#22242a]">{m.value}</dd>
                </div>
              ))}
            </dl>
          ) : null}
          <ul className="mt-4 grid gap-2 text-xs text-[#4a4e5a]">
            {(lines(propStr(p, "guarantees", "")).length ? lines(propStr(p, "guarantees", "")) : ["ضمانت بازگشت وجه ۷ روزه", "پشتیبانی فنی ۶ ماهه", "به‌روزرسانی رایگان", "نصب و راه‌اندازی اختیاری"]).map((g, k) => {
              const Icons = [ShieldCheck, Headphones, RefreshCw, Download]
              const C = Icons[k % Icons.length]
              return <li key={g} className="flex items-center gap-2 rounded-xl bg-[#f6f8fd] px-3 py-2"><C className="h-4 w-4 text-[#0054ff]" />{g}</li>
            })}
          </ul>
        </div>
      </aside>
    </div>
  )
}

function RichText({ text }: { text: string }) {
  const out: ReactNode[] = []
  let list: string[] = []
  const flush = () => { if (list.length) { out.push(<ul key={`u${out.length}`} className="my-3 grid gap-2">{list.map((l) => <li key={l} className="flex gap-2 leading-8"><CheckCircle2 className="mt-2 h-4 w-4 shrink-0 text-[#0054ff]" />{l}</li>)}</ul>); list = [] } }
  lines(text).forEach((l, i) => {
    if (l.startsWith("- ")) { list.push(l.slice(2)); return }
    flush()
    if (l.startsWith("### ")) out.push(<h3 key={i} className="mt-6 text-lg font-extrabold text-[#22242a]">{l.slice(4)}</h3>)
    else if (l.startsWith("## ")) out.push(<h2 key={i} className="mt-8 text-xl font-black text-[#22242a]">{l.slice(3)}</h2>)
    else out.push(<p key={i} className="my-3 leading-9 text-[#3a3d45]">{l}</p>)
  })
  flush()
  return <div className="text-[15px]">{out}</div>
}

/** Tabs: description, features, specs, changelog, FAQ, reviews. */
export function SaleProductTabsWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const tabs = useMemo(() => [
    { key: "description", label: "توضیحات", icon: Sparkles },
    { key: "specs", label: "مشخصات", icon: BadgeCheck },
    { key: "changelog", label: "تغییرات نسخه", icon: History },
    { key: "faq", label: "سوالات متداول", icon: MessageSquareText },
    { key: "reviews", label: "نظرات", icon: Star },
  ].filter((t) => propStr(p, t.key, "").trim()), [p])
  const [active, setActive] = useState(tabs[0]?.key ?? "description")
  if (!tabs.length) return null
  return (
    <div className="mx-auto max-w-7xl px-4 py-6">
      <div className="rounded-3xl border border-[#e6e9f2] bg-white shadow-sm">
        <div role="tablist" className="flex gap-1 overflow-x-auto border-b border-[#eef0f5] p-2">
          {tabs.map((t) => (
            <button key={t.key} role="tab" aria-selected={active === t.key} type="button" onClick={() => setActive(t.key)}
              className={`inline-flex shrink-0 items-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-bold transition ${active === t.key ? "bg-[#0054ff] text-white" : "text-[#4a4e5a] hover:bg-[#f2f5fc]"}`}>
              <t.icon className="h-4 w-4" />{t.label}
            </button>
          ))}
        </div>
        <div className="p-5 sm:p-8">
          {tabs.map((t) => (
            <section key={t.key} hidden={active !== t.key}>
              {t.key === "description" ? <RichText text={propStr(p, "description", "")} /> : null}
              {t.key === "specs" ? (
                <table className="w-full text-sm"><tbody>
                  {rows(propStr(p, "specs", "")).map(([k = "", v = ""], i) => (
                    <tr key={i} className="border-b border-[#eef0f5] last:border-0"><th className="w-1/3 py-3 text-right font-medium text-[#6b7080]">{k}</th><td className="py-3 font-semibold text-[#22242a]">{v}</td></tr>
                  ))}
                </tbody></table>
              ) : null}
              {t.key === "changelog" ? (
                <ol className="relative grid gap-5 border-r-2 border-[#e6ecfb] pr-5">
                  {rows(propStr(p, "changelog", "")).map(([v = "", d = "", notes = ""], i) => (
                    <li key={i} className="relative">
                      <span className="absolute -right-[27px] top-1.5 h-3 w-3 rounded-full bg-[#0054ff] ring-4 ring-white" />
                      <p className="font-black text-[#22242a]">نسخه <span dir="ltr">{v}</span> <span className="mr-2 text-xs font-medium text-[#6b7080]">{d}</span></p>
                      <ul className="mt-2 grid gap-1 text-sm text-[#3a3d45]">{notes.split("؛").map((n) => n.trim()).filter(Boolean).map((n) => <li key={n}>• {n}</li>)}</ul>
                    </li>
                  ))}
                </ol>
              ) : null}
              {t.key === "faq" ? (
                <div className="grid gap-3">
                  {rows(propStr(p, "faq", "")).map(([q = "", a = ""], i) => (
                    <details key={i} className="group rounded-2xl border border-[#eef0f5] bg-[#fafbff] p-4" open={i === 0}>
                      <summary className="cursor-pointer list-none font-bold text-[#22242a]">{q}</summary>
                      <p className="mt-2 leading-8 text-[#3a3d45]">{a}</p>
                    </details>
                  ))}
                </div>
              ) : null}
              {t.key === "reviews" ? (
                <div className="grid gap-4 md:grid-cols-2">
                  {rows(propStr(p, "reviews", "")).map(([name = "", stars = "5", text = "", role = ""], i) => (
                    <figure key={i} className="rounded-2xl border border-[#eef0f5] p-5">
                      <div className="flex items-center justify-between"><figcaption className="font-bold text-[#22242a]">{name}<span className="mr-2 text-xs font-normal text-[#6b7080]">{role}</span></figcaption><Stars value={Number(stars) || 5} /></div>
                      <blockquote className="mt-3 leading-8 text-[#3a3d45]">{text}</blockquote>
                    </figure>
                  ))}
                </div>
              ) : null}
            </section>
          ))}
        </div>
      </div>
    </div>
  )
}

/** Catalog grid: items «slug|title|category|price|oldPrice|image|href|short» per line, with category chips. */
export function SaleProductGridWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const items = rows(propStr(p, "items", "")).map(([slug = "", title = "", category = "", price = "", old = "", image = "", href = "", short = ""]) => ({ slug, title, category, price: num(price), old: num(old), image, href: safe(href), short }))
  const cats = Array.from(new Set(items.map((i) => i.category).filter(Boolean)))
  const showFilter = propStr(p, "showFilter", "true") !== "false" && cats.length > 1
  const [cat, setCat] = useState("")
  const list = cat ? items.filter((i) => i.category === cat) : items
  const cartHref = safe(propStr(p, "cartHref", "/pages/cart"))
  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      {propStr(p, "title", "") ? <h2 className="text-2xl font-black text-[#22242a]">{propStr(p, "title", "")}</h2> : null}
      {propStr(p, "intro", "") ? <p className="mt-2 max-w-3xl leading-8 text-[#4a4e5a]">{propStr(p, "intro", "")}</p> : null}
      {showFilter ? (
        <div className="mt-5 flex flex-wrap gap-2">
          {["", ...cats].map((c) => (
            <button key={c || "all"} type="button" onClick={() => setCat(c)} className={`rounded-full px-4 py-2 text-sm font-bold ${cat === c ? "bg-[#22242a] text-white" : "bg-[#f2f5fc] text-[#4a4e5a] hover:bg-[#e6ecfb]"}`}>{c || "همه"}</button>
          ))}
        </div>
      ) : null}
      <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {list.map((it) => (
          <article key={it.slug} className="group flex flex-col overflow-hidden rounded-3xl border border-[#e6e9f2] bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
            <Link href={it.href || "#"} className="block overflow-hidden bg-[#f5f7fc]">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              {it.image ? <img src={it.image} alt={it.title} loading="lazy" className="aspect-[16/10] w-full object-cover transition group-hover:scale-105" /> : <div className="aspect-[16/10]" />}
            </Link>
            <div className="flex flex-1 flex-col p-4">
              {it.category ? <span className="text-xs font-bold text-[#0054ff]">{it.category}</span> : null}
              <h3 className="mt-1 font-extrabold leading-7 text-[#22242a]"><Link href={it.href || "#"} className="hover:text-[#0054ff]">{it.title}</Link></h3>
              {it.short ? <p className="mt-1 line-clamp-2 text-sm leading-7 text-[#6b7080]">{it.short}</p> : null}
              <div className="mt-auto flex items-end justify-between gap-2 pt-4">
                <div>
                  {it.old > it.price ? <p className="text-xs text-[#9aa0ad] line-through">{toman(it.old)}</p> : null}
                  <p className="font-black text-[#22242a]">{it.price ? toman(it.price) : "استعلام"}</p>
                </div>
                <AddButton slug={it.slug} name={it.title} price={it.price} image={it.image} label="خرید" cartHref={cartHref} />
              </div>
            </div>
          </article>
        ))}
      </div>
    </div>
  )
}

/** Cart: localStorage lines, quantity, total and checkout CTA (goes to the order/proposal form with product names). */
export function SaleCartWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const cart = useCart()
  const total = cart.reduce((s, l) => s + l.price * l.qty, 0)
  const checkout = safe(propStr(p, "checkoutHref", "/proposal"))
  const q = encodeURIComponent(cart.map((l) => `${l.name} × ${l.qty}`).join("، "))
  return (
    <div className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="text-2xl font-black text-[#22242a]">{propStr(p, "title", "سبد خرید")}</h1>
      {!cart.length ? (
        <div className="mt-6 rounded-3xl border border-dashed border-[#d8deeb] p-10 text-center">
          <ShoppingCart className="mx-auto h-10 w-10 text-[#9aa0ad]" />
          <p className="mt-3 text-[#4a4e5a]">{propStr(p, "emptyText", "سبد خرید شما خالی است.")}</p>
          <Link href="/products" className="mt-4 inline-flex rounded-2xl bg-[#0054ff] px-5 py-3 text-sm font-bold text-white">مشاهده محصولات</Link>
        </div>
      ) : (
        <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
          <ul className="grid gap-3">
            {cart.map((l) => (
              <li key={l.slug} className="flex items-center gap-4 rounded-2xl border border-[#e6e9f2] bg-white p-3">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                {l.image ? <img src={l.image} alt="" className="h-16 w-24 rounded-xl object-cover" /> : null}
                <div className="min-w-0 flex-1"><p className="truncate font-bold text-[#22242a]">{l.name}</p><p className="text-sm text-[#6b7080]">{toman(l.price)}</p></div>
                <div className="flex items-center gap-1 rounded-xl border border-[#e6e9f2] p-1">
                  <button type="button" aria-label="افزایش" onClick={() => setQty(l.slug, l.qty + 1)} className="p-1"><Plus className="h-4 w-4" /></button>
                  <span className="w-6 text-center font-bold">{l.qty}</span>
                  <button type="button" aria-label="کاهش" onClick={() => setQty(l.slug, l.qty - 1)} className="p-1"><Minus className="h-4 w-4" /></button>
                </div>
                <button type="button" aria-label="حذف" onClick={() => setQty(l.slug, 0)} className="p-2 text-[#ff4d4f]"><Trash2 className="h-4 w-4" /></button>
              </li>
            ))}
          </ul>
          <aside className="h-fit rounded-3xl border border-[#e6e9f2] bg-white p-5">
            <div className="flex justify-between text-sm text-[#6b7080]"><span>جمع کل</span><span className="text-lg font-black text-[#22242a]">{toman(total)}</span></div>
            {propStr(p, "note", "") ? <p className="mt-3 text-xs leading-6 text-[#6b7080]">{propStr(p, "note", "")}</p> : null}
            <Link href={`${checkout}${checkout.includes("?") ? "&" : "?"}products=${q}`} className="mt-4 flex h-12 items-center justify-center rounded-2xl bg-[#0054ff] font-bold text-white">{propStr(p, "checkoutLabel", "ثبت سفارش")}</Link>
          </aside>
        </div>
      )}
    </div>
  )
}
