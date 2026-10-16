"use client"

import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { useEffect, useState } from "react"
import { BookOpen, LayoutGrid, Loader2, LogOut, Package, PlayCircle, User } from "lucide-react"

import { OrderBadge } from "./OrderStatus"
import { shopApi, shopLogout, tomanFromMinor, useShopUser } from "./shop-api"

type Order = { id: number; number: string; status: string; total_minor: number; payment_mode?: string; created_at?: string; items: { name: string; quantity: number; total: number; kind?: string; slug?: string }[] }
type Course = { id: number; slug: string; title: string; cover_url?: string | null; level?: string | null; duration?: string | null; instructor?: string | null; url: string; lessons_count: number; purchased_at?: string }
type Profile = { name: string; email: string; orders_count: number; paid_total_minor: number }

const TABS = [
  { id: "overview", label: "پیشخوان", icon: LayoutGrid },
  { id: "courses", label: "دوره‌های من", icon: BookOpen },
  { id: "orders", label: "سفارشات من", icon: Package },
  { id: "profile", label: "اطلاعات حساب", icon: User },
] as const
type Tab = (typeof TABS)[number]["id"]
const faDate = (iso?: string) => (iso ? new Intl.DateTimeFormat("fa-IR", { dateStyle: "medium" }).format(new Date(iso)) : "")
const fa = (n: number) => new Intl.NumberFormat("fa-IR").format(n)

export function AccountView() {
  const router = useRouter()
  const q = useSearchParams()
  const { user, loading } = useShopUser()
  const tab = (TABS.some((t) => t.id === q.get("tab")) ? q.get("tab") : "overview") as Tab
  const [orders, setOrders] = useState<Order[] | null>(null)
  const [courses, setCourses] = useState<Course[] | null>(null)
  const [profile, setProfile] = useState<Profile | null>(null)

  useEffect(() => { if (!loading && !user) router.replace(`/account/login?next=${encodeURIComponent(`/account${tab !== "overview" ? `?tab=${tab}` : ""}`)}`) }, [loading, user, router, tab])
  useEffect(() => {
    if (!user) return
    void shopApi<Order[]>("/api/v1/account/orders").then((r) => setOrders(r.ok ? r.data : []))
    void shopApi<Course[]>("/api/v1/account/courses").then((r) => setCourses(r.ok ? r.data : []))
    void shopApi<Profile>("/api/v1/account/profile").then((r) => r.ok && setProfile(r.data))
  }, [user])

  if (loading || !user) return <div className="mx-auto max-w-6xl px-4 py-10"><div className="h-64 animate-pulse rounded-3xl bg-[#f3f5f9]" /></div>
  const go = (t: Tab) => router.replace(t === "overview" ? "/account" : `/account?tab=${t}`, { scroll: false })

  return (
    <div className="mx-auto grid max-w-6xl gap-6 px-4 py-10 lg:grid-cols-[240px_1fr]" dir="rtl">
      <aside className="h-fit rounded-3xl border border-[#e6e9f2] bg-white p-3 lg:sticky lg:top-24">
        <div className="flex items-center gap-3 p-2">
          <span className="flex size-11 items-center justify-center rounded-full bg-[#0054ff] text-lg font-black text-white">{(user.name || "?").charAt(0)}</span>
          <div className="min-w-0"><p className="truncate font-bold">{user.name}</p><p className="truncate text-xs text-[#6b7080]" dir="ltr">{user.email}</p></div>
        </div>
        <nav className="mt-2 flex gap-1 overflow-x-auto lg:grid">
          {TABS.map((t) => (
            <button key={t.id} type="button" onClick={() => go(t.id)} className={`flex shrink-0 items-center gap-2 rounded-2xl px-3 py-2.5 text-sm font-bold transition ${tab === t.id ? "bg-[#0054ff] text-white" : "text-[#4a4e5a] hover:bg-[#f3f6fc]"}`}>
              <t.icon className="size-4" />{t.label}
            </button>
          ))}
          <button type="button" onClick={() => void shopLogout().then(() => router.push("/"))} className="flex shrink-0 items-center gap-2 rounded-2xl px-3 py-2.5 text-sm font-bold text-[#ff4d4f] hover:bg-[#fff1f1]"><LogOut className="size-4" />خروج</button>
        </nav>
      </aside>

      <main className="min-w-0">
        {tab === "overview" ? (
          <div className="grid gap-4">
            <h1 className="text-2xl font-black">سلام {user.name} 👋</h1>
            <div className="grid gap-3 sm:grid-cols-3">
              <Stat label="دوره‌های من" value={courses ? fa(courses.length) : "…"} onClick={() => go("courses")} />
              <Stat label="سفارش‌ها" value={profile ? fa(profile.orders_count) : "…"} onClick={() => go("orders")} />
              <Stat label="مجموع خرید" value={profile ? tomanFromMinor(profile.paid_total_minor) : "…"} />
            </div>
            <Section title="آخرین سفارش‌ها"><OrdersList orders={orders?.slice(0, 3) ?? null} /></Section>
          </div>
        ) : null}
        {tab === "courses" ? <Section title="دوره‌های من"><CoursesGrid courses={courses} /></Section> : null}
        {tab === "orders" ? <Section title="سفارشات من"><OrdersList orders={orders} /></Section> : null}
        {tab === "profile" ? <Section title="اطلاعات حساب"><ProfileForm initialName={user.name} email={user.email} /></Section> : null}
      </main>
    </div>
  )
}

function Stat({ label, value, onClick }: { label: string; value: string; onClick?: () => void }) {
  return (
    <button type="button" onClick={onClick} className="rounded-3xl border border-[#e6e9f2] bg-white p-5 text-start transition hover:border-[#0054ff]">
      <p className="text-sm text-[#6b7080]">{label}</p><p className="mt-2 text-xl font-black">{value}</p>
    </button>
  )
}
function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return <section className="rounded-3xl border border-[#e6e9f2] bg-white p-5"><h2 className="mb-4 text-lg font-black">{title}</h2>{children}</section>
}

function CoursesGrid({ courses }: { courses: Course[] | null }) {
  if (!courses) return <div className="h-32 animate-pulse rounded-2xl bg-[#f3f5f9]" />
  if (!courses.length) return <Empty text="هنوز دوره‌ای خریداری نکرده‌اید." href="/academy" cta="مشاهده دوره‌ها" />
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {courses.map((c) => (
        <article key={c.id} className="overflow-hidden rounded-3xl border border-[#e6e9f2]">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          {c.cover_url ? <img src={c.cover_url} alt="" className="aspect-video w-full object-cover" /> : <div className="aspect-video bg-[#eef4ff]" />}
          <div className="p-4">
            <h3 className="font-bold">{c.title}</h3>
            <p className="mt-1 text-xs text-[#6b7080]">{[c.instructor, c.duration, `${fa(c.lessons_count)} درس`].filter(Boolean).join(" · ")}</p>
            <Link href={c.url} className="mt-3 inline-flex items-center gap-1 rounded-2xl bg-[#0054ff] px-4 py-2 text-sm font-bold text-white"><PlayCircle className="size-4" />ادامه یادگیری</Link>
          </div>
        </article>
      ))}
    </div>
  )
}

function OrdersList({ orders }: { orders: Order[] | null }) {
  const [paying, setPaying] = useState<number | null>(null)
  const [err, setErr] = useState("")
  if (!orders) return <div className="h-24 animate-pulse rounded-2xl bg-[#f3f5f9]" />
  if (!orders.length) return <Empty text="هنوز سفارشی ثبت نکرده‌اید." href="/products" cta="مشاهده محصولات" />
  async function pay(o: Order) {
    setPaying(o.id); setErr("")
    const mode = o.payment_mode === "installment" ? "installment" : "cash"
    const opts = await shopApi<{ gateways: { code: string }[] }>(`/api/v1/payments/checkout-options?mode=${mode}`)
    const provider = opts.ok ? opts.data.gateways[0]?.code : undefined
    const r = await shopApi<{ redirect_url?: string }>("/api/v1/payments/intent", { method: "POST", json: { order_id: o.id, provider, mode } })
    if (r.ok && r.data.redirect_url?.startsWith("http")) { window.location.assign(r.data.redirect_url); return }
    setPaying(null); setErr(r.ok ? "درگاه پاسخ نداد." : r.message)
  }
  return (
    <div className="grid gap-3">
      {err ? <p className="rounded-xl bg-[#fff1f1] p-3 text-sm text-[#d4380d]">{err}</p> : null}
      {orders.map((o) => (
        <div key={o.id} className="rounded-2xl border border-[#eef1f6] p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-sm"><b dir="ltr">{o.number}</b> <span className="text-[#6b7080]">· {faDate(o.created_at)}</span></p>
            <OrderBadge status={o.status} />
          </div>
          <p className="mt-2 text-sm text-[#4a4e5a]">{o.items.map((i) => i.name).join("، ")}</p>
          <div className="mt-3 flex items-center justify-between">
            <b>{tomanFromMinor(o.total_minor)}</b>
            {o.status === "pending_payment" ? (
              <button type="button" disabled={paying === o.id} onClick={() => void pay(o)} className="inline-flex items-center gap-1 rounded-xl bg-[#0054ff] px-4 py-2 text-sm font-bold text-white disabled:opacity-60">{paying === o.id ? <Loader2 className="size-4 animate-spin" /> : null}پرداخت</button>
            ) : null}
          </div>
        </div>
      ))}
    </div>
  )
}

function ProfileForm({ initialName, email }: { initialName: string; email: string }) {
  const [name, setName] = useState(initialName)
  const [pw, setPw] = useState({ current_password: "", password: "", password_confirmation: "" })
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)
  const [busy, setBusy] = useState(false)
  const f = "h-12 w-full rounded-2xl border border-[#e2e6f0] px-4 text-sm outline-none focus:border-[#0054ff]"
  return (
    <form className="grid max-w-lg gap-4" onSubmit={async (e) => {
      e.preventDefault(); setBusy(true)
      const r = await shopApi("/api/v1/account/profile", { method: "PUT", json: { name, ...(pw.password ? pw : {}) } })
      setBusy(false); setMsg(r.ok ? { ok: true, text: "اطلاعات ذخیره شد." } : { ok: false, text: r.message })
      if (r.ok) setPw({ current_password: "", password: "", password_confirmation: "" })
    }}>
      <label className="grid gap-1 text-sm"><span>نام و نام خانوادگی</span><input required className={f} value={name} onChange={(e) => setName(e.target.value)} /></label>
      <label className="grid gap-1 text-sm"><span>ایمیل</span><input disabled className={`${f} bg-[#f7f8fb]`} dir="ltr" value={email} /></label>
      <p className="mt-2 text-sm font-bold">تغییر رمز عبور (اختیاری)</p>
      <input type="password" placeholder="رمز فعلی" className={f} value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} autoComplete="current-password" />
      <input type="password" placeholder="رمز جدید (حداقل ۸ کاراکتر)" className={f} value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} autoComplete="new-password" />
      <input type="password" placeholder="تکرار رمز جدید" className={f} value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} autoComplete="new-password" />
      {msg ? <p className={`text-sm ${msg.ok ? "text-emerald-600" : "text-[#d4380d]"}`}>{msg.text}</p> : null}
      <button disabled={busy} className="h-12 rounded-2xl bg-[#0054ff] font-bold text-white disabled:opacity-60">ذخیره</button>
    </form>
  )
}

function Empty({ text, href, cta }: { text: string; href: string; cta: string }) {
  return <div className="rounded-2xl border border-dashed border-[#d8deeb] p-8 text-center text-sm text-[#6b7080]">{text}<div><Link href={href} className="mt-3 inline-flex rounded-2xl bg-[#0054ff] px-4 py-2 font-bold text-white">{cta}</Link></div></div>
}
