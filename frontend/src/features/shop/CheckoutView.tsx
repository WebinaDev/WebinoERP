"use client"

import Link from "next/link"
import { useRouter } from "next/navigation"
import { useEffect, useState } from "react"
import { CreditCard, Loader2, LogIn, ShieldCheck, ShoppingBag, WalletCards } from "lucide-react"

import { CartSummary } from "./CartView"
import { refreshCart, shopApi, tomanFromMinor, useShopCart, useShopUser } from "./shop-api"

type Gateway = { code: string; label_fa: string; modes: string[]; fee_percent?: number; sandbox?: boolean }
type Settings = { require_login: boolean; require_email: boolean; require_address: boolean; checkout_note: string }
type Order = { id: number; number: string; status: string; total_minor: number }

const field = "h-12 w-full rounded-2xl border border-[#e2e6f0] bg-white px-4 text-sm outline-none transition focus:border-[#0054ff] focus:ring-4 focus:ring-[#0054ff]/10"
const toLatin = (v: string) => v.replace(/[۰-۹]/g, (d) => String("۰۱۲۳۴۵۶۷۸۹".indexOf(d)))

export function CheckoutView() {
  const router = useRouter()
  const { cart, loading } = useShopCart()
  const { user } = useShopUser()
  const [settings, setSettings] = useState<Settings | null>(null)
  const [gateways, setGateways] = useState<Gateway[]>([])
  const [modes, setModes] = useState<string[]>(["cash"])
  const [mode, setMode] = useState<"cash" | "installment">("cash")
  const [gateway, setGateway] = useState("")
  const [form, setForm] = useState({ name: "", phone: "", email: "", address: "", city: "", postcode: "", note: "" })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState("")
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    void shopApi<Settings>("/api/v1/storefront/settings").then((r) => r.ok && setSettings(r.data))
    void shopApi<{ gateways: Gateway[]; modes: string[] }>("/api/v1/payments/checkout-options").then((r) => {
      if (!r.ok) return
      setGateways(r.data.gateways ?? [])
      setModes(r.data.modes?.length ? r.data.modes : ["cash"])
    })
  }, [])
  useEffect(() => { if (user) setForm((f) => ({ ...f, name: f.name || user.name || "", email: f.email || user.email || "" })) }, [user])
  useEffect(() => { if (!modes.includes(mode)) setMode((modes[0] as "cash" | "installment") ?? "cash") }, [modes, mode])
  const available = gateways.filter((g) => g.modes.includes(mode))
  useEffect(() => { if (!available.some((g) => g.code === gateway)) setGateway(available[0]?.code ?? "") }, [available, gateway])

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setForm({ ...form, [k]: e.target.value })
  const total = cart?.pricing?.total_minor ?? 0
  const needsAddress = !!cart?.pricing?.requires_shipping

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError(""); setErrors({})
    if (total > 0 && !gateway) { setError("در حال حاضر درگاه فعالی برای این روش پرداخت وجود ندارد."); return }
    setBusy(true)
    const order = await shopApi<Order>("/api/v1/checkout", {
      method: "POST",
      json: {
        customer_name: form.name, customer_phone: toLatin(form.phone), customer_email: form.email || null,
        customer_note: form.note || null, payment_mode: mode,
        shipping_address: needsAddress ? { line: form.address, city: form.city } : null,
        shipping_postcode: needsAddress ? toLatin(form.postcode) || null : null,
      },
    })
    if (!order.ok) {
      setBusy(false)
      if (order.status === 401) { router.push(`/account/login?next=${encodeURIComponent("/checkout")}`); return }
      setError(order.message)
      setErrors(Object.fromEntries(Object.entries(order.errors ?? {}).map(([k, v]) => [k, v[0]])))
      return
    }
    const result = `/checkout/result?order=${order.data.id}&number=${encodeURIComponent(order.data.number)}`
    if (order.data.status !== "pending_payment") { await refreshCart(); router.push(result); return }
    const intent = await shopApi<{ redirect_url?: string | null }>("/api/v1/payments/intent", { method: "POST", json: { order_id: order.data.id, provider: gateway, mode, number: order.data.number } })
    await refreshCart()
    if (intent.ok && intent.data.redirect_url?.startsWith("http")) { window.location.assign(intent.data.redirect_url); return }
    setBusy(false)
    router.push(`${result}&payment=failed&reason=${encodeURIComponent(intent.ok ? "درگاه پاسخ نداد." : intent.message)}`)
  }

  if (loading) return <div className="mx-auto max-w-6xl px-4 py-10"><div className="h-60 animate-pulse rounded-3xl bg-[#f3f5f9]" /></div>
  if (!cart?.items.length) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center" dir="rtl">
        <ShoppingBag className="mx-auto size-10 text-[#9aa0ad]" />
        <h1 className="mt-3 text-xl font-black">سبد خرید شما خالی است</h1>
        <Link href="/products" className="mt-4 inline-flex rounded-2xl bg-[#0054ff] px-5 py-3 text-sm font-bold text-white">مشاهده محصولات</Link>
      </div>
    )
  }
  if (settings?.require_login && !user) {
    return (
      <div className="mx-auto max-w-lg px-4 py-16 text-center" dir="rtl">
        <LogIn className="mx-auto size-10 text-[#0054ff]" />
        <h1 className="mt-3 text-xl font-black">برای تکمیل خرید وارد شوید</h1>
        <Link href={`/account/login?next=${encodeURIComponent("/checkout")}`} className="mt-4 inline-flex rounded-2xl bg-[#0054ff] px-5 py-3 text-sm font-bold text-white">ورود یا ثبت‌نام</Link>
      </div>
    )
  }

  const err = (k: string) => (errors[k] ? <span className="text-xs text-[#ff4d4f]">{errors[k]}</span> : null)
  return (
    <div className="mx-auto max-w-6xl px-4 py-10" dir="rtl">
      <h1 className="text-2xl font-black text-[#22242a]">تسویه حساب</h1>
      {!user ? (
        <p className="mt-3 rounded-2xl bg-[#eef4ff] px-4 py-3 text-sm text-[#22242a]">حساب کاربری دارید؟ <Link href={`/account/login?next=${encodeURIComponent("/checkout")}`} className="font-bold text-[#0054ff]">وارد شوید</Link> تا سفارش‌ها و دوره‌های خریداری‌شده در پنل شما ثبت شود.</p>
      ) : null}
      <form onSubmit={submit} className="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div className="grid gap-6">
          <section className="rounded-3xl border border-[#e6e9f2] bg-white p-5">
            <h2 className="font-black">اطلاعات خریدار</h2>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <label className="grid gap-1 text-sm"><span>نام و نام خانوادگی *</span><input required className={field} value={form.name} onChange={set("name")} autoComplete="name" />{err("customer_name")}</label>
              <label className="grid gap-1 text-sm"><span>شماره موبایل *</span><input required className={field} dir="ltr" inputMode="tel" placeholder="09xxxxxxxxx" value={form.phone} onChange={set("phone")} autoComplete="tel" />{err("customer_phone")}</label>
              <label className="grid gap-1 text-sm md:col-span-2"><span>ایمیل{settings?.require_email ? " *" : " (اختیاری)"}</span><input type="email" required={!!settings?.require_email} className={field} dir="ltr" value={form.email} onChange={set("email")} autoComplete="email" />{err("customer_email")}</label>
              {needsAddress ? (<>
                <label className="grid gap-1 text-sm"><span>شهر</span><input className={field} value={form.city} onChange={set("city")} /></label>
                <label className="grid gap-1 text-sm"><span>کد پستی</span><input className={field} dir="ltr" value={form.postcode} onChange={set("postcode")} /></label>
                <label className="grid gap-1 text-sm md:col-span-2"><span>نشانی کامل{settings?.require_address ? " *" : ""}</span><textarea required={!!settings?.require_address} className={`${field} h-24 py-3`} value={form.address} onChange={set("address")} />{err("shipping_address")}</label>
              </>) : null}
              <label className="grid gap-1 text-sm md:col-span-2"><span>توضیحات سفارش (اختیاری)</span><textarea className={`${field} h-20 py-3`} value={form.note} onChange={set("note")} /></label>
            </div>
          </section>

          {total > 0 ? (
            <section className="rounded-3xl border border-[#e6e9f2] bg-white p-5">
              <h2 className="font-black">روش پرداخت</h2>
              {modes.length > 1 ? (
                <div className="mt-4 grid grid-cols-2 gap-2 rounded-2xl bg-[#f3f5f9] p-1">
                  {modes.map((m) => (
                    <button key={m} type="button" onClick={() => setMode(m as "cash" | "installment")} className={`flex h-11 items-center justify-center gap-2 rounded-xl text-sm font-bold transition ${mode === m ? "bg-white text-[#0054ff] shadow" : "text-[#6b7080]"}`}>
                      {m === "cash" ? <CreditCard className="size-4" /> : <WalletCards className="size-4" />}{m === "cash" ? "پرداخت نقدی" : "پرداخت اقساطی"}
                    </button>
                  ))}
                </div>
              ) : null}
              <div className="mt-4 grid gap-2">
                {available.length ? available.map((g) => (
                  <label key={g.code} className={`flex cursor-pointer items-center justify-between rounded-2xl border p-4 transition ${gateway === g.code ? "border-[#0054ff] bg-[#f5f8ff]" : "border-[#e6e9f2]"}`}>
                    <span className="flex items-center gap-3"><input type="radio" name="gw" checked={gateway === g.code} onChange={() => setGateway(g.code)} className="accent-[#0054ff]" /><b className="text-sm">{g.label_fa}</b>{g.sandbox ? <span className="rounded-full bg-amber-100 px-2 text-[11px] text-amber-700">آزمایشی</span> : null}</span>
                    {g.fee_percent ? <span className="text-xs text-[#6b7080]">کارمزد {new Intl.NumberFormat("fa-IR").format(g.fee_percent)}٪</span> : null}
                  </label>
                )) : <p className="rounded-2xl bg-[#fff7e6] p-4 text-sm text-[#8a5a00]">برای {mode === "cash" ? "پرداخت نقدی" : "پرداخت اقساطی"} درگاه فعالی تنظیم نشده است.</p>}
              </div>
            </section>
          ) : null}
        </div>

        <CartSummary compact cta={<>
          {settings?.checkout_note ? <p className="mt-3 text-xs leading-6 text-[#6b7080]">{settings.checkout_note}</p> : null}
          {error ? <p role="alert" className="mt-3 rounded-xl bg-[#fff1f1] p-3 text-sm text-[#d4380d]">{error}</p> : null}
          <button disabled={busy} className="mt-4 flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[#0054ff] font-bold text-white hover:bg-[#0045d1] disabled:opacity-60">
            {busy ? <Loader2 className="size-5 animate-spin" /> : <ShieldCheck className="size-5" />}
            {total > 0 ? `پرداخت ${tomanFromMinor(total)}` : "ثبت سفارش"}
          </button>
          <Link href="/cart" className="mt-2 block text-center text-sm text-[#6b7080] hover:text-[#0054ff]">بازگشت به سبد خرید</Link>
        </>} />
      </form>
    </div>
  )
}
