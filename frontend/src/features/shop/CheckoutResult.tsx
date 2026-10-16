"use client"

import Link from "next/link"
import { useSearchParams } from "next/navigation"
import { useEffect, useState } from "react"
import { CheckCircle2, Clock, XCircle } from "lucide-react"

import { OrderBadge } from "./OrderStatus"
import { refreshCart, shopApi, tomanFromMinor, useShopUser } from "./shop-api"

type Order = { id: number; number: string; status: string; total_minor: number; items: { name: string; quantity: number; total: number; kind?: string; course_slug?: string }[] }

export function CheckoutResult() {
  const q = useSearchParams()
  const { user } = useShopUser()
  const [order, setOrder] = useState<Order | null>(null)
  const [failed, setFailed] = useState(false)
  const id = q.get("order")
  const number = q.get("number") ?? ""
  useEffect(() => {
    if (!id) { setFailed(true); return }
    void refreshCart()
    void shopApi<Order>(`/api/v1/storefront/orders/${id}?number=${encodeURIComponent(number)}`).then((r) => (r.ok ? setOrder(r.data) : setFailed(true)))
  }, [id, number])
  const paid = order && ["paid", "processing", "shipped", "delivered"].includes(order.status)
  const reason = q.get("reason")
  return (
    <div className="mx-auto max-w-2xl px-4 py-14 text-center" dir="rtl">
      {!order && !failed ? <div className="h-48 animate-pulse rounded-3xl bg-[#f3f5f9]" /> : null}
      {failed ? <p className="text-[#6b7080]">سفارش پیدا نشد.</p> : null}
      {order ? (
        <div className="rounded-3xl border border-[#e6e9f2] bg-white p-8">
          {paid ? <CheckCircle2 className="mx-auto size-14 text-emerald-500" /> : order.status === "pending_payment" ? (q.get("payment") === "failed" || reason ? <XCircle className="mx-auto size-14 text-rose-500" /> : <Clock className="mx-auto size-14 text-amber-500" />) : <Clock className="mx-auto size-14 text-sky-500" />}
          <h1 className="mt-4 text-2xl font-black">{paid ? "پرداخت با موفقیت انجام شد" : order.status === "pending_payment" ? "پرداخت انجام نشد" : "سفارش ثبت شد"}</h1>
          {reason ? <p className="mt-2 text-sm text-[#d4380d]">{reason}</p> : null}
          <p className="mt-2 text-sm text-[#6b7080]">شماره سفارش: <b dir="ltr">{order.number}</b> · <OrderBadge status={order.status} /></p>
          <ul className="mx-auto mt-6 grid max-w-md gap-2 text-start text-sm">
            {order.items.map((i, n) => <li key={n} className="flex justify-between rounded-xl bg-[#f7f8fb] px-3 py-2"><span>{i.name} × {new Intl.NumberFormat("fa-IR").format(i.quantity)}</span><b>{tomanFromMinor(i.total)}</b></li>)}
            <li className="flex justify-between px-3 pt-2 font-black"><span>مبلغ کل</span><span>{tomanFromMinor(order.total_minor)}</span></li>
          </ul>
          <div className="mt-6 flex flex-wrap justify-center gap-2">
            {order.status === "pending_payment" ? <Link href="/account?tab=orders" className="rounded-2xl bg-[#0054ff] px-5 py-3 text-sm font-bold text-white">پرداخت دوباره از سفارشات من</Link> : null}
            {paid && order.items.some((i) => i.kind === "course") ? <Link href={user ? "/account?tab=courses" : "/account/login?next=%2Faccount%3Ftab%3Dcourses"} className="rounded-2xl bg-[#0054ff] px-5 py-3 text-sm font-bold text-white">دوره‌های من</Link> : null}
            <Link href={user ? "/account?tab=orders" : "/products"} className="rounded-2xl border border-[#d8deeb] px-5 py-3 text-sm font-bold">{user ? "سفارشات من" : "بازگشت به فروشگاه"}</Link>
          </div>
        </div>
      ) : null}
    </div>
  )
}
