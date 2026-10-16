export const ORDER_STATUS: Record<string, { label: string; cls: string }> = {
  pending_payment: { label: "در انتظار پرداخت", cls: "bg-amber-50 text-amber-700" },
  paid: { label: "پرداخت‌شده", cls: "bg-emerald-50 text-emerald-700" },
  processing: { label: "در حال پردازش", cls: "bg-sky-50 text-sky-700" },
  shipped: { label: "ارسال‌شده", cls: "bg-indigo-50 text-indigo-700" },
  delivered: { label: "تحویل‌شده", cls: "bg-emerald-50 text-emerald-700" },
  cancelled: { label: "لغوشده", cls: "bg-rose-50 text-rose-700" },
}
export function OrderBadge({ status }: { status: string }) {
  const s = ORDER_STATUS[status] ?? { label: status, cls: "bg-slate-100 text-slate-600" }
  return <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold ${s.cls}`}>{s.label}</span>
}
