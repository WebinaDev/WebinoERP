"use client"

import { useQuery } from "@tanstack/react-query"
import { useLocale } from "next-intl"
import { useState } from "react"

import { PageShell } from "@/components/PageShell"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { api } from "@/lib/api"
import { formatDateTime, formatNumber } from "@/lib/locale"

type Lead = { id: number; popup_id: number; popup_name: string; kind: "phone" | "email"; value: string; created_at: string }
type Page = { data: Lead[]; current_page: number; last_page: number; total: number }
type PopupRow = { id: number; name: string }

const sel = "h-9 rounded-md border bg-background px-2 text-sm"

/** Leads captured by storefront popups (phone / email), filterable and exportable as CSV. */
export default function PopupLeadsPageClient(_props: { route?: unknown }) {
  const locale = useLocale() as Parameters<typeof formatDateTime>[1]
  const [popupId, setPopupId] = useState("")
  const [kind, setKind] = useState("")
  const [page, setPage] = useState(1)
  const popups = useQuery({ queryKey: ["marketing-popups"], queryFn: () => api<{ data: PopupRow[] }>("/api/v1/marketing/popups") })
  const qs = new URLSearchParams({ page: String(page), per_page: "50", ...(popupId ? { popup_id: popupId } : {}), ...(kind ? { kind } : {}) })
  const leads = useQuery({ queryKey: ["marketing-popup-leads", popupId, kind, page], queryFn: () => api<Page>(`/api/v1/marketing/popups/leads?${qs}`) })
  const rows = leads.data?.data ?? []

  const exportCsv = () => {
    const csv = ["پاپ‌آپ,نوع,مقدار,تاریخ", ...rows.map((r) => [r.popup_name, r.kind === "email" ? "ایمیل" : "موبایل", r.value, formatDateTime(r.created_at, locale)].map((v) => `"${String(v).replace(/"/g, '""')}"`).join(","))].join("\n")
    const a = document.createElement("a")
    a.href = URL.createObjectURL(new Blob(["\ufeff" + csv], { type: "text/csv;charset=utf-8" }))
    a.download = "popup-leads.csv"
    a.click()
    URL.revokeObjectURL(a.href)
  }

  return (
    <PageShell
      title="سرنخ‌های پاپ‌آپ"
      description={`شماره‌ها و ایمیل‌های ثبت‌شده از پاپ‌آپ‌ها · ${formatNumber(leads.data?.total ?? 0, locale)} مورد`}
      actions={<Button variant="outline" onClick={exportCsv} disabled={rows.length === 0}>خروجی CSV</Button>}
    >
      <div className="mb-3 flex flex-wrap gap-2">
        <select className={sel} value={popupId} onChange={(e) => { setPopupId(e.target.value); setPage(1) }}>
          <option value="">همه پاپ‌آپ‌ها</option>
          {(popups.data?.data ?? []).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </select>
        <select className={sel} value={kind} onChange={(e) => { setKind(e.target.value); setPage(1) }}>
          <option value="">همه انواع</option>
          <option value="phone">موبایل</option>
          <option value="email">ایمیل</option>
        </select>
      </div>
      <Card>
        <CardContent className="p-0">
          {rows.length === 0 && !leads.isLoading ? <p className="text-muted-foreground p-4 text-sm">هنوز سرنخی ثبت نشده است.</p> : null}
          {rows.length > 0 ? (
            <table className="w-full text-sm">
              <thead className="text-muted-foreground border-b text-xs"><tr><th className="p-3 text-start">مقدار</th><th className="p-3 text-start">نوع</th><th className="p-3 text-start">پاپ‌آپ</th><th className="p-3 text-start">تاریخ</th></tr></thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id} className="border-b last:border-0">
                    <td className="p-3 font-mono" dir="ltr">{r.value}</td>
                    <td className="p-3"><Badge variant="secondary">{r.kind === "email" ? "ایمیل" : "موبایل"}</Badge></td>
                    <td className="p-3">{r.popup_name}</td>
                    <td className="text-muted-foreground p-3 text-xs">{formatDateTime(r.created_at, locale)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : null}
        </CardContent>
      </Card>
      {(leads.data?.last_page ?? 1) > 1 ? (
        <div className="mt-3 flex items-center justify-center gap-2">
          <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>قبلی</Button>
          <span className="text-muted-foreground text-xs">{formatNumber(page, locale)} / {formatNumber(leads.data?.last_page ?? 1, locale)}</span>
          <Button size="sm" variant="outline" disabled={page >= (leads.data?.last_page ?? 1)} onClick={() => setPage(page + 1)}>بعدی</Button>
        </div>
      ) : null}
    </PageShell>
  )
}
