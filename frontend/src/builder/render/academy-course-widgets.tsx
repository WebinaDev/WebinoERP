"use client"

/* Academy course widgets (صفحه‌ساز). Course data (syllabus, lessons, quizzes, instructor) comes from
 * داشبورد ← بازاریابی ← آکادمی via /v1/public/academy/{slug}; the widgets hold only page copy and labels. */

import Link from "next/link"
import { usePathname } from "next/navigation"
import { useEffect, useState, useSyncExternalStore, type ReactNode } from "react"

import { addToCart, cartSnapshot, subscribeCart } from "../cart"

import { propStr } from "../props"
import type { WidgetNode } from "../types"
import { useBuilderRuntime } from "./runtime"

type Quiz = { questions?: number; pass_score?: number; minutes?: number; attempts?: number; sample?: { q: string; options: string[]; answer: number }[] }
type Lesson = { title: string; slug: string; summary?: string | null; chapter_id?: number | null; duration_minutes?: number | null; kind?: string | null; is_preview?: boolean; quiz?: Quiz | null }
type Lms = {
  tagline?: string; students_count?: number; rating?: number; reviews_count?: number; updated_label?: string; language?: string; access?: string; certificate?: string; total_minutes?: number
  instructor?: { name: string; title?: string; bio?: string; years?: number; courses?: number; students?: number; skills?: string[] }
  audience?: string[]; prerequisites?: string[]; projects?: string[]
  materials?: { title: string; type?: string; size?: string }[]
  faq?: { q: string; a: string }[]
  reviews?: { name: string; role?: string; rating?: number; text: string }[]
}
export type AcademyCourse = {
  id?: number; slug?: string; title: string; description?: string | null; cover_url?: string | null; level?: string | null; duration?: string | null
  outcomes?: string | null; enroll_url?: string | null; is_free?: boolean; lms?: Lms | null
  lessons?: Lesson[]; chapters?: { id: number; title: string; summary?: string | null }[]
}

const api = (path: string) => `${process.env.NEXT_PUBLIC_API_URL?.trim() || "/api"}${path}`
const fa = (n: number | string) => String(n).replace(/\d/g, (d) => "۰۱۲۳۴۵۶۷۸۹"[Number(d)])
const faNum = (n: number) => fa(n.toLocaleString("en-US").replace(/,/g, "٬"))
const mins = (m: number) => (m >= 60 ? `${fa(Math.floor(m / 60))} ساعت${m % 60 ? ` و ${fa(m % 60)} دقیقه` : ""}` : `${fa(m)} دقیقه`)
const KIND: Record<string, { label: string; icon: string }> = {
  video: { label: "ویدیو", icon: "▶" }, reading: { label: "مطالعه", icon: "📄" }, quiz: { label: "آزمونک", icon: "✎" },
  exam: { label: "آزمون جامع", icon: "🏁" }, project: { label: "پروژه عملی", icon: "🛠" }, live: { label: "جلسه زنده", icon: "●" },
}
const PRIMARY = "var(--wb-primary,#0054ff)"
const propBool = (p: Record<string, unknown>, k: string, d: boolean) => { const v = p[k]; return typeof v === "boolean" ? v : v === "true" ? true : v === "false" ? false : d }
const lines = (s: string) => s.split("\n").map((x) => x.trim()).filter(Boolean)

function usePrefetched<T>(path: string | null): T | null {
  const pre = useBuilderRuntime().prefetch
  const seeded = path && pre && path in pre ? (pre[path] as T) : undefined
  const [data, setData] = useState<T | null>(seeded ?? null)
  useEffect(() => {
    if (!path || seeded !== undefined) return
    let off = false
    fetch(api(path), { headers: { Accept: "application/json" } })
      .then((r) => (r.ok ? r.json() : Promise.resolve({} as { data?: T })))
      .then((j: { data?: T }) => !off && setData((j?.data ?? null) as T))
      .catch(() => undefined)
    return () => {
      off = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path])
  return seeded !== undefined ? seeded : data
}

/** Slug from the widget prop «course», else from the current /academy/{slug} URL. */
export function academyCourseSlug(props: Record<string, unknown>, pathname?: string | null): string {
  const s = typeof props.course === "string" ? props.course.trim() : ""
  if (s) return s
  const m = (pathname ?? "").match(/\/academy\/([^/?#]+)/)
  return m ? decodeURIComponent(m[1]) : ""
}
export const academyCoursePath = (slug: string) => `/v1/public/academy/${encodeURIComponent(slug)}`

function useCourse(widget: WidgetNode): AcademyCourse | null {
  const slug = academyCourseSlug(widget.props, usePathname())
  return usePrefetched<AcademyCourse>(slug ? academyCoursePath(slug) : null)
}

function Placeholder({ text }: { text: string }) {
  return <div className="rounded-3xl border border-dashed border-black/15 p-6 text-center text-sm opacity-70" dir="rtl">{text}</div>
}
function Stars({ value }: { value: number }) {
  return <span aria-label={`امتیاز ${fa(value)} از ۵`} className="tracking-tight text-amber-500">{"★★★★★".slice(0, Math.round(value))}<span className="opacity-30">{"★★★★★".slice(Math.round(value))}</span></span>
}
function Card({ id, title, children }: { id?: string; title?: string; children: ReactNode }) {
  return (
    <section id={id || undefined} dir="rtl" className="webina-shell my-6 scroll-mt-28">
      <div className="rounded-3xl border border-black/5 bg-white/70 p-5 shadow-sm sm:p-7 dark:border-white/10 dark:bg-white/5">
        {title ? <h2 className="mb-4 text-xl font-extrabold">{title}</h2> : null}
        {children}
      </div>
    </section>
  )
}
function stats(c: AcademyCourse) {
  const lms = c.lms ?? {}
  const lessons = c.lessons ?? []
  const total = lms.total_minutes ?? lessons.reduce((s, l) => s + (l.duration_minutes ?? 0), 0)
  const quizzes = lessons.filter((l) => l.kind === "quiz" || l.kind === "exam")
  return { lms, lessons, total, quizzes, projects: lessons.filter((l) => l.kind === "project").length, videos: lessons.filter((l) => !l.kind || l.kind === "video").length }
}
const missing = (w: WidgetNode) => <Placeholder text={propStr(w.props, "emptyText", "دوره را در تنظیمات ویجت انتخاب کنید (نامک دوره).")} />

/* ---------------- hero ---------------- */
export function AcademyCourseHeroWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const c = useCourse(widget)
  if (!c) return missing(widget)
  const { lms, lessons, total, quizzes } = stats(c)
  const [lead] = lines(c.description ?? "")
  const enroll = propStr(p, "enrollHref", "") || c.enroll_url || "/consultation"
  const ins = lms.instructor
  const title = propStr(p, "title", "") || c.title
  const tagline = propStr(p, "tagline", "") || lms.tagline
  const sub = propStr(p, "subtitle", "") || lead
  const st = [
    lms.students_count ? ["دانشجو", faNum(lms.students_count)] : null,
    lms.rating ? ["امتیاز", `${fa(lms.rating)} از ۵`] : null,
    total ? ["مدت دوره", mins(total)] : c.duration ? ["مدت دوره", c.duration] : null,
    lessons.length ? ["درس", fa(lessons.length)] : null,
    quizzes.length ? ["آزمون", fa(quizzes.length)] : null,
    c.level ? ["سطح", c.level] : null,
  ].filter(Boolean) as [string, string][]
  return (
    <header className="page-hero !pb-10" dir="rtl">
      <div className="webina-shell">
        {propBool(p, "breadcrumb", true) ? (
          <nav className="mb-4 text-sm opacity-70"><Link href="/">خانه</Link> <span aria-hidden>‹</span> <Link href="/academy">آکادمی</Link> <span aria-hidden>‹</span> <span>{c.title}</span></nav>
        ) : null}
        <div className="grid items-center gap-8 lg:grid-cols-[1.25fr_1fr]">
          <div className="min-w-0">
            <p className="webina-kicker">{propStr(p, "kicker", "دوره آنلاین آکادمی وبینا")}</p>
            <h1 className="mt-4">{title}</h1>
            {tagline ? <p className="mt-3 text-lg font-bold" style={{ color: PRIMARY }}>{tagline}</p> : null}
            {sub ? <p className="lead">{sub}</p> : null}
            <div className="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
              {lms.rating ? <span className="flex items-center gap-1"><b>{fa(lms.rating)}</b> <Stars value={lms.rating} /> {lms.reviews_count ? <span className="opacity-70">({faNum(lms.reviews_count)} نظر)</span> : null}</span> : null}
              {lms.students_count ? <span>👥 {faNum(lms.students_count)} دانشجو</span> : null}
              {ins ? <span>مدرس: <a href="#instructor" className="font-bold underline-offset-4 hover:underline">{ins.name}</a></span> : null}
              {lms.updated_label ? <span className="opacity-70">به‌روزرسانی: {lms.updated_label}</span> : null}
            </div>
            <div className="mt-6 flex flex-wrap gap-3">
              <Link href={enroll} className="rounded-full px-6 py-3 font-bold text-white shadow-lg" style={{ background: PRIMARY }}>{propStr(p, "buttonLabel", "") || (c.is_free ? "شروع رایگان دوره" : "ثبت‌نام در دوره")}</Link>
              <a href="#syllabus" className="rounded-full border border-current px-6 py-3 font-bold">{propStr(p, "secondaryLabel", "مشاهده سرفصل‌ها")}</a>
            </div>
          </div>
          {c.cover_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={c.cover_url} alt={c.title} className="aspect-[16/10] w-full rounded-3xl object-cover shadow-2xl" />
          ) : null}
        </div>
        {st.length && propBool(p, "showStats", true) ? (
          <dl className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            {st.map(([k, v]) => (
              <div key={k} className="rounded-2xl bg-white/80 px-4 py-3 text-center shadow-sm dark:bg-white/10"><dd className="text-lg font-extrabold">{v}</dd><dt className="text-xs opacity-60">{k}</dt></div>
            ))}
          </dl>
        ) : null}
      </div>
    </header>
  )
}

/* ---------------- about / outcomes ---------------- */
export function AcademyCourseAboutWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const c = useCourse(widget)
  if (!c) return missing(widget)
  const lms = c.lms ?? {}
  const [, ...rest] = lines(c.description ?? "")
  const extra = lines(propStr(p, "text", ""))
  const outcomes = lines(c.outcomes ?? "")
  return (
    <Card id="about" title={propStr(p, "title", "درباره این دوره")}>
      {[...rest, ...extra].map((x) => <p key={x} className="mb-3 leading-8">{x}</p>)}
      {outcomes.length ? (
        <>
          <h3 className="mb-3 mt-5 text-lg font-bold">{propStr(p, "outcomesTitle", "بعد از این دوره می‌توانید")}</h3>
          <ul className="grid gap-2 sm:grid-cols-2">{outcomes.map((o) => <li key={o} className="flex gap-2"><span className="text-emerald-600">✔</span><span>{o}</span></li>)}</ul>
        </>
      ) : null}
      <div className="mt-6 grid gap-6 sm:grid-cols-2">
        {lms.audience?.length ? <div><h3 className="mb-2 font-bold">{propStr(p, "audienceTitle", "این دوره برای چه کسانی است؟")}</h3><ul className="list-disc space-y-1 ps-5 text-sm leading-7">{lms.audience.map((a) => <li key={a}>{a}</li>)}</ul></div> : null}
        {lms.prerequisites?.length ? <div><h3 className="mb-2 font-bold">{propStr(p, "prereqTitle", "پیش‌نیازها")}</h3><ul className="list-disc space-y-1 ps-5 text-sm leading-7">{lms.prerequisites.map((a) => <li key={a}>{a}</li>)}</ul></div> : null}
      </div>
    </Card>
  )
}

/* ---------------- syllabus ---------------- */
export function AcademyCourseSyllabusWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const c = useCourse(widget)
  if (!c) return missing(widget)
  const { lessons, total } = stats(c)
  if (!lessons.length) return null
  const chapters = c.chapters ?? []
  const groups = chapters.length
    ? [...chapters.map((ch) => ({ title: ch.title, summary: ch.summary, lessons: lessons.filter((l) => l.chapter_id === ch.id) })), { title: "", summary: null, lessons: lessons.filter((l) => !chapters.some((ch) => ch.id === l.chapter_id)) }].filter((g) => g.lessons.length)
    : [{ title: "", summary: null as string | null | undefined, lessons }]
  const openAll = propBool(p, "openAll", false)
  return (
    <Card id="syllabus" title={propStr(p, "title", "سرفصل‌های دوره")}>
      <p className="mb-4 text-sm opacity-70">{fa(groups.length)} فصل، {fa(lessons.length)} درس، {mins(total)}</p>
      <div className="space-y-3">
        {groups.map((g, gi) => {
          const gm = g.lessons.reduce((s, l) => s + (l.duration_minutes ?? 0), 0)
          return (
            <details key={gi} open={openAll || gi === 0} className="group overflow-hidden rounded-2xl border border-black/10 dark:border-white/10">
              <summary className="flex cursor-pointer list-none items-center gap-3 bg-black/[.03] px-4 py-3 dark:bg-white/5">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-extrabold text-white" style={{ background: PRIMARY }}>{fa(gi + 1)}</span>
                <span className="min-w-0 flex-1">
                  <span className="block font-bold">{g.title ? `فصل ${fa(gi + 1)}: ${g.title}` : "درس‌ها"}</span>
                  <span className="block text-xs opacity-60">{fa(g.lessons.length)} درس{gm ? `، ${mins(gm)}` : ""}</span>
                </span>
                <span className="transition group-open:rotate-180" aria-hidden>⌄</span>
              </summary>
              {g.summary ? <p className="px-4 pt-3 text-sm opacity-75">{g.summary}</p> : null}
              <ol className="divide-y divide-black/5 dark:divide-white/10">
                {g.lessons.map((l, i) => {
                  const k = KIND[l.kind ?? "video"] ?? KIND.video
                  return (
                    <li key={l.slug} className="flex items-start gap-3 px-4 py-3">
                      <span className="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-black/5 text-xs dark:bg-white/10" title={k.label}>{k.icon}</span>
                      <div className="min-w-0 flex-1">
                        <p className="font-bold"><span className="me-1 text-xs" style={{ color: PRIMARY }}>{fa(gi + 1)}.{fa(i + 1)}</span> {l.title}</p>
                        {l.summary ? <p className="mt-0.5 text-sm opacity-70">{l.summary}</p> : null}
                        {l.quiz?.questions ? <p className="mt-1 text-xs opacity-70">{fa(l.quiz.questions)} سؤال، حداقل نمره قبولی {fa(l.quiz.pass_score ?? 60)}٪{l.quiz.attempts ? `، ${fa(l.quiz.attempts)} بار فرصت` : ""}</p> : null}
                      </div>
                      <div className="shrink-0 text-end text-xs">
                        {l.is_preview ? <span className="mb-1 block rounded-full bg-emerald-100 px-2 py-0.5 font-bold text-emerald-700">{propStr(p, "previewLabel", "پیش‌نمایش رایگان")}</span> : <span className="mb-1 block opacity-50">🔒 {k.label}</span>}
                        {l.duration_minutes ? <span className="opacity-70">{mins(l.duration_minutes)}</span> : null}
                      </div>
                    </li>
                  )
                })}
              </ol>
            </details>
          )
        })}
      </div>
    </Card>
  )
}

/* ---------------- instructor ---------------- */
export function AcademyCourseInstructorWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const c = useCourse(widget)
  if (!c) return missing(widget)
  const ins = c.lms?.instructor
  if (!ins) return null
  const photo = propStr(p, "photo", "")
  return (
    <Card id="instructor" title={propStr(p, "title", "مدرس دوره")}>
      <div className="flex flex-col gap-5 sm:flex-row">
        {photo ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={photo} alt={ins.name} className="h-24 w-24 shrink-0 rounded-3xl object-cover" />
        ) : (
          <div className="grid h-24 w-24 shrink-0 place-items-center rounded-3xl text-3xl font-extrabold text-white" style={{ background: `linear-gradient(135deg, ${PRIMARY}, #0a2a7a)` }}>{ins.name.trim().charAt(0)}</div>
        )}
        <div className="min-w-0">
          <h3 className="text-lg font-extrabold">{ins.name}</h3>
          {ins.title ? <p className="text-sm" style={{ color: PRIMARY }}>{ins.title}</p> : null}
          <div className="mt-2 flex flex-wrap gap-4 text-sm opacity-80">
            {ins.years ? <span>{fa(ins.years)} سال تجربه</span> : null}
            {ins.students ? <span>{faNum(ins.students)} دانشجو</span> : null}
            {ins.courses ? <span>{fa(ins.courses)} دوره</span> : null}
          </div>
          {ins.bio ? <p className="mt-3 leading-8">{ins.bio}</p> : null}
          {ins.skills?.length ? <div className="mt-3 flex flex-wrap gap-2">{ins.skills.map((s) => <span key={s} className="rounded-full bg-black/5 px-3 py-1 text-xs font-bold dark:bg-white/10">{s}</span>)}</div> : null}
        </div>
      </div>
    </Card>
  )
}

/* ---------------- quizzes / exams / projects ---------------- */
export function AcademyCourseQuizzesWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const c = useCourse(widget)
  if (!c) return missing(widget)
  const { quizzes, lms } = stats(c)
  if (!quizzes.length) return null
  const showSample = propBool(p, "showSample", true)
  return (
    <Card id="exams" title={propStr(p, "title", "آزمون‌ها و ارزیابی")}>
      {propStr(p, "text", "") ? <p className="mb-4 text-sm opacity-75">{propStr(p, "text", "")}</p> : null}
      <div className="grid gap-3 sm:grid-cols-2">
        {quizzes.map((q) => (
          <div key={q.slug} className={`rounded-2xl border p-4 ${q.kind === "exam" ? "border-[#0054ff] bg-[#0054ff]/5" : "border-black/10 dark:border-white/10"}`}>
            <p className="text-xs font-bold opacity-60">{KIND[q.kind ?? "quiz"]?.label}</p>
            <h3 className="font-bold">{q.title}</h3>
            <p className="mt-1 text-sm opacity-75">{fa(q.quiz?.questions ?? 0)} سؤال، {mins(q.quiz?.minutes ?? q.duration_minutes ?? 0)}، قبولی {fa(q.quiz?.pass_score ?? 60)}٪</p>
            {showSample && q.quiz?.sample?.[0] ? (
              <details className="mt-2 text-sm">
                <summary className="cursor-pointer font-bold" style={{ color: PRIMARY }}>نمونه سؤال</summary>
                <p className="mt-2">{q.quiz.sample[0].q}</p>
                <ol className="mt-1 list-[persian] space-y-1 ps-5">{q.quiz.sample[0].options.map((o, oi) => <li key={o} className={oi === q.quiz!.sample![0].answer ? "font-bold text-emerald-700" : ""}>{o}</li>)}</ol>
              </details>
            ) : null}
          </div>
        ))}
      </div>
      {lms.projects?.length ? (
        <>
          <h3 className="mb-2 mt-6 font-bold">{propStr(p, "projectsTitle", "پروژه‌های عملی")}</h3>
          <ul className="space-y-2">{lms.projects.map((x) => <li key={x} className="flex gap-2"><span>🛠</span><span>{x}</span></li>)}</ul>
        </>
      ) : null}
      {lms.materials?.length ? (
        <>
          <h3 className="mb-2 mt-6 font-bold">{propStr(p, "materialsTitle", "منابع و فایل‌های دوره")}</h3>
          <ul className="grid gap-3 sm:grid-cols-2">
            {lms.materials.map((m) => (
              <li key={m.title} className="flex items-center gap-3 rounded-2xl border border-black/10 p-3 dark:border-white/10">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-black/5 text-[10px] font-extrabold dark:bg-white/10" dir="ltr">{m.type}</span>
                <span className="min-w-0 flex-1"><span className="block font-bold">{m.title}</span>{m.size ? <span className="text-xs opacity-60">{m.size}</span> : null}</span>
                <span className="text-xs opacity-50">🔒</span>
              </li>
            ))}
          </ul>
        </>
      ) : null}
    </Card>
  )
}

/* ---------------- reviews + faq (FAQPage schema) ---------------- */
export function AcademyCourseReviewsWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const c = useCourse(widget)
  if (!c) return missing(widget)
  const lms = c.lms ?? {}
  const faq = lms.faq ?? []
  return (
    <>
      {lms.reviews?.length ? (
        <Card id="reviews" title={propStr(p, "title", "نظر دانشجویان")}>
          <div className="grid gap-3 md:grid-cols-3">
            {lms.reviews.map((r) => (
              <figure key={r.name + r.text} className="rounded-2xl bg-black/[.03] p-4 dark:bg-white/5">
                {r.rating ? <Stars value={r.rating} /> : null}
                <blockquote className="mt-2 text-sm leading-7">«{r.text}»</blockquote>
                <figcaption className="mt-3 text-xs"><b>{r.name}</b>{r.role ? <span className="opacity-60">، {r.role}</span> : null}</figcaption>
              </figure>
            ))}
          </div>
        </Card>
      ) : null}
      {faq.length && propBool(p, "showFaq", true) ? (
        <Card id="faq" title={propStr(p, "faqTitle", "پرسش‌های پرتکرار")}>
          <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({ "@context": "https://schema.org", "@type": "FAQPage", mainEntity: faq.map((f) => ({ "@type": "Question", name: f.q, acceptedAnswer: { "@type": "Answer", text: f.a } })) }) }} />
          <div className="space-y-2">{faq.map((f) => (
            <details key={f.q} className="rounded-2xl border border-black/10 px-4 py-3 dark:border-white/10">
              <summary className="cursor-pointer font-bold">{f.q}</summary>
              <p className="mt-2 text-sm leading-7 opacity-80">{f.a}</p>
            </details>
          ))}</div>
        </Card>
      ) : null}
    </>
  )
}

/** Add-to-cart for the course's Shop product (same cart as sellable products/plugins). */
function CourseCartButton({ slug, name, price, image, label, cartHref, light }: { slug: string; name: string; price: number; image?: string | null; label: string; cartHref: string; light?: boolean }) {
  const inCart = useSyncExternalStore(subscribeCart, () => cartSnapshot().some((l) => l.slug === slug), () => false)
  return (
    <div className="mt-3 flex flex-col gap-1">
      <button type="button" onClick={() => addToCart({ slug, name, price, tone: "#0054ff", image: image || null })}
        className={`rounded-full px-6 py-3 text-center font-bold ${light ? "bg-white" : "text-white"}`} style={light ? { color: PRIMARY } : { background: PRIMARY }}>
        {inCart ? "در سبد خرید است" : label}
      </button>
      {inCart ? <Link href={cartHref} className="text-center text-sm font-bold underline-offset-4 hover:underline">مشاهده سبد خرید و پرداخت</Link> : null}
    </div>
  )
}
const toman = (n: number) => `${faNum(n)} تومان`

/** Shop catalog price (toman) for a product slug; 0 while loading or when not sold. */
function useShopPrice(slug: string) {
  const [price, setPrice] = useState(0)
  useEffect(() => {
    if (!slug) return
    let alive = true
    void fetch(`/api/v1/public/catalog/items/${encodeURIComponent(slug)}`, { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } })
      .then((r) => (r.ok ? r.json() : null))
      .then((j) => { const d = j?.data ?? j; if (alive && d?.is_available !== false) setPrice(Math.round(Number(d?.discounted_price_minor ?? d?.price_minor ?? 0) / 10)) })
      .catch(() => undefined)
    return () => { alive = false }
  }, [slug])
  return price
}

/* ---------------- enrollment CTA (box or banner) + Course schema ---------------- */
export function AcademyCourseEnrollWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const c = useCourse(widget)
  const shopSlug = propStr(p, "cartSlug", "") || (c?.slug ? `course-${c.slug}` : "")
  const shopPrice = useShopPrice(c && !c.is_free ? shopSlug : "")
  if (!c) return missing(widget)
  const { lms, total, quizzes, projects, videos } = stats(c)
  const enroll = propStr(p, "href", "") || c.enroll_url || "/consultation"
  const label = propStr(p, "buttonLabel", "") || (c.is_free ? "شروع رایگان دوره" : "ثبت‌نام در دوره")
  const includes = [
    total ? `${mins(total)} محتوای آموزشی` : null,
    videos ? `${fa(videos)} ویدیوی آموزشی` : null,
    quizzes.length ? `${fa(quizzes.length)} آزمونک و آزمون جامع` : null,
    projects ? `${fa(projects)} پروژه عملی` : null,
    lms.materials?.length ? `${fa(lms.materials.length)} فایل و قالب قابل دانلود` : null,
    lms.access, lms.certificate,
  ].filter(Boolean) as string[]
  const ins = lms.instructor
  const outcomes = lines(c.outcomes ?? "")
  // Price is managed in the Shop catalog (product «course-{slug}»); a builder price prop still overrides for display.
  const price = Number(String(p.price ?? "").replace(/[^0-9]/g, "")) || shopPrice || 0
  const cart = price > 0 && propBool(p, "cart", true)
    ? <CourseCartButton slug={propStr(p, "cartSlug", "") || `course-${c.slug ?? ""}`} name={c.title} price={price} image={c.cover_url} label={propStr(p, "cartLabel", "افزودن دوره به سبد خرید")} cartHref={propStr(p, "cartHref", "/pages/cart")} light={propStr(p, "variant", "box") === "banner"} />
    : null
  const ld = {
    "@context": "https://schema.org", "@type": "Course", name: c.title, description: (c.description ?? c.title).slice(0, 500), inLanguage: "fa-IR",
    provider: { "@type": "Organization", name: "وبینا", sameAs: "https://webina.dev" },
    ...(c.slug ? { url: `https://webina.dev/academy/${c.slug}` } : {}),
    ...(c.cover_url ? { image: c.cover_url.startsWith("http") ? c.cover_url : `https://webina.dev${c.cover_url}` } : {}),
    ...(c.level ? { educationalLevel: c.level } : {}), ...(c.is_free ? { isAccessibleForFree: true } : {}),
    ...(lms.rating && lms.reviews_count ? { aggregateRating: { "@type": "AggregateRating", ratingValue: lms.rating, reviewCount: lms.reviews_count, bestRating: 5 } } : {}),
    ...(outcomes.length ? { teaches: outcomes } : {}),
    ...(price > 0 ? { offers: { "@type": "Offer", price: price * 10, priceCurrency: "IRR", category: "Paid", availability: "https://schema.org/InStock" } } : {}),
    hasCourseInstance: { "@type": "CourseInstance", courseMode: "online", ...(ins ? { instructor: { "@type": "Person", name: ins.name, description: ins.title } } : {}), ...(total ? { courseWorkload: `PT${Math.floor(total / 60)}H${total % 60}M` } : {}) },
  }
  const schema = propBool(p, "schema", true) ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} /> : null
  if (propStr(p, "variant", "box") === "banner") {
    return (
      <section className="webina-shell my-10" dir="rtl">
        {schema}
        <div className="rounded-3xl p-6 text-center text-white sm:p-10" style={{ background: PRIMARY }}>
          <h2 className="text-2xl font-extrabold">{propStr(p, "title", "آماده شروع هستید؟")}</h2>
          <p className="mx-auto mt-2 max-w-xl opacity-90">{propStr(p, "text", "") || lms.tagline || c.title}</p>
          {cart ? <div className="mx-auto max-w-xs">{cart}</div> : <Link href={enroll} className="mt-5 inline-block rounded-full bg-white px-7 py-3 font-bold" style={{ color: PRIMARY }}>{label}</Link>}
        </div>
      </section>
    )
  }
  return (
    <section className="webina-shell my-6" dir="rtl">
      {schema}
      <div className="grid gap-6 rounded-3xl border border-black/5 bg-white p-5 shadow-xl sm:p-7 md:grid-cols-[1fr_1.2fr] dark:border-white/10 dark:bg-white/5">
        <div>
          <p className="text-2xl font-extrabold" style={{ color: PRIMARY }}>{propStr(p, "priceLabel", "") || (price ? toman(price) : c.is_free ? "رایگان" : "ثبت‌نام آنلاین")}</p>
          {cart}
          {propStr(p, "text", "") ? <p className="mt-2 text-sm leading-7 opacity-80">{propStr(p, "text", "")}</p> : null}
          <Link href={enroll} className="mt-4 block rounded-full px-6 py-3 text-center font-bold text-white" style={{ background: PRIMARY }}>{label}</Link>
          <a href="#syllabus" className="mt-2 block rounded-full border border-black/15 px-6 py-2.5 text-center text-sm font-bold dark:border-white/20">{propStr(p, "secondaryLabel", "درس‌های پیش‌نمایش رایگان")}</a>
          {lms.language ? <p className="mt-4 text-xs opacity-60">زبان: {lms.language}</p> : null}
        </div>
        {includes.length ? (
          <div>
            <h3 className="mb-2 text-sm font-extrabold">{propStr(p, "includesTitle", "این دوره شامل")}</h3>
            <ul className="space-y-2 text-sm">{includes.map((x) => <li key={x} className="flex gap-2"><span className="text-emerald-600">✔</span><span>{x}</span></li>)}</ul>
          </div>
        ) : null}
      </div>
    </section>
  )
}

/* ---------------- related courses ---------------- */
export function AcademyRelatedCoursesWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const current = academyCourseSlug(p, usePathname())
  const all = usePrefetched<AcademyCourse[]>("/v1/public/academy")
  const limit = Math.max(1, Math.min(12, Number(p.limit) || 3))
  const items = (Array.isArray(all) ? all : []).filter((c) => c.slug !== current).slice(0, limit)
  if (!items.length) return null
  return (
    <section className="webina-shell my-10" dir="rtl">
      <h2 className="mb-5 text-2xl font-extrabold">{propStr(p, "title", "دوره‌های مرتبط")}</h2>
      <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((c) => (
          <li key={c.slug} className="overflow-hidden rounded-3xl border border-black/5 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-white/5">
            <Link href={`/academy/${c.slug}`} className="block">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              {c.cover_url ? <img src={c.cover_url} alt={c.title} loading="lazy" className="aspect-[16/10] w-full object-cover" /> : null}
              <div className="p-4">
                <div className="mb-2 flex flex-wrap gap-2 text-xs">{c.level ? <span className="rounded-full bg-[#0054ff]/10 px-2 py-0.5 font-bold" style={{ color: PRIMARY }}>{c.level}</span> : null}{c.duration ? <span className="opacity-60">{c.duration}</span> : null}</div>
                <h3 className="font-extrabold leading-7">{c.title}</h3>
                <span className="mt-2 inline-block text-sm font-bold" style={{ color: PRIMARY }}>{propStr(p, "detailLabel", "مشاهده دوره")}</span>
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  )
}
