"use client"

/* Real shop pages for the corporate site (ERP-local; candidates for @webina/ui once Dashboard needs them):
 *  - shop-catalog:  live Sales storefront catalog with filters (category, on_sale=1, in_stock, sort, search) synced to the URL
 *  - offers-grid:   every discounted product, sorted by discount, with countdowns when meta.sale_ends_at is set
 *  - compare-table: per-visitor compare list (X-Visitor-Id), price/feature table, add from the catalog, add to cart
 * Prices come from /api/v1/public/catalog in IRR minor units and are shown in toman. Copy lives in builder props. */

import Link from "next/link"
import { usePathname, useRouter, useSearchParams } from "next/navigation"
import { useCallback, useEffect, useMemo, useState, useSyncExternalStore, type ReactNode } from "react"
import { BadgePercent, Check, GitCompareArrows, Plus, Search, ShoppingCart, Star, Timer, Trash2, X } from "lucide-react"

import { addToCart } from "../cart"
import { propNum, propStr } from "../props"
import type { WidgetNode } from "../types"
import { visitorHeaders } from "../storefront/visitor"

const API = (process.env.NEXT_PUBLIC_API_URL ?? "").trim().replace(/\/+$/, "").replace(/\/api$/, "")
const fa = (n: number) => new Intl.NumberFormat("fa-IR").format(n)
const toman = (minor: number) => `${fa(Math.round(minor / 10))} تومان`

export type ApiProduct = {
  id: number
  slug: string
  name: string
  excerpt?: string | null
  description?: string | null
  image_url?: string | null
  cover_image_url?: string | null
  price_minor: number
  discounted_price_minor: number
  sale_price_minor?: number | null
  discount_percent?: number | null
  sale_ends_at?: string | null
  is_available?: boolean
  category?: { slug: string; name: string } | null
  attributes?: unknown
  rating_avg?: number
  reviews_count?: number
  kind?: string
  url?: string | null
  digital?: boolean
}

type CatalogJson = { data?: { items?: ApiProduct[]; categories?: Array<{ slug: string; name: string }>; pagination?: { total?: number; last_page?: number } } }

const KIND: Record<string, string> = { license: "لایسنس نرم‌افزار", course: "دوره آموزشی", service: "خدمت", digital: "فایل دیجیتال", product: "کالای فیزیکی" }

function hrefOf(p: ApiProduct) {
  if (p.url && p.url.startsWith("/")) return p.url
  return `/products/${encodeURIComponent(p.slug)}`
}

function plain(html?: string | null) {
  return (html ?? "").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim()
}

export function specsOf(raw: unknown): Array<{ name: string; value: string }> {
  const out: Array<{ name: string; value: string }> = []
  const val = (v: unknown) => (Array.isArray(v) ? v.map((x) => (x && typeof x === "object" ? String((x as { name?: unknown }).name ?? (x as { value?: unknown }).value ?? "") : String(x))).join("، ") : String(v ?? ""))
  if (Array.isArray(raw)) {
    for (const r of raw) {
      if (!r || typeof r !== "object") continue
      const row = r as Record<string, unknown>
      const name = String(row.name ?? row.slug ?? "").trim()
      if (name) out.push({ name, value: val(row.value ?? row.values ?? row.terms) })
    }
  } else if (raw && typeof raw === "object") {
    for (const [k, v] of Object.entries(raw as Record<string, unknown>)) out.push({ name: k, value: val(v) })
  }
  return out
}

async function getJson<T>(path: string, init?: RequestInit): Promise<T | null> {
  try {
    const res = await fetch(`${API}${path}`, { credentials: "include", ...init, headers: { Accept: "application/json", ...visitorHeaders(), ...(init?.headers ?? {}) } })
    return res.ok ? ((await res.json()) as T) : null
  } catch {
    return null
  }
}

/* ---------- compare store (shared by every card on the page) ---------- */
let compareIds: number[] = []
let compareLoaded = false
const listeners = new Set<() => void>()
const emit = () => listeners.forEach((l) => l())
function subscribe(l: () => void) {
  listeners.add(l)
  if (!compareLoaded && typeof window !== "undefined") {
    compareLoaded = true
    void getJson<{ data?: { ids?: number[] } }>("/api/v1/public/compare").then((j) => {
      compareIds = Array.isArray(j?.data?.ids) ? j!.data!.ids!.map(Number) : []
      emit()
    })
  }
  return () => listeners.delete(l)
}
const EMPTY: number[] = []
function useCompareIds() {
  return useSyncExternalStore(subscribe, () => compareIds, () => EMPTY)
}
export async function toggleCompare(id: number, on: boolean) {
  const j = await getJson<{ data?: { ids?: number[] } }>(`/api/v1/public/compare/products/${id}`, { method: on ? "POST" : "DELETE", headers: { "X-Requested-With": "XMLHttpRequest" } })
  if (Array.isArray(j?.data?.ids)) compareIds = j!.data!.ids!.map(Number)
  else compareIds = on ? [...compareIds, id] : compareIds.filter((x) => x !== id)
  emit()
}

export function CompareButton({ id, compact }: { id: number; compact?: boolean }) {
  const ids = useCompareIds()
  const on = ids.includes(id)
  return (
    <button
      type="button"
      aria-pressed={on}
      title={on ? "حذف از مقایسه" : "افزودن به مقایسه"}
      onClick={() => void toggleCompare(id, !on)}
      className={`inline-flex items-center justify-center gap-1.5 rounded-2xl border text-sm font-bold transition ${compact ? "h-11 w-11" : "h-11 px-3"} ${on ? "border-[#0054ff] bg-[#eef3ff] text-[#0054ff]" : "border-[#e6e9f2] bg-white text-[#4a4e5a] hover:border-[#0054ff] hover:text-[#0054ff]"}`}
    >
      {on ? <Check className="h-4 w-4" /> : <GitCompareArrows className="h-4 w-4" />}
      {compact ? <span className="sr-only">{on ? "در مقایسه" : "مقایسه"}</span> : on ? "در مقایسه" : "مقایسه"}
    </button>
  )
}

/** Compare toggle for widgets that only know the product slug (builder product hero / sale grid). */
const slugIds = new Map<string, Promise<number | null>>()
export function CompareBySlug({ slug, compact }: { slug: string; compact?: boolean }) {
  const [id, setId] = useState<number | null>(null)
  useEffect(() => {
    if (!slug) return
    if (!slugIds.has(slug)) slugIds.set(slug, getJson<{ data?: { id?: number } }>(`/api/v1/public/catalog/items/${encodeURIComponent(slug)}`).then((j) => (j?.data?.id ? Number(j.data.id) : null)))
    void slugIds.get(slug)!.then(setId)
  }, [slug])
  return id ? <CompareButton id={id} compact={compact} /> : null
}

function CompareDock() {
  const ids = useCompareIds()
  if (!ids.length) return null
  return (
    <div className="fixed inset-x-3 bottom-3 z-40 mx-auto flex max-w-md items-center justify-between gap-3 rounded-2xl bg-[#22242a] px-4 py-3 text-sm text-white shadow-2xl">
      <span className="inline-flex items-center gap-2"><GitCompareArrows className="h-4 w-4" />{fa(ids.length)} محصول در لیست مقایسه</span>
      <Link href="/compare" className="rounded-xl bg-[#0054ff] px-3 py-1.5 font-bold">مشاهده مقایسه</Link>
    </div>
  )
}

/* ---------- countdown ---------- */
function useNow(active: boolean) {
  const [now, setNow] = useState<number | null>(null)
  useEffect(() => {
    if (!active) return
    setNow(Date.now())
    const t = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(t)
  }, [active])
  return now
}

function Countdown({ ends, big }: { ends: string; big?: boolean }) {
  const target = Date.parse(ends)
  const now = useNow(!Number.isNaN(target))
  if (Number.isNaN(target) || now === null) return null
  const diff = Math.max(0, target - now)
  if (diff === 0) return <span className="text-xs font-bold text-[#9aa0ad]">مهلت این تخفیف تمام شد</span>
  const d = Math.floor(diff / 86400000)
  const h = Math.floor((diff % 86400000) / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  const parts = [...(d ? [{ v: d, l: "روز" }] : []), { v: h, l: "ساعت" }, { v: m, l: "دقیقه" }, { v: s, l: "ثانیه" }]
  return (
    <div className={`flex items-center gap-1.5 ${big ? "" : "text-xs"}`} aria-label="زمان باقی‌مانده تا پایان تخفیف">
      {!big ? <Timer className="h-4 w-4 text-[#e5484d]" /> : null}
      {parts.map((p) => (
        <span key={p.l} className={`grid place-items-center rounded-xl font-black tabular-nums ${big ? "min-w-16 bg-white/15 px-3 py-2 text-2xl" : "min-w-9 bg-[#fff1f1] px-1.5 py-1 text-[#e5484d]"}`}>
          {fa(p.v)}
          <small className={`font-bold ${big ? "text-[11px] opacity-80" : "text-[9px]"}`}>{p.l}</small>
        </span>
      ))}
    </div>
  )
}

/* ---------- product card ---------- */
function ProductCard({ p, eager, showTimer }: { p: ApiProduct; eager?: boolean; showTimer?: boolean }) {
  const [added, setAdded] = useState(false)
  const price = p.discounted_price_minor || p.price_minor
  const off = p.discount_percent ?? 0
  const img = p.cover_image_url || p.image_url
  const short = p.excerpt || plain(p.description)
  return (
    <article className="group flex flex-col overflow-hidden rounded-3xl border border-[#e6e9f2] bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
      <Link href={hrefOf(p)} className="relative block overflow-hidden bg-[#f5f7fc]">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        {img ? <img src={img} alt={p.name} loading={eager ? "eager" : "lazy"} decoding="async" className="aspect-[16/10] w-full object-cover transition group-hover:scale-105" /> : <div className="aspect-[16/10]" />}
        {off > 0 ? <span className="absolute start-3 top-3 rounded-full bg-[#e5484d] px-2.5 py-1 text-xs font-black text-white">{fa(off)}٪ تخفیف</span> : null}
        {p.is_available === false ? <span className="absolute inset-x-3 bottom-3 rounded-full bg-[#22242a]/85 py-1 text-center text-xs font-bold text-white">ناموجود</span> : null}
      </Link>
      <div className="flex flex-1 flex-col p-4">
        {p.category?.name ? <span className="text-xs font-bold text-[#0054ff]">{p.category.name}</span> : null}
        <h3 className="mt-1 font-extrabold leading-7 text-[#22242a]"><Link href={hrefOf(p)} className="hover:text-[#0054ff]">{p.name}</Link></h3>
        {short ? <p className="mt-1 line-clamp-2 text-sm leading-7 text-[#6b7080]">{short}</p> : null}
        {showTimer && p.sale_ends_at ? <div className="mt-3"><Countdown ends={p.sale_ends_at} /></div> : null}
        <div className="mt-auto pt-4">
          <div className="mb-3">
            {price < p.price_minor ? <p className="text-xs text-[#9aa0ad] line-through">{toman(p.price_minor)}</p> : null}
            <p className="font-black text-[#22242a]">{price ? toman(price) : "استعلام قیمت"}</p>
          </div>
          <div className="flex gap-2">
            <button
              type="button"
              disabled={p.is_available === false}
              onClick={() => { addToCart({ slug: p.slug, productId: p.id, name: p.name, price: Math.round(price / 10), tone: "#0054ff", image: img || null }); setAdded(true); window.setTimeout(() => setAdded(false), 2200) }}
              className="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-2xl bg-[#0054ff] text-sm font-bold text-white transition hover:bg-[#0045d1] disabled:opacity-50"
            >
              <ShoppingCart className="h-4 w-4" />{added ? "به سبد اضافه شد" : "افزودن به سبد"}
            </button>
            <CompareButton id={p.id} compact />
          </div>
        </div>
      </div>
    </article>
  )
}

function Grid({ items, loading, empty, showTimer }: { items: ApiProduct[]; loading: boolean; empty: string; showTimer?: boolean }) {
  if (loading) {
    return (
      <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-busy="true">
        {Array.from({ length: 8 }, (_, i) => <div key={i} className="h-96 animate-pulse rounded-3xl bg-[#f2f5fc]" />)}
      </div>
    )
  }
  if (!items.length) return <p className="mt-8 rounded-3xl bg-[#f5f7fc] p-8 text-center text-[#4a4e5a]">{empty}</p>
  return (
    <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      {items.map((p, i) => <ProductCard key={p.id} p={p} eager={i < 2} showTimer={showTimer} />)}
    </div>
  )
}

function Head({ title, intro }: { title: string; intro: string }) {
  return (
    <>
      {title ? <h2 className="text-2xl font-black text-[#22242a] md:text-3xl">{title}</h2> : null}
      {intro ? <p className="mt-2 max-w-3xl leading-8 text-[#4a4e5a]">{intro}</p> : null}
    </>
  )
}

/* ---------- shop-catalog ---------- */
const SORTS: Array<[string, string]> = [["newest", "جدیدترین"], ["best_selling", "پرفروش‌ترین"], ["price_asc", "ارزان‌ترین"], ["price_desc", "گران‌ترین"]]

export function ShopCatalogWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const perPage = Math.max(4, propNum(p, "limit", 12))
  const params = useSearchParams()
  const router = useRouter()
  const pathname = usePathname()
  const onSale = params.get("on_sale") === "1"
  const inStock = params.get("in_stock") === "1"
  const category = params.get("category") ?? ""
  const sort = params.get("sort") ?? "newest"
  const q = params.get("q") ?? ""
  const page = Math.max(1, Number(params.get("page") ?? "1") || 1)
  const [term, setTerm] = useState(q)
  const [state, setState] = useState<{ items: ApiProduct[]; cats: Array<{ slug: string; name: string }>; last: number; total: number; loading: boolean }>({ items: [], cats: [], last: 1, total: 0, loading: true })

  useEffect(() => setTerm(q), [q])
  useEffect(() => {
    let off = false
    const qs = new URLSearchParams({ per_page: String(perPage), page: String(page), sort })
    if (onSale) qs.set("on_sale", "1")
    if (inStock) qs.set("in_stock", "1")
    if (category) qs.set("category", category)
    if (q) qs.set("q", q)
    setState((s) => ({ ...s, loading: true }))
    void getJson<CatalogJson>(`/api/v1/public/catalog?${qs}`).then((j) => {
      if (off) return
      const items = j?.data?.items ?? []
      setState((s) => ({ items, cats: j?.data?.categories?.length ? j.data.categories : s.cats, last: Math.max(1, Math.ceil((Number(j?.data?.pagination?.total ?? items.length) || 0) / perPage)), total: Number(j?.data?.pagination?.total ?? items.length) || 0, loading: false }))
    })
    return () => { off = true }
  }, [category, inStock, onSale, page, perPage, q, sort])

  const set = useCallback((changes: Record<string, string>) => {
    const next = new URLSearchParams(params.toString())
    for (const [k, v] of Object.entries(changes)) { if (v) next.set(k, v); else next.delete(k) }
    if (!("page" in changes)) next.delete("page")
    const s = next.toString()
    router.replace(s ? `${pathname}?${s}` : pathname, { scroll: false })
  }, [params, pathname, router])

  const chip = (active: boolean) => `rounded-full px-4 py-2 text-sm font-bold transition ${active ? "bg-[#22242a] text-white" : "bg-[#f2f5fc] text-[#4a4e5a] hover:bg-[#e6ecfb]"}`
  const saleTitle = propStr(p, "saleTitle", "محصولات تخفیف‌دار")
  return (
    <section className="mx-auto max-w-7xl px-4 py-8">
      <Head title={onSale ? saleTitle : propStr(p, "title", "")} intro={onSale ? propStr(p, "saleIntro", "") : propStr(p, "intro", "")} />
      <div className="mt-6 flex flex-col gap-3 rounded-3xl border border-[#e6e9f2] bg-white p-3 md:flex-row md:items-center md:justify-between">
        <form className="relative flex-1" onSubmit={(e) => { e.preventDefault(); set({ q: term.trim() }) }} role="search">
          <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#9aa0ad]" />
          <input value={term} onChange={(e) => setTerm(e.target.value)} placeholder={propStr(p, "searchPlaceholder", "جستجو در محصولات")} aria-label="جستجو در محصولات" className="h-11 w-full rounded-2xl bg-[#f5f7fc] ps-9 pe-3 text-sm outline-none focus:ring-2 focus:ring-[#0054ff]/30" />
        </form>
        <div className="flex flex-wrap items-center gap-2">
          <button type="button" aria-pressed={onSale} onClick={() => set({ on_sale: onSale ? "" : "1" })} className={`${chip(onSale)} inline-flex items-center gap-1.5`}><BadgePercent className="h-4 w-4" />فقط تخفیف‌دارها</button>
          <button type="button" aria-pressed={inStock} onClick={() => set({ in_stock: inStock ? "" : "1" })} className={chip(inStock)}>فقط موجودها</button>
          <select value={sort} onChange={(e) => set({ sort: e.target.value === "newest" ? "" : e.target.value })} aria-label="مرتب‌سازی" className="h-10 rounded-2xl border border-[#e6e9f2] bg-white px-3 text-sm font-bold text-[#4a4e5a]">
            {SORTS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </div>
      </div>
      {state.cats.length > 1 ? (
        <div className="mt-4 flex gap-2 overflow-x-auto pb-1">
          <button type="button" className={`${chip(!category)} shrink-0`} onClick={() => set({ category: "" })}>همه دسته‌ها</button>
          {state.cats.map((c) => <button key={c.slug} type="button" className={`${chip(category === c.slug)} shrink-0`} onClick={() => set({ category: c.slug })}>{c.name}</button>)}
        </div>
      ) : null}
      {!state.loading ? <p className="mt-4 text-sm text-[#6b7080]">{fa(state.total)} محصول{onSale ? " با تخفیف" : ""}</p> : null}
      <Grid items={state.items} loading={state.loading} empty={onSale ? "الان محصول تخفیف‌داری نداریم. به‌زودی پیشنهادهای تازه اضافه می‌شود." : "محصولی با این فیلترها پیدا نشد."} showTimer={onSale} />
      {state.last > 1 ? (
        <nav className="mt-8 flex flex-wrap justify-center gap-2" aria-label="صفحه‌بندی">
          {Array.from({ length: state.last }, (_, i) => i + 1).map((n) => (
            <button key={n} type="button" aria-current={n === page ? "page" : undefined} onClick={() => set({ page: n === 1 ? "" : String(n) })} className={`h-10 min-w-10 rounded-xl text-sm font-bold ${n === page ? "bg-[#0054ff] text-white" : "bg-[#f2f5fc] text-[#4a4e5a]"}`}>{fa(n)}</button>
          ))}
        </nav>
      ) : null}
      <CompareDock />
    </section>
  )
}

/* ---------- offers-grid ---------- */
export function OffersGridWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const limit = Math.max(1, propNum(p, "limit", 24))
  const [items, setItems] = useState<ApiProduct[] | null>(null)
  useEffect(() => {
    void getJson<CatalogJson>(`/api/v1/public/catalog?on_sale=1&per_page=${limit}&sort=newest`).then((j) => {
      const rows = (j?.data?.items ?? []).filter((x) => (x.discounted_price_minor || x.price_minor) < x.price_minor)
      rows.sort((a, b) => (b.discount_percent ?? 0) - (a.discount_percent ?? 0))
      setItems(rows)
    })
  }, [limit])
  const soonest = useMemo(() => {
    const ends = (items ?? []).map((x) => (x.sale_ends_at ? Date.parse(x.sale_ends_at) : Number.NaN)).filter((t) => !Number.isNaN(t) && t > Date.now())
    return ends.length ? new Date(Math.min(...ends)).toISOString() : ""
  }, [items])
  const best = (items ?? []).reduce((m, x) => Math.max(m, x.discount_percent ?? 0), 0)
  return (
    <section className="mx-auto max-w-7xl px-4 py-8">
      <div className="flex flex-col gap-5 overflow-hidden rounded-[2rem] bg-gradient-to-l from-[#0054ff] to-[#3b2bd6] p-6 text-white md:flex-row md:items-center md:justify-between md:p-8">
        <div>
          <p className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-bold"><BadgePercent className="h-4 w-4" />{propStr(p, "kicker", "پیشنهاد ویژه وبینا")}</p>
          <h2 className="mt-3 text-2xl font-black md:text-3xl">{propStr(p, "title", "پیشنهادهای شگفت‌انگیز")}</h2>
          {propStr(p, "intro", "") ? <p className="mt-2 max-w-2xl leading-8 opacity-90">{propStr(p, "intro", "")}</p> : null}
          {best > 0 ? <p className="mt-3 text-sm font-bold">تا {fa(best)}٪ تخفیف روی {fa(items?.length ?? 0)} محصول</p> : null}
        </div>
        {soonest ? (
          <div>
            <p className="mb-2 text-sm font-bold opacity-90">{propStr(p, "timerTitle", "زمان باقی‌مانده تا پایان نزدیک‌ترین تخفیف")}</p>
            <Countdown ends={soonest} big />
          </div>
        ) : null}
      </div>
      <Grid items={items ?? []} loading={items === null} empty={propStr(p, "emptyText", "الان پیشنهاد فعالی نداریم. به‌زودی پیشنهادهای تازه اضافه می‌شود.")} showTimer />
      <div className="mt-8 text-center">
        <Link href="/shop?on_sale=1" className="inline-flex h-12 items-center rounded-2xl border border-[#0054ff] px-6 font-bold text-[#0054ff] hover:bg-[#eef3ff]">{propStr(p, "moreLabel", "همه محصولات تخفیف‌دار")}</Link>
      </div>
      <CompareDock />
    </section>
  )
}

/* ---------- compare-table ---------- */
export function CompareTableWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const ids = useCompareIds()
  const [items, setItems] = useState<ApiProduct[] | null>(null)
  const [all, setAll] = useState<ApiProduct[]>([])
  const [picker, setPicker] = useState(false)
  const [term, setTerm] = useState("")
  const [added, setAdded] = useState<number | null>(null)
  const key = ids.join(",")

  useEffect(() => {
    let off = false
    void getJson<{ data?: { items?: ApiProduct[] } }>("/api/v1/public/compare").then((j) => { if (!off) setItems(j?.data?.items ?? []) })
    return () => { off = true }
  }, [key])
  useEffect(() => {
    if (!picker || all.length) return
    void getJson<CatalogJson>("/api/v1/public/catalog?per_page=60").then((j) => setAll(j?.data?.items ?? []))
  }, [picker, all.length])

  const rows = items ?? []
  const specNames = useMemo(() => Array.from(new Set(rows.flatMap((r) => specsOf(r.attributes).map((s) => s.name)))), [rows])
  const choices = all.filter((x) => !ids.includes(x.id) && (!term.trim() || x.name.includes(term.trim()) || (x.category?.name ?? "").includes(term.trim())))
  const max = 4
  const cell = "border-b border-[#eef0f6] p-3 align-top text-sm leading-7"
  const th = `${cell} sticky start-0 z-10 w-32 bg-[#f8f9fd] text-start font-bold text-[#22242a] md:w-44`

  const line = (label: string, render: (r: ApiProduct) => ReactNode) => (
    <tr>
      <th scope="row" className={th}>{label}</th>
      {rows.map((r) => <td key={r.id} className={`${cell} text-[#4a4e5a]`}>{render(r)}</td>)}
    </tr>
  )

  return (
    <section className="mx-auto max-w-7xl px-4 py-8">
      <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div><Head title={propStr(p, "title", "مقایسه محصولات")} intro={propStr(p, "intro", "")} /></div>
        <div className="flex gap-2">
          {rows.length < max ? <button type="button" onClick={() => setPicker((v) => !v)} className="inline-flex h-11 items-center gap-2 rounded-2xl bg-[#0054ff] px-4 text-sm font-bold text-white"><Plus className="h-4 w-4" />افزودن محصول</button> : null}
          {rows.length ? <button type="button" onClick={() => void Promise.all(rows.map((r) => toggleCompare(r.id, false)))} className="inline-flex h-11 items-center gap-2 rounded-2xl border border-[#e6e9f2] px-4 text-sm font-bold text-[#4a4e5a]"><Trash2 className="h-4 w-4" />پاک کردن همه</button> : null}
        </div>
      </div>

      {picker ? (
        <div className="mt-4 rounded-3xl border border-[#e6e9f2] bg-white p-4">
          <div className="flex items-center gap-2">
            <div className="relative flex-1">
              <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#9aa0ad]" />
              <input autoFocus value={term} onChange={(e) => setTerm(e.target.value)} placeholder="نام محصول را بنویسید" aria-label="جستجوی محصول برای مقایسه" className="h-11 w-full rounded-2xl bg-[#f5f7fc] ps-9 pe-3 text-sm outline-none" />
            </div>
            <button type="button" onClick={() => setPicker(false)} className="grid h-11 w-11 place-items-center rounded-2xl bg-[#f2f5fc]" aria-label="بستن"><X className="h-4 w-4" /></button>
          </div>
          <ul className="mt-3 grid max-h-80 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
            {choices.map((c) => (
              <li key={c.id}>
                <button type="button" onClick={() => { void toggleCompare(c.id, true); if (rows.length + 1 >= max) setPicker(false) }} className="flex w-full items-center gap-3 rounded-2xl border border-[#eef0f6] p-2 text-start hover:border-[#0054ff]">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  {c.image_url ? <img src={c.image_url} alt="" loading="lazy" className="h-12 w-16 shrink-0 rounded-xl object-cover" /> : <span className="h-12 w-16 shrink-0 rounded-xl bg-[#f2f5fc]" />}
                  <span className="min-w-0"><span className="block truncate text-sm font-bold text-[#22242a]">{c.name}</span><span className="text-xs text-[#6b7080]">{toman(c.discounted_price_minor || c.price_minor)}</span></span>
                </button>
              </li>
            ))}
            {!choices.length ? <li className="p-3 text-sm text-[#6b7080]">{all.length ? "محصول دیگری پیدا نشد." : "در حال بارگذاری محصولات"}</li> : null}
          </ul>
        </div>
      ) : null}

      {items === null ? <div className="mt-6 h-96 animate-pulse rounded-3xl bg-[#f2f5fc]" /> : null}
      {items !== null && !rows.length ? (
        <div className="mt-6 rounded-3xl bg-[#f5f7fc] p-8 text-center">
          <GitCompareArrows className="mx-auto h-10 w-10 text-[#0054ff]" />
          <p className="mt-3 font-bold text-[#22242a]">{propStr(p, "emptyText", "هنوز محصولی برای مقایسه انتخاب نکرده‌اید.")}</p>
          <p className="mt-1 text-sm text-[#6b7080]">روی دکمه مقایسه در کارت هر محصول بزنید یا از همین‌جا محصول اضافه کنید.</p>
          <div className="mt-4 flex flex-wrap justify-center gap-2">
            <button type="button" onClick={() => setPicker(true)} className="h-11 rounded-2xl bg-[#0054ff] px-5 text-sm font-bold text-white">افزودن محصول</button>
            <Link href="/shop" className="inline-flex h-11 items-center rounded-2xl border border-[#e6e9f2] bg-white px-5 text-sm font-bold text-[#4a4e5a]">رفتن به فروشگاه</Link>
          </div>
        </div>
      ) : null}

      {rows.length ? (
        <div className="mt-6 overflow-x-auto rounded-3xl border border-[#e6e9f2] bg-white">
          <table className="w-full min-w-[640px] border-collapse">
            <thead>
              <tr>
                <th scope="col" className={`${th} align-bottom`}>{fa(rows.length)} از {fa(max)} محصول</th>
                {rows.map((r) => {
                  const img = r.cover_image_url || r.image_url
                  return (
                    <td key={r.id} className={`${cell} min-w-48`}>
                      <div className="relative">
                        <button type="button" onClick={() => void toggleCompare(r.id, false)} aria-label={`حذف ${r.name} از مقایسه`} className="absolute end-1 top-1 z-10 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-[#e5484d] shadow"><X className="h-4 w-4" /></button>
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        {img ? <img src={img} alt={r.name} className="aspect-[16/10] w-full rounded-2xl object-cover" /> : <div className="aspect-[16/10] rounded-2xl bg-[#f2f5fc]" />}
                      </div>
                      <Link href={hrefOf(r)} className="mt-2 block font-extrabold text-[#22242a] hover:text-[#0054ff]">{r.name}</Link>
                    </td>
                  )
                })}
              </tr>
            </thead>
            <tbody>
              {line("قیمت", (r) => {
                const price = r.discounted_price_minor || r.price_minor
                return <>{price < r.price_minor ? <s className="block text-xs text-[#9aa0ad]">{toman(r.price_minor)}</s> : null}<b className="text-[#22242a]">{toman(price)}</b></>
              })}
              {line("تخفیف", (r) => (r.discount_percent ? <span className="rounded-full bg-[#fff1f1] px-2 py-0.5 font-bold text-[#e5484d]">{fa(r.discount_percent)}٪</span> : "ندارد"))}
              {rows.some((r) => r.sale_ends_at) ? line("پایان تخفیف", (r) => (r.sale_ends_at ? <Countdown ends={r.sale_ends_at} /> : "بدون محدودیت زمانی")) : null}
              {line("دسته", (r) => r.category?.name || "عمومی")}
              {line("نوع", (r) => KIND[r.kind ?? ""] ?? "محصول")}
              {line("تحویل", (r) => (r.digital ? "آنلاین و فوری" : "ارسال فیزیکی"))}
              {line("موجودی", (r) => (r.is_available === false ? <span className="text-[#e5484d]">ناموجود</span> : <span className="text-[#12a150]">موجود</span>))}
              {line("امتیاز کاربران", (r) => (r.reviews_count ? <span className="inline-flex items-center gap-1"><Star className="h-4 w-4 fill-amber-400 text-amber-400" />{fa(r.rating_avg ?? 0)} از ۵ ({fa(r.reviews_count)} نظر)</span> : "هنوز نظری ثبت نشده"))}
              {specNames.map((n) => line(n, (r) => specsOf(r.attributes).find((s) => s.name === n)?.value || "ندارد"))}
              {line("توضیح", (r) => <span className="line-clamp-4">{r.excerpt || plain(r.description) || "ندارد"}</span>)}
              <tr>
                <th scope="row" className={th}>خرید</th>
                {rows.map((r) => (
                  <td key={r.id} className={cell}>
                    <button
                      type="button"
                      disabled={r.is_available === false}
                      onClick={() => { const price = r.discounted_price_minor || r.price_minor; addToCart({ slug: r.slug, productId: r.id, name: r.name, price: Math.round(price / 10), tone: "#0054ff", image: r.image_url || null }); setAdded(r.id); window.setTimeout(() => setAdded(null), 2200) }}
                      className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-2xl bg-[#0054ff] text-sm font-bold text-white disabled:opacity-50"
                    >
                      <ShoppingCart className="h-4 w-4" />{added === r.id ? "به سبد اضافه شد" : "افزودن به سبد"}
                    </button>
                  </td>
                ))}
              </tr>
            </tbody>
          </table>
        </div>
      ) : null}
    </section>
  )
}
