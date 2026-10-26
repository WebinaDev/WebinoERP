"use client"

/* Portfolio grid: published items from داشبورد ← بازاریابی ← نمونه‌کار, filter chips from «دسته‌های نمونه‌کار».
 * Chips are sticky on desktop and scroll horizontally on mobile; optional newest/featured sort; 3/2/1 columns;
 * an editable empty state while nothing is published. */

import Link from "next/link"
import { useEffect, useMemo, useState } from "react"

import { optImg } from "@/lib/image-url"
import { propStr } from "../props"
import type { WidgetNode } from "../types"
import "./portfolio-widget.css"

type Category = { id: number; slug: string; name: string }
type Item = {
  id: number
  slug: string
  title: string
  result_metric?: string | null
  cover_url?: string | null
  featured?: boolean
  category?: { slug: string; name: string } | null
}

const api = (path: string) => `${process.env.NEXT_PUBLIC_API_URL?.trim() || "/api"}/v1/public/${path}`

export function PortfolioGridWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const onlyCategory = propStr(p, "category").replace(/[^\w-]/g, "")
  const showSort = p.showSort !== false && p.showSort !== "false"
  const limit = Math.max(1, Math.min(50, Number(p.limit) || 50))
  const [categories, setCategories] = useState<Category[]>([])
  const [items, setItems] = useState<Item[] | null>(null)
  const [active, setActive] = useState(onlyCategory)
  const [sort, setSort] = useState<"newest" | "featured">("newest")

  useEffect(() => {
    if (onlyCategory) return
    fetch(api("portfolio-categories"))
      .then((r) => (r.ok ? r.json() : { data: [] }))
      .then((j) => setCategories(Array.isArray(j.data) ? j.data : []))
      .catch(() => setCategories([]))
  }, [onlyCategory])

  useEffect(() => {
    const qs = new URLSearchParams({ per_page: String(limit), sort })
    if (active) qs.set("category", active)
    let cancelled = false
    fetch(api(`portfolio?${qs}`))
      .then((r) => (r.ok ? r.json() : { data: [] }))
      .then((j) => !cancelled && setItems(Array.isArray(j.data) ? j.data : []))
      .catch(() => !cancelled && setItems([]))
    return () => {
      cancelled = true
    }
  }, [active, sort, limit])

  const chips = useMemo(() => [{ slug: "", name: propStr(p, "allLabel", "همه") }, ...categories], [categories, p])
  const nothingPublished = items !== null && items.length === 0 && !active

  if (nothingPublished) {
    const text = propStr(p, "emptyText")
    const a = [propStr(p, "emptyPrimaryLabel"), propStr(p, "emptyPrimaryHref")]
    const b = [propStr(p, "emptySecondaryLabel"), propStr(p, "emptySecondaryHref")]
    return (
      <div className="wbx-pf-empty">
        <h2>{propStr(p, "emptyTitle", "به‌زودی")}</h2>
        {text ? text.split("\n").map((line, i) => <p key={i}>{line}</p>) : null}
        <div className="wbx-pf-empty-actions">
          {a[0] && a[1] ? <Link href={a[1]} className="wbx-pf-btn wbx-pf-btn-primary">{a[0]}</Link> : null}
          {b[0] && b[1] ? <Link href={b[1]} className="wbx-pf-btn">{b[0]}</Link> : null}
        </div>
      </div>
    )
  }

  return (
    <div className="wbx-pf">
      {!onlyCategory && (chips.length > 1 || showSort) ? (
        <div className="wbx-pf-bar">
          <div className="wbx-pf-chips" role="tablist">
            {chips.map((c) => (
              <button key={c.slug || "all"} type="button" role="tab" aria-selected={active === c.slug}
                className={`wbx-pf-chip${active === c.slug ? " is-active" : ""}`} onClick={() => setActive(c.slug)}>
                {c.name}
              </button>
            ))}
          </div>
          {showSort ? (
            <select className="wbx-pf-sort" value={sort} onChange={(e) => setSort(e.target.value as "newest" | "featured")} aria-label="مرتب‌سازی">
              <option value="newest">جدیدترین</option>
              <option value="featured">شاخص‌ترین</option>
            </select>
          ) : null}
        </div>
      ) : null}
      {items === null ? (
        <p className="wbx-pf-note">در حال بارگذاری…</p>
      ) : items.length === 0 ? (
        <p className="wbx-pf-note">در این دسته هنوز نمونه‌کاری منتشر نشده است.</p>
      ) : (
        <ul className="wbx-pf-grid">
          {items.map((item) => (
            <li key={item.id} className="wbx-pf-card">
              <Link href={`/portfolio/${item.slug}`} className="wbx-pf-link">
                <div className="wbx-pf-cover">
                  {item.cover_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img {...optImg(item.cover_url, "(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 400px")} alt={item.title} loading="lazy" decoding="async" />
                  ) : (
                    <span className="wbx-pf-placeholder" aria-hidden />
                  )}
                  <span className="wbx-pf-overlay"><span className="wbx-pf-btn wbx-pf-btn-primary">{propStr(p, "detailLabel", "مشاهده جزئیات")}</span></span>
                </div>
                <div className="wbx-pf-body">
                  {item.category?.name ? <span className="wbx-pf-tag">{item.category.name}</span> : null}
                  <h3>{item.title}</h3>
                  {item.result_metric ? <p>{item.result_metric}</p> : null}
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
