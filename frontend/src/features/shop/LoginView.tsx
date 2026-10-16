"use client"

import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { useEffect, useState } from "react"
import { Loader2 } from "lucide-react"

import { isStaff, shopLogin, shopRegister, useShopUser } from "./shop-api"

const f = "h-12 w-full rounded-2xl border border-[#e2e6f0] bg-white px-4 text-sm outline-none transition focus:border-[#0054ff] focus:ring-4 focus:ring-[#0054ff]/10"

export function LoginView() {
  const router = useRouter()
  const q = useSearchParams()
  const { user } = useShopUser()
  const rawNext = q.get("next") || "/account"
  const next = rawNext.startsWith("/") && !rawNext.startsWith("//") ? rawNext : "/account"
  const [tab, setTab] = useState<"login" | "register">(q.get("tab") === "register" ? "register" : "login")
  const [form, setForm] = useState({ name: "", email: "", password: "", password_confirmation: "" })
  const [error, setError] = useState("")
  const [busy, setBusy] = useState(false)
  useEffect(() => { if (user) router.replace(next) }, [user, next, router])

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true); setError("")
    const r = tab === "login" ? await shopLogin(form.email.trim(), form.password) : await shopRegister({ ...form, email: form.email.trim() })
    setBusy(false)
    if (!r.ok) { setError(r.status === 422 && tab === "login" ? "ایمیل یا رمز عبور درست نیست." : r.message); return }
  }
  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, [k]: e.target.value })
  return (
    <div className="mx-auto max-w-md px-4 py-14" dir="rtl">
      <div className="rounded-3xl border border-[#e6e9f2] bg-white p-6 shadow-[0_24px_60px_-30px_rgba(0,30,90,.25)]">
        <div className="grid grid-cols-2 gap-1 rounded-2xl bg-[#f3f5f9] p-1">
          {(["login", "register"] as const).map((t) => (
            <button key={t} type="button" onClick={() => { setTab(t); setError("") }} className={`h-11 rounded-xl text-sm font-bold ${tab === t ? "bg-white text-[#0054ff] shadow" : "text-[#6b7080]"}`}>{t === "login" ? "ورود" : "ثبت‌نام"}</button>
          ))}
        </div>
        <form onSubmit={submit} className="mt-6 grid gap-3">
          {tab === "register" ? <input required placeholder="نام و نام خانوادگی" className={f} value={form.name} onChange={set("name")} autoComplete="name" /> : null}
          <input required type="email" placeholder="ایمیل" dir="ltr" className={f} value={form.email} onChange={set("email")} autoComplete="email" />
          <input required type="password" placeholder={tab === "register" ? "رمز عبور (حداقل ۸ کاراکتر)" : "رمز عبور"} className={f} value={form.password} onChange={set("password")} autoComplete={tab === "login" ? "current-password" : "new-password"} minLength={tab === "register" ? 8 : undefined} />
          {tab === "register" ? <input required type="password" placeholder="تکرار رمز عبور" className={f} value={form.password_confirmation} onChange={set("password_confirmation")} autoComplete="new-password" /> : null}
          {error ? <p role="alert" className="rounded-xl bg-[#fff1f1] p-3 text-sm text-[#d4380d]">{error}</p> : null}
          <button disabled={busy} className="flex h-12 items-center justify-center gap-2 rounded-2xl bg-[#0054ff] font-bold text-white disabled:opacity-60">{busy ? <Loader2 className="size-5 animate-spin" /> : null}{tab === "login" ? "ورود به حساب" : "ساخت حساب"}</button>
        </form>
        <p className="mt-4 text-center text-xs text-[#6b7080]">با ورود، سبد خرید فعلی شما حفظ می‌شود. <Link href="/products" className="text-[#0054ff]">بازگشت به فروشگاه</Link></p>
      </div>
      {user && isStaff(user) ? <p className="mt-3 text-center text-sm"><Link href="/dashboard" className="text-[#0054ff]">ورود به پنل مدیریت</Link></p> : null}
    </div>
  )
}
