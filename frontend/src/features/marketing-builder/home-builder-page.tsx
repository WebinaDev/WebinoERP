"use client"

import Link from "next/link"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { PageShell } from "@/components/PageShell"
import { Button } from "@/components/ui/button"
import { api } from "@/lib/builder-api"
import {
  BADGE_FIELDS,
  COMPANY_SCHEMA,
  CONTACT_SCHEMA,
  FOOTER_SCHEMA,
  HEADER_SCHEMA,
  SOCIAL_FIELDS,
} from "@/themes/webina-corporate-v1/site-content"
import { isObj, ListField, SchemaForm, type Json } from "./schema-form"

type Settings = { branding?: Json | null; social_links?: unknown }

const TABS = [
  { id: "header", label: "سربرگ" },
  { id: "footer", label: "پابرگ" },
  { id: "contact", label: "تماس و شبکه‌ها" },
  { id: "company", label: "اطلاعات شرکت" },
  { id: "badges", label: "نشان‌های اعتماد" },
] as const
type TabId = (typeof TABS)[number]["id"]

const PAGES = [
  { slug: "home", label: "صفحه اصلی" },
  { slug: "contact", label: "صفحه تماس" },
  { slug: "about", label: "درباره ما" },
]

/**
 * Global settings of the corporate site (header, footer, contact channels, company record, badges).
 * Page content (home, contact, about) lives in page-builder documents built from «وبینا» widgets.
 */
export function HomeBuilderPage() {
  const [tab, setTab] = useState<TabId>("header")
  const [branding, setBranding] = useState<Json>({})
  const [socials, setSocials] = useState<Json[]>([])
  const [pages, setPages] = useState<{ id: number; slug: string }[]>([])
  const [busy, setBusy] = useState(false)
  const [loaded, setLoaded] = useState(false)

  useEffect(() => {
    api<{ data?: Settings } & Settings>("/api/v1/marketing/settings")
      .then((res) => {
        const s: Settings = res?.data ?? res ?? {}
        setBranding(isObj(s.branding) ? s.branding : {})
        setSocials(Array.isArray(s.social_links) ? (s.social_links as unknown[]).filter(isObj) : [])
        setLoaded(true)
      })
      .catch(() => toast.error("بارگذاری تنظیمات سایت ناموفق بود."))
    api<{ pages?: { id: number; slug: string }[]; data?: { pages?: { id: number; slug: string }[] } }>("/api/v1/builder")
      .then((res) => setPages(res?.pages ?? res?.data?.pages ?? []))
      .catch(() => setPages([]))
  }, [])

  async function save() {
    setBusy(true)
    try {
      await api("/api/v1/marketing/settings", { method: "PUT", json: { branding, social_links: socials } })
      toast.success("ذخیره شد. سایت تا یک دقیقه دیگر به‌روز می‌شود.")
    } catch {
      toast.error("ذخیره ناموفق بود.")
    } finally {
      setBusy(false)
    }
  }

  const brandingPart = (key: string): Json => (isObj(branding[key]) ? (branding[key] as Json) : {})
  const setBrandingPart = (key: string, value: unknown) => setBranding({ ...branding, [key]: value })

  return (
    <PageShell
      title="تنظیمات سراسری سایت"
      description="سربرگ، پابرگ، راه‌های تماس، اطلاعات ثبتی و نشان‌ها. محتوای صفحه‌ها در صفحه‌ساز با ویجت‌های «وبینا» ساخته می‌شود."
      actions={
        <>
          <Button variant="outline" asChild>
            <Link href="/" target="_blank">مشاهده سایت</Link>
          </Button>
          <Button variant="outline" asChild>
            <Link href="/dashboard/builder">صفحه‌ساز</Link>
          </Button>
          <Button disabled={busy || !loaded} onClick={() => void save()}>
            ذخیره و انتشار
          </Button>
        </>
      }
    >
      <section className="grid gap-2 rounded-2xl border border-dashed p-4">
        <p className="text-sm font-semibold">ویرایش صفحه‌ها در صفحه‌ساز</p>
        <div className="flex flex-wrap gap-2">
          {PAGES.map((p) => {
            const row = pages.find((r) => r.slug === p.slug)
            return (
              <Button key={p.slug} variant="outline" size="sm" asChild disabled={!row}>
                <Link href={row ? `/dashboard/builder/${row.id}` : "/dashboard/builder"}>{p.label}</Link>
              </Button>
            )
          })}
        </div>
      </section>

      <div className="flex flex-wrap gap-2" role="tablist">
        {TABS.map((item) => (
          <button
            key={item.id}
            type="button"
            role="tab"
            aria-selected={tab === item.id}
            onClick={() => setTab(item.id)}
            className={`rounded-xl border px-3 py-2 text-sm ${tab === item.id ? "border-primary bg-primary/5 font-semibold" : ""}`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {!loaded ? <p className="text-muted-foreground text-sm">در حال بارگذاری…</p> : null}

      {loaded && tab === "header" ? (
        <Panel>
          <SchemaForm fields={HEADER_SCHEMA} value={brandingPart("header")} onChange={(v) => setBrandingPart("header", v)} />
        </Panel>
      ) : null}

      {loaded && tab === "footer" ? (
        <Panel>
          <SchemaForm fields={FOOTER_SCHEMA} value={brandingPart("footer")} onChange={(v) => setBrandingPart("footer", v)} />
        </Panel>
      ) : null}

      {loaded && tab === "contact" ? (
        <Panel>
          <SchemaForm fields={CONTACT_SCHEMA} value={brandingPart("contact")} onChange={(v) => setBrandingPart("contact", v)} />
          <ListField
            field={{ key: "social_links", label: "شبکه‌های اجتماعی", kind: "list", fields: SOCIAL_FIELDS, itemTitle: "label" }}
            value={socials}
            onChange={(v) => setSocials(v)}
          />
        </Panel>
      ) : null}

      {loaded && tab === "company" ? (
        <Panel>
          <p className="text-muted-foreground text-xs">اطلاعات ثبتی رسمی؛ در پابرگ و ویجت‌های «معرفی رسمی» و «نقشه» استفاده می‌شود.</p>
          <SchemaForm fields={COMPANY_SCHEMA} value={brandingPart("company")} onChange={(v) => setBrandingPart("company", v)} />
        </Panel>
      ) : null}

      {loaded && tab === "badges" ? (
        <Panel>
          <ListField
            field={{ key: "trust_badges", label: "نشان‌های اعتماد (اینماد، ساماندهی…)", kind: "list", fields: BADGE_FIELDS, itemTitle: "label" }}
            value={Array.isArray(branding.trust_badges) ? (branding.trust_badges as unknown[]).filter(isObj) : []}
            onChange={(v) => setBrandingPart("trust_badges", v)}
          />
        </Panel>
      ) : null}
    </PageShell>
  )
}

function Panel({ children }: { children: React.ReactNode }) {
  return <section className="grid gap-3 rounded-2xl border p-4">{children}</section>
}
