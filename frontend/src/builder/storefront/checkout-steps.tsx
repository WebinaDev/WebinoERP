"use client"

/* Stepped storefront checkout (signed-in or guest via X-Cart-Token).
 * Steps come from GET /storefront/checkout-config; every step is saved with POST /checkout/steps/{step}
 * and the server answers with `next_step` and fresh `totals`. The order is placed with POST /checkout
 * (which reads the saved step values), then paid with the picked gateway. Prices: IRR via BuilderPrice. */

import { Check, ChevronLeft, Gift, Loader2, ShieldCheck, Tag } from "lucide-react"
import { useLocale, useTranslations } from "next-intl"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useMemo, useState } from "react"

import { acceptCart, refreshCart, shopApi, useShopCart, type ShopCart } from "@/features/shop/shop-api"

import { BuilderPrice } from "../money"
import { nextStepAfter, visibleSteps, type StepKey } from "./checkout-logic"

type Field = { key: string; label: string; required: boolean; type: string; order: number }
type Slot = { id: string; label: string }
type Day = { date: string; weekday: number; slots: Slot[] }
type Gateway = { code: string; label?: string; label_fa?: string; modes: string[]; fee_percent?: number; sandbox?: boolean }
type Method = { id: string; title: string; cost_minor: number; free_over_minor?: number; delivery_time_required?: boolean }
type Line = { product_id: number; variant_id?: string | null; variant_name?: string | null; name: string; quantity: number; unit_price_minor: number; regular_price_minor: number; line_total_minor: number }
export type CheckoutTotals = {
  lines: Line[]
  subtotal_minor: number
  regular_subtotal_minor?: number
  sale_savings_minor?: number
  discount_minor: number
  shipping_minor: number
  gift_card_minor?: number
  total_minor: number
  requires_shipping: boolean
  shipping_methods: Method[]
  shipping_method: string | null
  delivery_time_required?: boolean
  coupon?: { code: string } | null
  coupon_error?: string | null
  gift_card?: { code: string; applied_minor: number; remaining_minor: number } | null
  gift_card_error?: string | null
}
type Config = {
  stepped: boolean
  steps: { key: StepKey; label: string; enabled: boolean; order: number }[]
  fields: Record<StepKey, Field[]>
  delivery_time: { mode: string; shipping_methods: string[]; days: Day[] }
  payment: { gateway_layout: string; coupon_enabled: boolean; gift_card_enabled: boolean; summary_enabled: boolean; terms_required: boolean; terms_url: string }
  gateways: Gateway[]
  payment_modes: string[]
  shipping_methods: Method[]
  pricing: { single_price: boolean }
  gift_cards_enabled: boolean
  require_login: boolean
}
type Values = Record<string, string | boolean | null | undefined>
type StepResult = { step: StepKey; valid: boolean; values: Values; next_step: StepKey | null; totals: CheckoutTotals }
type Order = { id: number; number: string; status: string; total_minor: number }

const toLatin = (v: string) => v.replace(/[۰-۹]/g, (d) => String("۰۱۲۳۴۵۶۷۸۹".indexOf(d))).replace(/[٠-٩]/g, (d) => String("٠١٢٣٤٥٦٧٨٩".indexOf(d)))
const field = "sf-field h-11 w-full rounded-2xl border border-border bg-background px-3 text-sm outline-none focus:border-primary"
const LATIN_KEYS = new Set(["customer_phone", "customer_email", "shipping_postcode"])

export { visibleSteps }

export function StorefrontSteppedCheckout() {
  const t = useTranslations("storefrontCheckout")
  const locale = useLocale()
  const router = useRouter()
  const { cart, loading } = useShopCart()
  const [config, setConfig] = useState<Config | null>(null)
  const [states, setStates] = useState<{ code: string; name: string }[]>([])
  const [values, setValues] = useState<Values>({})
  const [totals, setTotals] = useState<CheckoutTotals | null>(null)
  const [step, setStep] = useState<StepKey>("address")
  const [done, setDone] = useState<StepKey[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState("")
  const [busy, setBusy] = useState(false)
  const [mode, setMode] = useState<"cash" | "installment">("cash")
  const [coupon, setCoupon] = useState("")
  const [gift, setGift] = useState("")
  const [giftNote, setGiftNote] = useState("")
  const [giftBalance, setGiftBalance] = useState<number | null>(null)

  const loadSession = useCallback(async () => {
    const s = await shopApi<{ values: Values; completed: StepKey[]; totals: CheckoutTotals | null }>("/api/v1/checkout/session")
    if (!s.ok) return
    setValues((v) => ({ ...s.data.values, ...v }))
    setTotals(s.data.totals)
    setDone(s.data.completed ?? [])
    if (typeof s.data.values?.gift_card_code === "string") setGift(s.data.values.gift_card_code)
    if (typeof s.data.values?.coupon_code === "string") setCoupon(s.data.values.coupon_code)
  }, [])

  useEffect(() => {
    void shopApi<Config>("/api/v1/storefront/checkout-config").then((r) => {
      if (!r.ok) return
      setConfig(r.data)
      if (r.data.payment_modes?.length && !r.data.payment_modes.includes("cash")) setMode(r.data.payment_modes[0] as "cash" | "installment")
    })
    void shopApi<{ states?: { code: string; name: string }[] }>("/api/v1/public/geo/states").then((r) => r.ok && setStates(r.data.states ?? []))
    void loadSession()
  }, [loadSession])

  // Cart changed elsewhere (qty, coupon): refresh totals.
  const cartStamp = cart ? cart.items.map((l) => `${l.id}:${l.quantity}`).join(",") + `|${cart.coupon_code ?? ""}` : ""
  useEffect(() => {
    if (cartStamp) void loadSession()
  }, [cartStamp, loadSession])

  const steps = useMemo(() => (config ? visibleSteps(config, totals) : []), [config, totals])
  useEffect(() => {
    if (steps.length && !steps.includes(step)) setStep(steps[0])
  }, [steps, step])

  const set = (k: string, v: string | boolean | null) => setValues((prev) => ({ ...prev, [k]: v }))
  const fieldsOf = (k: StepKey) => [...(config?.fields?.[k] ?? [])].sort((a, b) => a.order - b.order)
  const shippingFields = (k: StepKey) => fieldsOf(k).filter((f) => totals?.requires_shipping !== false || ["customer_name", "customer_phone", "customer_email", "customer_note"].includes(f.key) || k === "payment")
  const gateways = (config?.gateways ?? []).filter((g) => (g.modes ?? []).includes(mode))
  const total = totals?.total_minor ?? cart?.pricing?.total_minor ?? 0

  useEffect(() => {
    const picked = String(values.payment_gateway ?? "")
    if (gateways.length && !gateways.some((g) => g.code === picked)) set("payment_gateway", gateways[0].code)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [gateways.map((g) => g.code).join(","), values.payment_gateway])

  async function submitStep(k: StepKey) {
    setBusy(true)
    setError("")
    setErrors({})
    const body: Values = {}
    for (const f of fieldsOf(k)) {
      if (f.key === "order_summary" || f.key === "terms") continue
      const v = values[f.key]
      body[f.key] = typeof v === "string" && LATIN_KEYS.has(f.key) ? toLatin(v) : v ?? null
    }
    if (k === "shipping") body.shipping_method = String(values.shipping_method ?? totals?.shipping_method ?? "")
    if (k === "payment") {
      body.payment_mode = config?.pricing.single_price ? "cash" : mode
      body.coupon_code = coupon.trim() || null
      body.gift_card_code = gift.trim() || null
      body.terms_accepted = values.terms_accepted === true
      if (config?.payment.terms_required && values.terms_accepted !== true) {
        setBusy(false)
        setErrors({ terms: t("terms_required") })
        return null
      }
    }
    const r = await shopApi<StepResult>(`/api/v1/checkout/steps/${k}`, { method: "POST", json: body })
    setBusy(false)
    if (!r.ok) {
      setError(r.message)
      setErrors(Object.fromEntries(Object.entries(r.errors ?? {}).map(([key, v]) => [key, v[0]])))
      return null
    }
    setValues((prev) => ({ ...prev, ...r.data.values }))
    setTotals(r.data.totals)
    setDone((d) => Array.from(new Set([...d, k])))
    return r.data
  }

  async function next(k: StepKey) {
    const res = await submitStep(k)
    if (!res) return
    if (k === "payment") return placeOrder()
    const target = config ? nextStepAfter(visibleSteps(config, res.totals), [...done, k], k, res.next_step) : res.next_step
    if (target) setStep(target)
    if (typeof window !== "undefined") window.scrollTo({ top: 0, behavior: "smooth" })
  }

  async function placeOrder() {
    setBusy(true)
    const payMode = config?.pricing.single_price ? "cash" : mode
    const order = await shopApi<Order>("/api/v1/checkout", { method: "POST", json: { payment_mode: payMode, terms_accepted: values.terms_accepted === true } })
    if (!order.ok) {
      setBusy(false)
      if (order.status === 401) {
        router.push(`/account/login?next=${encodeURIComponent("/checkout")}`)
        return
      }
      setError(order.message)
      setErrors(Object.fromEntries(Object.entries(order.errors ?? {}).map(([key, v]) => [key, v[0]])))
      return
    }
    const result = `/checkout/result?order=${order.data.id}&number=${encodeURIComponent(order.data.number)}`
    if (order.data.status !== "pending_payment") {
      await refreshCart()
      router.push(result)
      return
    }
    const intent = await shopApi<{ redirect_url?: string | null }>("/api/v1/payments/intent", {
      method: "POST",
      json: { order_id: order.data.id, provider: String(values.payment_gateway ?? "") || null, mode: payMode, number: order.data.number },
    })
    await refreshCart()
    if (intent.ok && intent.data.redirect_url?.startsWith("http")) {
      window.location.assign(intent.data.redirect_url)
      return
    }
    setBusy(false)
    router.push(`${result}&payment=failed&reason=${encodeURIComponent(intent.ok ? t("gateway_no_answer") : intent.message)}`)
  }

  async function applyCoupon() {
    setErrors((e) => ({ ...e, coupon_code: "" }))
    const r = await shopApi<ShopCart>("/api/v1/cart/coupon", { method: "POST", json: { coupon_code: coupon.trim() } })
    acceptCart(r)
    if (!r.ok) {
      setErrors((e) => ({ ...e, coupon_code: r.message }))
      return
    }
    if (r.data.pricing?.coupon_error) setErrors((e) => ({ ...e, coupon_code: String(r.data.pricing.coupon_error) }))
    await loadSession()
  }

  async function checkGift() {
    setGiftNote("")
    setErrors((e) => ({ ...e, gift_card_code: "" }))
    const code = gift.trim()
    if (!code) return
    const r = await shopApi<{ balance_minor: number; status?: string }>("/api/v1/public/gift-cards/balance", { method: "POST", json: { code } })
    if (!r.ok || r.data.status !== "active") {
      setErrors((e) => ({ ...e, gift_card_code: r.ok ? t("gift_unusable") : r.message }))
      return
    }
    setGiftNote(t("gift_balance"))
    set("gift_card_code", code)
    setGiftBalance(r.data.balance_minor)
  }

  if (loading || !config) return <div className="mx-auto max-w-6xl px-4 py-10" dir="rtl"><div className="h-60 animate-pulse rounded-3xl bg-muted" /></div>
  if (!cart?.items.length) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center" dir="rtl">
        <h1 className="text-xl font-black">{t("empty")}</h1>
        <Link href="/shop" className="mt-4 inline-flex rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground">{t("continue_shop")}</Link>
      </div>
    )
  }
  if (config.require_login && !cart.authenticated) {
    return (
      <div className="mx-auto max-w-lg px-4 py-16 text-center" dir="rtl">
        <h1 className="text-xl font-black">{t("login_title")}</h1>
        <Link href={`/account/login?next=${encodeURIComponent("/checkout")}`} className="mt-4 inline-flex rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground">{t("login_cta")}</Link>
      </div>
    )
  }

  const err = (k: string) => (errors[k] ? <span role="alert" className="text-xs text-destructive">{errors[k]}</span> : null)
  const stepLabel = (k: StepKey) => config.steps.find((s) => s.key === k)?.label || ({ address: t("step_address"), delivery_time: t("step_delivery_time"), shipping: t("step_shipping"), payment: t("step_payment") } as const)[k]
  const dayLabel = (iso: string) => {
    try {
      return new Intl.DateTimeFormat(locale === "fa" ? "fa-IR-u-ca-persian" : locale, { weekday: "long", day: "numeric", month: "long" }).format(new Date(`${iso}T12:00:00`))
    } catch {
      return iso
    }
  }
  const methods = totals?.shipping_methods?.length ? totals.shipping_methods : config.shipping_methods
  const pickedMethod = String(values.shipping_method ?? totals?.shipping_method ?? methods[0]?.id ?? "")
  const days = config.delivery_time.days ?? []
  const pickedDay = days.find((d) => d.date === values.delivery_date) ?? null
  // Gift card checked but not yet saved with the payment step: preview its effect locally.
  const giftPreview = totals?.gift_card_minor ? 0 : giftBalance != null ? Math.min(giftBalance, totals?.total_minor ?? total) : 0
  const payable = Math.max(0, (totals?.total_minor ?? total) - giftPreview)

  const renderField = (f: Field) => {
    const v = values[f.key]
    const label = <span>{f.label}{f.required ? <b className="text-destructive"> *</b> : null}</span>
    const wide = f.type === "textarea"
    if (f.key === "shipping_state_code" || f.type === "select") {
      return (
        <label key={f.key} className="grid gap-1 text-sm">
          {label}
          <select className={field} value={String(v ?? "")} required={f.required} onChange={(e) => set(f.key, e.target.value)}>
            <option value="">{t("choose")}</option>
            {states.map((s) => <option key={s.code} value={s.code}>{s.name}</option>)}
          </select>
          {err(f.key)}
        </label>
      )
    }
    if (f.type === "textarea") {
      return (
        <label key={f.key} className={`grid gap-1 text-sm ${wide ? "md:col-span-2" : ""}`}>
          {label}
          <textarea className={`${field} h-24 py-2`} value={String(v ?? "")} required={f.required} onChange={(e) => set(f.key, e.target.value)} />
          {err(f.key)}
        </label>
      )
    }
    const latin = LATIN_KEYS.has(f.key)
    return (
      <label key={f.key} className="grid gap-1 text-sm">
        {label}
        <input
          className={field}
          type={f.type === "email" ? "email" : f.type === "tel" ? "tel" : "text"}
          inputMode={f.type === "tel" || f.key === "shipping_postcode" ? "numeric" : undefined}
          dir={latin ? "ltr" : undefined}
          autoComplete={f.key === "customer_name" ? "name" : f.key === "customer_phone" ? "tel" : f.key === "customer_email" ? "email" : f.key === "shipping_postcode" ? "postal-code" : undefined}
          value={String(v ?? "")}
          required={f.required}
          onChange={(e) => set(f.key, e.target.value)}
        />
        {err(f.key)}
      </label>
    )
  }

  const body = (k: StepKey) => {
    if (k === "address") return <div className="grid gap-4 md:grid-cols-2">{shippingFields("address").map(renderField)}</div>
    if (k === "delivery_time") {
      return (
        <div className="grid gap-4">
          {days.length ? (
            <div className="flex gap-2 overflow-x-auto pb-1" role="radiogroup" aria-label={t("delivery_day")}>
              {days.map((d) => (
                <button key={d.date} type="button" role="radio" aria-checked={values.delivery_date === d.date} onClick={() => { set("delivery_date", d.date); set("delivery_slot", null) }}
                  className={`shrink-0 rounded-2xl border px-4 py-3 text-sm font-bold ${values.delivery_date === d.date ? "border-primary bg-primary/10 text-primary" : "border-border"}`}>
                  {dayLabel(d.date)}
                </button>
              ))}
            </div>
          ) : <p className="text-sm text-muted-foreground">{t("delivery_none")}</p>}
          {err("delivery_date")}
          {pickedDay ? (
            <div className="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label={t("delivery_slot")}>
              {pickedDay.slots.map((s) => (
                <label key={s.id} className={`flex cursor-pointer items-center gap-2 rounded-2xl border p-3 text-sm ${values.delivery_slot === s.id ? "border-primary bg-primary/10" : "border-border"}`}>
                  <input type="radio" name="slot" className="accent-primary" checked={values.delivery_slot === s.id} onChange={() => set("delivery_slot", s.id)} />
                  {s.label}
                </label>
              ))}
            </div>
          ) : null}
          {err("delivery_slot")}
        </div>
      )
    }
    if (k === "shipping") {
      return (
        <div className="grid gap-2" role="radiogroup" aria-label={stepLabel("shipping")}>
          {methods.map((m) => (
            <label key={m.id} className={`flex cursor-pointer items-center justify-between gap-3 rounded-2xl border p-4 text-sm ${pickedMethod === m.id ? "border-primary bg-primary/10" : "border-border"}`}>
              <span className="flex items-center gap-2">
                <input type="radio" name="ship" className="accent-primary" checked={pickedMethod === m.id} onChange={() => set("shipping_method", m.id)} />
                <b>{m.title}</b>
                {m.delivery_time_required ? <span className="rounded-full bg-muted px-2 text-[11px]">{t("with_delivery_time")}</span> : null}
              </span>
              <span className="text-end">
                {m.cost_minor > 0 ? <BuilderPrice amount={m.cost_minor} from="IRR" /> : <b className="text-primary">{t("free")}</b>}
                {m.free_over_minor && m.cost_minor > 0 ? <span className="block text-[11px] text-muted-foreground">{t("free_over")} <BuilderPrice amount={m.free_over_minor} from="IRR" /></span> : null}
              </span>
            </label>
          ))}
          {err("shipping_method")}
        </div>
      )
    }
    const modes = config.pricing.single_price ? ["cash"] : config.payment_modes
    return (
      <div className="grid gap-5">
        {payable > 0 ? (
          <div className="grid gap-3">
            <h3 className="text-sm font-black">{t("gateway_title")}</h3>
            {modes.length > 1 ? (
              <div className="grid grid-cols-2 gap-2 rounded-2xl bg-muted p-1">
                {modes.map((m) => (
                  <button key={m} type="button" onClick={() => setMode(m as "cash" | "installment")} className={`h-10 rounded-xl text-sm font-bold ${mode === m ? "bg-card text-primary shadow" : "text-muted-foreground"}`}>
                    {m === "cash" ? t("mode_cash") : t("mode_installment")}
                  </button>
                ))}
              </div>
            ) : null}
            <div className={config.payment.gateway_layout === "grid" ? "grid gap-2 sm:grid-cols-2" : "grid gap-2"} role="radiogroup" aria-label={t("gateway_title")}>
              {gateways.length ? gateways.map((g) => (
                <label key={g.code} className={`flex cursor-pointer items-center justify-between gap-3 rounded-2xl border p-4 text-sm ${values.payment_gateway === g.code ? "border-primary bg-primary/10" : "border-border"}`}>
                  <span className="flex items-center gap-2">
                    <input type="radio" name="gw" className="accent-primary" checked={values.payment_gateway === g.code} onChange={() => set("payment_gateway", g.code)} />
                    <b>{g.label_fa || g.label || g.code}</b>
                    {g.sandbox ? <span className="rounded-full bg-amber-100 px-2 text-[11px] text-amber-700">{t("sandbox")}</span> : null}
                  </span>
                  {g.fee_percent ? <span className="text-xs text-muted-foreground">{t("fee", { percent: new Intl.NumberFormat(locale === "fa" ? "fa-IR" : locale).format(g.fee_percent) })}</span> : null}
                </label>
              )) : <p className="rounded-2xl bg-muted p-4 text-sm">{t("no_gateway")}</p>}
            </div>
            {err("payment_gateway")}
          </div>
        ) : <p className="rounded-2xl bg-primary/10 p-4 text-sm font-bold text-primary">{t("nothing_to_pay")}</p>}
        <div className="grid gap-3 md:grid-cols-2">
          {config.payment.coupon_enabled ? (
            <div className="grid gap-1 text-sm">
              <span className="flex items-center gap-1"><Tag className="size-4" />{t("coupon")}</span>
              <div className="flex gap-2">
                <input className={field} value={coupon} onChange={(e) => setCoupon(e.target.value)} dir="ltr" />
                <button type="button" onClick={() => void applyCoupon()} className="shrink-0 rounded-2xl border border-border px-4 text-sm font-bold">{t("apply")}</button>
              </div>
              {totals?.coupon ? <span className="text-xs text-primary">{t("coupon_ok", { code: totals.coupon.code })}</span> : null}
              {err("coupon_code")}
            </div>
          ) : null}
          {config.payment.gift_card_enabled && config.gift_cards_enabled ? (
            <div className="grid gap-1 text-sm">
              <span className="flex items-center gap-1"><Gift className="size-4" />{t("gift_card")}</span>
              <div className="flex gap-2">
                <input className={field} value={gift} onChange={(e) => { setGift(e.target.value); setGiftBalance(null); setGiftNote("") }} dir="ltr" />
                <button type="button" onClick={() => void checkGift()} className="shrink-0 rounded-2xl border border-border px-4 text-sm font-bold">{t("apply")}</button>
              </div>
              {giftNote && giftBalance != null ? <span className="text-xs text-primary">{giftNote} <BuilderPrice amount={giftBalance} from="IRR" /></span> : null}
              {err("gift_card_code")}
            </div>
          ) : null}
        </div>
        {config.payment.terms_required || fieldsOf("payment").some((f) => f.key === "terms") ? (
          <label className="flex items-start gap-2 text-sm">
            <input type="checkbox" className="mt-1 accent-primary" checked={values.terms_accepted === true} onChange={(e) => set("terms_accepted", e.target.checked)} />
            <span>
              {fieldsOf("payment").find((f) => f.key === "terms")?.label || t("terms")}
              {config.payment.terms_url ? <> <a href={config.payment.terms_url} target="_blank" rel="noreferrer" className="font-bold text-primary underline">{t("terms_read")}</a></> : null}
            </span>
          </label>
        ) : null}
        {err("terms")}
      </div>
    )
  }

  const current = steps.includes(step) ? step : steps[0]
  const idx = steps.indexOf(current)

  return (
    <div className="sf-checkout mx-auto max-w-6xl px-4 py-8" dir="rtl">
      <h1 className="text-2xl font-black">{t("title")}</h1>
      {!cart.authenticated ? (
        <p className="mt-3 rounded-2xl bg-muted px-4 py-3 text-sm">{t("guest_hint")} <Link href={`/account/login?next=${encodeURIComponent("/checkout")}`} className="font-bold text-primary">{t("login_link")}</Link></p>
      ) : null}
      <ol className="mt-5 flex gap-2 overflow-x-auto pb-1" aria-label={t("progress")}>
        {steps.map((k, i) => {
          const reached = i <= idx || done.includes(k)
          return (
            <li key={k} className="shrink-0">
              <button type="button" disabled={!reached || busy} onClick={() => setStep(k)} aria-current={k === current ? "step" : undefined}
                className={`flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold ${k === current ? "bg-primary text-primary-foreground" : reached ? "bg-primary/10 text-primary" : "bg-muted text-muted-foreground"}`}>
                <span className="grid size-6 place-items-center rounded-full bg-background/30 text-xs">{done.includes(k) && k !== current ? <Check className="size-3.5" /> : new Intl.NumberFormat(locale === "fa" ? "fa-IR" : locale).format(i + 1)}</span>
                {stepLabel(k)}
              </button>
            </li>
          )
        })}
      </ol>
      <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
        <form
          className="sf-card rounded-3xl border border-border bg-card p-5"
          onSubmit={(e) => {
            e.preventDefault()
            void next(current)
          }}
        >
          <h2 className="mb-4 text-lg font-black">{stepLabel(current)}</h2>
          {body(current)}
          {error ? <p role="alert" className="mt-4 rounded-xl bg-destructive/10 p-3 text-sm text-destructive">{error}</p> : null}
          <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
            {idx > 0 ? (
              <button type="button" onClick={() => setStep(steps[idx - 1])} className="rounded-2xl border border-border px-4 py-2.5 text-sm font-bold">{t("back")}</button>
            ) : <Link href="/cart" className="text-sm text-muted-foreground hover:text-primary">{t("back_cart")}</Link>}
            <button disabled={busy || (current === "payment" && payable > 0 && !gateways.length)} className="flex h-12 min-w-40 items-center justify-center gap-2 rounded-2xl bg-primary px-6 text-sm font-bold text-primary-foreground disabled:opacity-60">
              {busy ? <Loader2 className="size-5 animate-spin" /> : current === "payment" ? <ShieldCheck className="size-5" /> : null}
              {current === "payment" ? (payable > 0 ? t("pay") : t("place_order")) : t("next")}
              {current !== "payment" && !busy ? <ChevronLeft className="size-4" /> : null}
            </button>
          </div>
        </form>
        <CheckoutSummary totals={totals} cart={cart} giftPreview={giftPreview} />
      </div>
    </div>
  )
}

function Row({ label, children, strong }: { label: string; children: React.ReactNode; strong?: boolean }) {
  return (
    <div className={`flex items-center justify-between gap-3 ${strong ? "text-base font-black" : "text-sm"}`}>
      <span className={strong ? "" : "text-muted-foreground"}>{label}</span>
      <span>{children}</span>
    </div>
  )
}

/** Order summary: lines (variant, strike-through regular price), savings, discount, shipping, gift card, total. */
export function CheckoutSummary({ totals, cart, giftPreview = 0 }: { totals: CheckoutTotals | null; cart: ShopCart; giftPreview?: number }) {
  const t = useTranslations("storefrontCheckout")
  const lines: Line[] = totals?.lines ?? cart.items.map((l) => ({
    product_id: l.product_id, variant_id: l.variant_id, variant_name: l.variant_name, name: l.product.name, quantity: l.quantity,
    unit_price_minor: l.unit_price_minor, regular_price_minor: l.regular_price_minor ?? l.unit_price_minor, line_total_minor: l.line_total_minor,
  }))
  const p = cart.pricing
  const subtotal = totals?.subtotal_minor ?? p.subtotal_minor
  const discount = totals?.discount_minor ?? p.discount_minor
  const shipping = totals?.shipping_minor ?? p.shipping_minor
  const giftMinor = (totals?.gift_card_minor ?? 0) + giftPreview
  const total = Math.max(0, (totals?.total_minor ?? p.total_minor) - giftPreview)
  const physical = totals ? totals.requires_shipping : p.requires_shipping !== false
  return (
    <aside className="sf-card h-fit rounded-3xl border border-border bg-card p-5 lg:sticky lg:top-24" aria-label={t("summary")}>
      <h2 className="font-black">{t("summary")}</h2>
      <ul className="mt-4 grid gap-3">
        {lines.map((l) => (
          <li key={`${l.product_id}:${l.variant_id ?? ""}`} className="flex items-start justify-between gap-3 text-sm">
            <span className="min-w-0">
              <span className="block truncate font-bold">{l.name}</span>
              {l.variant_name ? <span className="block text-xs text-muted-foreground">{l.variant_name}</span> : null}
              <span className="text-xs text-muted-foreground">{t("qty", { n: new Intl.NumberFormat("fa-IR").format(l.quantity) })}</span>
            </span>
            <BuilderPrice amount={l.line_total_minor} original={l.regular_price_minor > l.unit_price_minor ? l.regular_price_minor * l.quantity : null} from="IRR" className="shrink-0 font-bold" />
          </li>
        ))}
      </ul>
      <div className="mt-4 grid gap-2 border-t border-border pt-4">
        <Row label={t("subtotal")}><BuilderPrice amount={subtotal} from="IRR" /></Row>
        {totals?.sale_savings_minor ? <Row label={t("savings")}><span className="text-primary"><BuilderPrice amount={totals.sale_savings_minor} from="IRR" /></span></Row> : null}
        {discount > 0 ? <Row label={t("discount")}><span className="text-primary">−<BuilderPrice amount={discount} from="IRR" /></span></Row> : null}
        {physical ? <Row label={t("shipping")}>{shipping > 0 ? <BuilderPrice amount={shipping} from="IRR" /> : t("free")}</Row> : null}
        {giftMinor > 0 ? <Row label={t("gift_card")}><span className="text-primary">−<BuilderPrice amount={giftMinor} from="IRR" /></span></Row> : null}
        <div className="mt-2 border-t border-border pt-3">
          <Row label={t("total")} strong>{total > 0 ? <BuilderPrice amount={total} from="IRR" /> : t("free")}</Row>
        </div>
      </div>
    </aside>
  )
}
