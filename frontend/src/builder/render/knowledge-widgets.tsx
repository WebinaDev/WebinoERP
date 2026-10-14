"use client"

/* Knowledge-center listing widgets. Every item comes from داشبورد ← بازاریابی (blog, academy, magazine,
 * downloads, FAQ, announcements); the widgets hold only labels, never content. */

import Link from "next/link"
import { useEffect, useMemo, useState } from "react"

import { propStr } from "../props"
import type { WidgetNode } from "../types"
import "./portfolio-widget.css"
import "./knowledge-widgets.css"

const api = (path: string) => `${process.env.NEXT_PUBLIC_API_URL?.trim() || "/api"}/v1/public/${path}`
const fa = (n: number | string) => String(n).replace(/\d/g, (d) => "۰۱۲۳۴۵۶۷۸۹"[Number(d)])

function useJson<T>(path: string | null, pick: (j: Record<string, unknown>) => T, fallback: T): T | null {
  const [data, setData] = useState<T | null>(null)
  useEffect(() => {
    if (!path) return
    let cancelled = false
    setData(null)
    fetch(api(path), { headers: { Accept: "application/json" } })
      .then((r) => (r.ok ? r.json() : {}))
      .then((j) => !cancelled && setData(pick(j)))
      .catch(() => !cancelled && setData(fallback))
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path])
  return data
}
const arr = <T,>(v: unknown): T[] => (Array.isArray(v) ? (v as T[]) : [])

function jalali(iso?: string | null, opts: Intl.DateTimeFormatOptions = { year: "numeric", month: "long", day: "numeric" }) {
  if (!iso) return ""
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? "" : new Intl.DateTimeFormat("fa-IR-u-ca-persian", opts).format(d)
}
function jalaliParts(iso?: string | null): { y: string; m: string; mName: string } | null {
  if (!iso) return null
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return null
  const parts = new Intl.DateTimeFormat("en-US-u-ca-persian-nu-latn", { year: "numeric", month: "numeric" }).formatToParts(d)
  const y = parts.find((p) => p.type === "year")?.value ?? ""
  const m = parts.find((p) => p.type === "month")?.value ?? ""
  return { y, m, mName: new Intl.DateTimeFormat("fa-IR-u-ca-persian", { month: "long" }).format(d) }
}

function Chips({ items, active, onPick }: { items: { key: string; label: string }[]; active: string; onPick: (k: string) => void }) {
  return (
    <div className="wbx-pf-chips" role="tablist">
      {items.map((c) => (
        <button key={c.key || "all"} type="button" role="tab" aria-selected={active === c.key} className={`wbx-pf-chip${active === c.key ? " is-active" : ""}`} onClick={() => onPick(c.key)}>
          {c.label}
        </button>
      ))}
    </div>
  )
}
function Empty({ text }: { text: string }) {
  return <p className="wbx-pf-note">{text}</p>
}
function Cover({ src, alt }: { src?: string | null; alt: string }) {
  return <div className="wbx-pf-cover">{src ? <img src={src} alt={alt} loading="lazy" /> : <span className="wbx-pf-placeholder" />}</div>
}

/* ---------------- blog ---------------- */
type Post = { id: number; slug: string; title: string; excerpt?: string | null; cover_url?: string | null; published_at?: string | null; read_minutes?: number; category?: { slug: string; name: string } | null }

export function BlogListWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const limit = Math.max(1, Math.min(50, Number(p.limit) || 12))
  const [active, setActive] = useState("")
  const cats = useJson("blog-categories", (j) => arr<{ slug: string; name: string }>(j.data), [])
  const posts = useJson(`blog?per_page=${limit}${active ? `&category=${encodeURIComponent(active)}` : ""}`, (j) => arr<Post>(j.data), [])
  const chips = useMemo(() => [{ key: "", label: propStr(p, "allLabel", "همه") }, ...(cats ?? []).map((c) => ({ key: c.slug, label: c.name }))], [cats, p])
  return (
    <div className="wbx-pf">
      {chips.length > 1 ? <div className="wbx-pf-bar"><Chips items={chips} active={active} onPick={setActive} /></div> : null}
      {posts === null ? <Empty text="در حال بارگذاری…" /> : posts.length === 0 ? <Empty text={propStr(p, "emptyText", "هنوز مطلبی منتشر نشده است.")} /> : (
        <ul className="wbx-pf-grid">
          {posts.map((it) => (
            <li key={it.id} className="wbx-pf-card">
              <Link href={`/blog/${it.slug}`} className="wbx-pf-link">
                <Cover src={it.cover_url} alt={it.title} />
                <div className="wbx-pf-body">
                  {it.category ? <span className="wbx-pf-tag">{it.category.name}</span> : null}
                  <h3>{it.title}</h3>
                  {it.excerpt ? <p>{it.excerpt}</p> : null}
                  <span className="wbx-kn-meta">{[jalali(it.published_at), it.read_minutes ? `${fa(it.read_minutes)} دقیقه مطالعه` : ""].filter(Boolean).join(" · ")}</span>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

/* ---------------- academy ---------------- */
type Course = { id: number; slug: string; title: string; description?: string | null; cover_url?: string | null; level?: string | null; duration?: string | null; is_free?: boolean }

export function AcademyGridWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const courses = useJson("academy", (j) => arr<Course>(j.data), [])
  if (courses === null) return <Empty text="در حال بارگذاری…" />
  if (courses.length === 0) return <Empty text={propStr(p, "emptyText", "دوره‌ها به‌زودی منتشر می‌شوند.")} />
  return (
    <ul className="wbx-pf-grid">
      {courses.map((c) => (
        <li key={c.id} className="wbx-pf-card">
          <Link href={`/academy/${c.slug}`} className="wbx-pf-link">
            <Cover src={c.cover_url} alt={c.title} />
            <div className="wbx-pf-body">
              <div className="wbx-kn-badges">
                {c.level ? <span className="wbx-pf-tag">{c.level}</span> : null}
                {c.duration ? <span className="wbx-kn-pill">{c.duration}</span> : null}
                {c.is_free ? <span className="wbx-kn-pill">رایگان</span> : null}
              </div>
              <h3>{c.title}</h3>
              {c.description ? <p>{c.description.split("\n")[0]}</p> : null}
              <span className="wbx-kn-more">{propStr(p, "detailLabel", "مشاهده دوره")}</span>
            </div>
          </Link>
        </li>
      ))}
    </ul>
  )
}

/* ---------------- magazine ---------------- */
type Mag = Post & { issue?: string | null; author?: string | null; featured?: boolean }

export function MagazineFeatureWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const [issue, setIssue] = useState("")
  const res = useJson(`magazine?per_page=30${issue ? `&issue=${encodeURIComponent(issue)}` : ""}`, (j) => ({ items: arr<Mag>(j.data), issues: arr<string>((j.meta as Record<string, unknown> | undefined)?.issues) }), { items: [], issues: [] })
  const [issues, setIssues] = useState<string[]>([])
  useEffect(() => {
    if (res && !issue) setIssues(res.issues)
  }, [res, issue])
  if (res === null) return <Empty text="در حال بارگذاری…" />
  const [lead, ...rest] = res.items
  const meta = (m: Mag) => [m.issue, m.author, jalali(m.published_at)].filter(Boolean).join(" · ")
  return (
    <div className="wbx-pf">
      {issues.length ? <div className="wbx-pf-bar"><Chips items={[{ key: "", label: propStr(p, "allLabel", "همه شماره‌ها") }, ...issues.map((i) => ({ key: i, label: i }))]} active={issue} onPick={setIssue} /></div> : null}
      {!lead ? <Empty text={propStr(p, "emptyText", "نخستین شماره مجله به‌زودی منتشر می‌شود.")} /> : (
        <>
          <Link href={`/magazine/${lead.slug}`} className="wbx-kn-feature">
            <Cover src={lead.cover_url} alt={lead.title} />
            <div className="wbx-kn-feature-body">
              <span className="wbx-pf-tag">{lead.featured ? propStr(p, "featuredLabel", "ویژه") : lead.issue || propStr(p, "featuredLabel", "ویژه")}</span>
              <h2>{lead.title}</h2>
              {lead.excerpt ? <p>{lead.excerpt}</p> : null}
              <span className="wbx-kn-meta">{meta(lead)}</span>
            </div>
          </Link>
          {rest.length ? (
            <ul className="wbx-pf-grid">
              {rest.map((m) => (
                <li key={m.id} className="wbx-pf-card">
                  <Link href={`/magazine/${m.slug}`} className="wbx-pf-link">
                    <Cover src={m.cover_url} alt={m.title} />
                    <div className="wbx-pf-body"><h3>{m.title}</h3>{m.excerpt ? <p>{m.excerpt}</p> : null}<span className="wbx-kn-meta">{meta(m)}</span></div>
                  </Link>
                </li>
              ))}
            </ul>
          ) : null}
        </>
      )}
    </div>
  )
}

/* ---------------- downloads ---------------- */
type Dl = { id: number; title: string; description?: string | null; file_type?: string | null; gated?: boolean }
const FILE_TYPES: Record<string, { label: string; icon: string }> = {
  checklist: { label: "چک‌لیست", icon: "ri-checkbox-multiple-line" },
  template: { label: "قالب", icon: "ri-layout-2-line" },
  guide: { label: "راهنما", icon: "ri-book-open-line" },
  worksheet: { label: "ورک‌شیت", icon: "ri-file-list-3-line" },
}

export function DownloadsListWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const [type, setType] = useState("")
  const items = useJson(`downloads${type ? `?type=${encodeURIComponent(type)}` : ""}`, (j) => arr<Dl>(j.data), [])
  const [open, setOpen] = useState<Dl | null>(null)
  const chips = [{ key: "", label: propStr(p, "allLabel", "همه") }, ...Object.entries(FILE_TYPES).filter(([k]) => k !== "worksheet").map(([key, v]) => ({ key, label: v.label }))]
  return (
    <div className="wbx-pf">
      <div className="wbx-pf-bar"><Chips items={chips} active={type} onPick={setType} /></div>
      {items === null ? <Empty text="در حال بارگذاری…" /> : items.length === 0 ? <Empty text={propStr(p, "emptyText", "منابع دانلودی به‌زودی اضافه می‌شوند.")} /> : (
        <ul className="wbx-pf-grid">
          {items.map((d) => {
            const ft = FILE_TYPES[d.file_type ?? ""]
            return (
              <li key={d.id} className="wbx-pf-card wbx-kn-dl">
                <div className="wbx-pf-body">
                  <i className={`${ft?.icon ?? "ri-file-download-line"} wbx-kn-icon`} aria-hidden />
                  {ft ? <span className="wbx-pf-tag">{ft.label}</span> : null}
                  <h3>{d.title}</h3>
                  {d.description ? <p>{d.description}</p> : null}
                  <button type="button" className="wbx-pf-btn wbx-pf-btn-primary" onClick={() => setOpen(d)}>{propStr(p, "buttonLabel", "دریافت رایگان")}</button>
                </div>
              </li>
            )
          })}
        </ul>
      )}
      {open ? <DownloadGate item={open} widget={widget} onClose={() => setOpen(null)} /> : null}
    </div>
  )
}

function DownloadGate({ item, widget, onClose }: { item: Dl; widget: WidgetNode; onClose: () => void }) {
  const p = widget.props
  const [state, setState] = useState<"form" | "sending" | "done" | "error">("form")
  const [result, setResult] = useState<{ url?: string | null; related_url?: string }>({})
  const [startedAt] = useState(() => Date.now())
  const submit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    const f = new FormData(e.currentTarget)
    setState("sending")
    fetch(api(`downloads/${item.id}/request`), {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ name: f.get("name"), email: f.get("email"), phone: f.get("phone"), company: f.get("company"), _hp: f.get("website"), _t: Date.now() - startedAt }),
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((j) => {
        setResult(j.data ?? {})
        setState("done")
      })
      .catch(() => setState("error"))
  }
  return (
    <div className="wbx-kn-modal" role="dialog" aria-modal="true" aria-label={item.title} onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="wbx-kn-modal-box">
        <button type="button" className="wbx-kn-close" aria-label="بستن" onClick={onClose}>×</button>
        <h3>{item.title}</h3>
        {state === "done" ? (
          <div className="wbx-kn-done">
            <p>{propStr(p, "doneText", "ممنون! فایل آماده دانلود است.")}</p>
            {result.url ? <a href={result.url} className="wbx-pf-btn wbx-pf-btn-primary" target="_blank" rel="noopener">{propStr(p, "downloadLabel", "دانلود فایل")}</a> : <p>{propStr(p, "noFileText", "فایل به‌زودی برای شما ارسال می‌شود.")}</p>}
            <p className="wbx-kn-offer">{propStr(p, "offerText", "می‌خواهید این موضوع را برای کسب‌وکار خودتان بررسی کنیم؟")}</p>
            <Link href={result.related_url || "/consultation"} className="wbx-pf-btn">{propStr(p, "offerLabel", "دریافت مشاوره رایگان")}</Link>
          </div>
        ) : (
          <form onSubmit={submit} className="wbx-kn-form">
            {item.gated !== false ? <p>{propStr(p, "gateText", "برای دریافت فایل، نام و ایمیل خود را وارد کنید.")}</p> : null}
            <input name="name" required={item.gated !== false} placeholder="نام و نام خانوادگی" maxLength={190} />
            <input name="email" type="email" required={item.gated !== false} placeholder="ایمیل" maxLength={190} dir="ltr" />
            <input name="company" placeholder="نام شرکت (اختیاری)" maxLength={190} />
            <input name="website" tabIndex={-1} autoComplete="off" className="wbx-kn-hp" aria-hidden />
            {state === "error" ? <p className="wbx-kn-err">ارسال انجام نشد؛ دوباره تلاش کنید.</p> : null}
            <button type="submit" className="wbx-pf-btn wbx-pf-btn-primary" disabled={state === "sending"}>{state === "sending" ? "در حال ارسال…" : propStr(p, "submitLabel", "دریافت فایل")}</button>
          </form>
        )}
      </div>
    </div>
  )
}

/* ---------------- FAQ ---------------- */
type Faq = { id: number; question: string; answer: string }
type Group = { name: string; items: Faq[] }

export function FaqCategorizedWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const groups = useJson("faq", (j) => arr<Group>((j.meta as Record<string, unknown> | undefined)?.groups), [])
  const [q, setQ] = useState("")
  const order = propStr(p, "order", "عمومی\nخدمات و پروژه‌ها\nمشاوره و پروپوزال\nهمکاری با ما\nپرداخت و قرارداد").split("\n").map((s) => s.trim()).filter(Boolean)
  const sorted = useMemo(() => [...(groups ?? [])].sort((a, b) => (order.indexOf(a.name) + 1 || 99) - (order.indexOf(b.name) + 1 || 99)), [groups, order])
  const needle = q.trim()
  const shown = sorted.map((g) => ({ ...g, items: needle ? g.items.filter((i) => i.question.includes(needle) || i.answer.includes(needle)) : g.items })).filter((g) => g.items.length)
  const all = sorted.flatMap((g) => g.items)
  const schema = all.length ? { "@context": "https://schema.org", "@type": "FAQPage", mainEntity: all.map((i) => ({ "@type": "Question", name: i.question, acceptedAnswer: { "@type": "Answer", text: i.answer } })) } : null
  const ctaText = propStr(p, "ctaText", "جوابت را پیدا نکردی؟")
  const ctaLabel = propStr(p, "ctaLabel", "مشاوره بگیر")
  const ctaHref = propStr(p, "ctaHref", "/consultation")
  return (
    <div className="wbx-kn-faq">
      {schema ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(schema).replace(/</g, "\\u003c") }} /> : null}
      <input type="search" className="wbx-kn-search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={propStr(p, "searchPlaceholder", "جست‌وجو در پرسش‌ها…")} aria-label="جست‌وجو" />
      {groups === null ? <Empty text="در حال بارگذاری…" /> : shown.length === 0 ? <Empty text={needle ? propStr(p, "noResultText", "پرسشی با این عبارت پیدا نشد.") : propStr(p, "emptyText", "پرسش‌ها به‌زودی اضافه می‌شوند.")} /> : shown.map((g) => (
        <section key={g.name} className="wbx-kn-faq-group">
          <h2>{g.name}</h2>
          {g.items.map((i) => (
            <details key={i.id} className="wbx-kn-faq-item" open={!!needle}>
              <summary>{i.question}</summary>
              <div className="wbx-kn-faq-a">{i.answer.split("\n").map((l, k) => <p key={k}>{l}</p>)}</div>
            </details>
          ))}
          <p className="wbx-kn-faq-cta">{ctaText} <Link href={ctaHref}>{ctaLabel}</Link></p>
        </section>
      ))}
    </div>
  )
}

/* ---------------- announcements ---------------- */
type Ann = { id: number; slug?: string | null; title: string; summary?: string | null; type?: string | null; pinned?: boolean; starts_at?: string | null; created_at?: string | null }
const ANN_TYPES: Record<string, string> = { event: "رویداد", update: "به‌روزرسانی", important: "مهم", news: "خبر سازمانی" }

export function AnnouncementsListWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const items = useJson("announcements?archive=1", (j) => arr<Ann>(j.data), [])
  const [type, setType] = useState("")
  const [period, setPeriod] = useState("")
  const dated = useMemo(() => (items ?? []).map((a) => ({ a, d: jalaliParts(a.starts_at || a.created_at) })), [items])
  const periods = useMemo(() => {
    const out: { key: string; label: string }[] = []
    const seen = new Set<string>()
    for (const { d } of dated) {
      if (!d) continue
      for (const [key, label] of [[d.y, `سال ${fa(d.y)}`], [`${d.y}-${d.m}`, `${d.mName} ${fa(d.y)}`]]) if (!seen.has(key)) { seen.add(key); out.push({ key, label }) }
    }
    return out
  }, [dated])
  const shown = dated.filter(({ a, d }) => (!type || a.type === type) && (!period || (d && (period.includes("-") ? `${d.y}-${d.m}` === period : d.y === period))))
  const chips = [{ key: "", label: propStr(p, "allLabel", "همه") }, ...Object.entries(ANN_TYPES).map(([key, label]) => ({ key, label }))]
  return (
    <div className="wbx-pf">
      <div className="wbx-pf-bar">
        <Chips items={chips} active={type} onPick={setType} />
        {periods.length ? (
          <select className="wbx-pf-sort" value={period} onChange={(e) => setPeriod(e.target.value)} aria-label="آرشیو">
            <option value="">{propStr(p, "archiveLabel", "آرشیو ماهانه / سالانه")}</option>
            {periods.map((o) => <option key={o.key} value={o.key}>{o.label}</option>)}
          </select>
        ) : null}
      </div>
      {items === null ? <Empty text="در حال بارگذاری…" /> : shown.length === 0 ? <Empty text={propStr(p, "emptyText", "اطلاعیه‌ای منتشر نشده است.")} /> : (
        <ul className="wbx-kn-ann">
          {shown.map(({ a }) => (
            <li key={a.id}>
              <Link href={`/announcements/${a.slug || a.id}`}>
                <div className="wbx-kn-badges">
                  {a.type && ANN_TYPES[a.type] ? <span className={`wbx-kn-type is-${a.type}`}>{ANN_TYPES[a.type]}</span> : null}
                  {a.pinned ? <span className="wbx-kn-pill">سنجاق‌شده</span> : null}
                  <time className="wbx-kn-meta">{jalali(a.starts_at || a.created_at)}</time>
                </div>
                <h3>{a.title}</h3>
                {a.summary ? <p>{a.summary}</p> : null}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
