"use client"

import Link from "next/link"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"
import { ArrowDown, ArrowUp, ChevronDown, Copy, Eye, EyeOff, Plus, Trash2 } from "lucide-react"

import { PageShell } from "@/components/PageShell"
import { Button } from "@/components/ui/button"
import { api } from "@/lib/builder-api"
import {
  BADGE_FIELDS,
  COMPANY_SCHEMA,
  CONTACT_PAGE_SCHEMA,
  CONTACT_SCHEMA,
  FOOTER_SCHEMA,
  HEADER_SCHEMA,
  HOME_BLOCK_ORDER,
  HOME_BLOCK_SCHEMAS,
  SOCIAL_FIELDS,
  type FieldSpec,
  type HomeBlockType,
} from "@/themes/webina-corporate-v1/site-content"

type Json = Record<string, unknown>
type Block = { type: string; enabled?: boolean; props?: Json | null }
type Settings = { home_blocks?: Block[] | null; branding?: Json | null; social_links?: unknown }

const TABS = [
  { id: "home", label: "صفحه اصلی" },
  { id: "header", label: "سربرگ" },
  { id: "footer", label: "پابرگ" },
  { id: "contact", label: "تماس و شبکه‌ها" },
  { id: "contact_page", label: "صفحه تماس" },
  { id: "company", label: "اطلاعات شرکت" },
  { id: "badges", label: "نشان‌های اعتماد" },
] as const
type TabId = (typeof TABS)[number]["id"]

function isObj(v: unknown): v is Json {
  return Boolean(v) && typeof v === "object" && !Array.isArray(v)
}

function emptyItem(fields: FieldSpec[]): Json {
  const out: Json = {}
  for (const f of fields) out[f.key] = f.kind === "list" ? [] : f.kind === "switch" ? false : f.kind === "number" ? 0 : ""
  return out
}

/**
 * Page builder for the corporate homepage, header and footer. Every text, link, list and
 * number the theme renders is stored in marketing site settings and edited here.
 */
export function HomeBuilderPage() {
  const [tab, setTab] = useState<TabId>("home")
  const [blocks, setBlocks] = useState<Block[]>([])
  const [branding, setBranding] = useState<Json>({})
  const [socials, setSocials] = useState<Json[]>([])
  const [open, setOpen] = useState<number | null>(0)
  const [busy, setBusy] = useState(false)
  const [loaded, setLoaded] = useState(false)

  useEffect(() => {
    api<{ data?: Settings } & Settings>("/api/v1/marketing/settings")
      .then((res) => {
        const s: Settings = res?.data ?? res ?? {}
        const rows = Array.isArray(s.home_blocks) ? s.home_blocks : []
        const known = new Set<string>(HOME_BLOCK_ORDER)
        const present = new Set(rows.map((b) => b.type))
        setBlocks([...rows, ...HOME_BLOCK_ORDER.filter((t) => !present.has(t)).map((type) => ({ type, enabled: true, props: {} }))].sort(
          (a, b) => Number(!known.has(a.type)) - Number(!known.has(b.type)),
        ))
        setBranding(isObj(s.branding) ? s.branding : {})
        setSocials(Array.isArray(s.social_links) ? (s.social_links as unknown[]).filter(isObj) : [])
        setLoaded(true)
      })
      .catch(() => toast.error("بارگذاری تنظیمات سایت ناموفق بود."))
  }, [])

  async function save() {
    setBusy(true)
    try {
      await api("/api/v1/marketing/settings", {
        method: "PUT",
        json: { home_blocks: blocks, branding, social_links: socials },
      })
      toast.success("ذخیره شد. سایت تا یک دقیقه دیگر به‌روز می‌شود.")
    } catch {
      toast.error("ذخیره ناموفق بود.")
    } finally {
      setBusy(false)
    }
  }

  function move(i: number, dir: -1 | 1) {
    const j = i + dir
    if (j < 0 || j >= blocks.length) return
    const next = blocks.slice()
    ;[next[i], next[j]] = [next[j], next[i]]
    setBlocks(next)
    setOpen(j)
  }

  const brandingPart = (key: string): Json => (isObj(branding[key]) ? (branding[key] as Json) : {})
  const setBrandingPart = (key: string, value: unknown) => setBranding({ ...branding, [key]: value })

  return (
    <PageShell
      title="صفحه‌ساز سایت شرکتی"
      description="همه متن‌ها، دکمه‌ها، پیوندها، فهرست‌ها و اعداد صفحه اصلی، سربرگ و پابرگ از این‌جا ویرایش می‌شوند."
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

      {loaded && tab === "home" ? (
        <div className="grid gap-3">
          {blocks.map((block, i) => {
            const schema = HOME_BLOCK_SCHEMAS[block.type as HomeBlockType]
            const enabled = block.enabled !== false
            return (
              <section key={`${block.type}-${i}`} className={`rounded-2xl border ${enabled ? "" : "opacity-60"}`}>
                <header className="flex items-center gap-2 p-3">
                  <button type="button" className="flex flex-1 items-center gap-2 text-start" onClick={() => setOpen(open === i ? null : i)}>
                    <ChevronDown className={`size-4 transition ${open === i ? "rotate-180" : ""}`} aria-hidden />
                    <span className="font-semibold">{schema?.label ?? block.type}</span>
                    {!schema ? <span className="text-muted-foreground text-xs">در این قالب نمایش داده نمی‌شود</span> : null}
                  </button>
                  <IconBtn label="بالا" onClick={() => move(i, -1)}><ArrowUp className="size-4" /></IconBtn>
                  <IconBtn label="پایین" onClick={() => move(i, 1)}><ArrowDown className="size-4" /></IconBtn>
                  <IconBtn
                    label={enabled ? "پنهان کردن" : "نمایش"}
                    onClick={() => setBlocks(blocks.map((b, j) => (j === i ? { ...b, enabled: !enabled } : b)))}
                  >
                    {enabled ? <Eye className="size-4" /> : <EyeOff className="size-4" />}
                  </IconBtn>
                </header>
                {open === i && schema ? (
                  <div className="grid gap-3 border-t p-4">
                    {schema.hint ? <p className="text-muted-foreground text-xs">{schema.hint}</p> : null}
                    <SchemaForm
                      fields={schema.fields}
                      value={isObj(block.props) ? block.props : {}}
                      onChange={(props) => setBlocks(blocks.map((b, j) => (j === i ? { ...b, props } : b)))}
                    />
                  </div>
                ) : null}
              </section>
            )
          })}
        </div>
      ) : null}

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
          <p className="text-muted-foreground text-xs">اطلاعات ثبتی رسمی؛ در پابرگ، صفحه تماس و صفحه درباره ما استفاده می‌شود.</p>
          <SchemaForm fields={COMPANY_SCHEMA} value={brandingPart("company")} onChange={(v) => setBrandingPart("company", v)} />
        </Panel>
      ) : null}

      {loaded && tab === "contact_page" ? (
        <Panel>
          <p className="text-muted-foreground text-xs">
            صفحه /contact. شماره‌ها و شبکه‌ها از «تماس و شبکه‌ها» هم خوانده می‌شوند؛ فیلدهای فرم در بازاریابی ← فرم‌ها (نامک contact) ویرایش می‌شوند.
          </p>
          <SchemaForm fields={CONTACT_PAGE_SCHEMA} value={brandingPart("contact_page")} onChange={(v) => setBrandingPart("contact_page", v)} />
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

function IconBtn({ label, onClick, children }: { label: string; onClick: () => void; children: React.ReactNode }) {
  return (
    <button type="button" aria-label={label} title={label} onClick={onClick} className="hover:bg-muted rounded-md p-1.5">
      {children}
    </button>
  )
}

function SchemaForm({ fields, value, onChange }: { fields: FieldSpec[]; value: Json; onChange: (value: Json) => void }) {
  return (
    <div className="grid gap-3">
      {fields.map((field) => (
        <FieldInput key={field.key} field={field} value={value[field.key]} onChange={(v) => onChange({ ...value, [field.key]: v })} />
      ))}
    </div>
  )
}

const INPUT = "h-9 rounded-md border bg-background px-2 text-sm"

function FieldInput({ field, value, onChange }: { field: FieldSpec; value: unknown; onChange: (value: unknown) => void }) {
  const text = typeof value === "string" || typeof value === "number" ? String(value) : ""
  switch (field.kind) {
    case "list":
      return <ListField field={field} value={Array.isArray(value) ? value.filter(isObj) : []} onChange={onChange} />
    case "textarea":
      return (
        <label className="grid gap-1 text-xs">
          {field.label}
          <textarea className="min-h-20 rounded-md border bg-background px-2 py-2 text-sm leading-6" value={text} onChange={(e) => onChange(e.target.value)} />
        </label>
      )
    case "switch":
      return (
        <label className="flex items-center justify-between gap-3 text-sm">
          {field.label}
          <input type="checkbox" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} />
        </label>
      )
    case "number":
      return (
        <label className="grid gap-1 text-xs">
          {field.label}
          <input type="number" min={0} className={INPUT} value={text} onChange={(e) => onChange(e.target.value === "" ? "" : Number(e.target.value))} />
        </label>
      )
    case "select":
      return (
        <label className="grid gap-1 text-xs">
          {field.label}
          <select className={INPUT} value={text} onChange={(e) => onChange(e.target.value)}>
            <option value="">—</option>
            {field.options.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </label>
      )
    default:
      return (
        <label className="grid gap-1 text-xs">
          {field.label}
          <input
            className={INPUT}
            dir={field.kind === "url" || field.kind === "icon" ? "ltr" : undefined}
            placeholder={field.kind === "icon" ? "Lucide: Code, Sparkles…" : field.kind === "url" ? "contact یا https://…" : undefined}
            value={text}
            onChange={(e) => onChange(e.target.value)}
          />
        </label>
      )
  }
}

function ListField({
  field,
  value,
  onChange,
}: {
  field: Extract<FieldSpec, { kind: "list" }>
  value: Json[]
  onChange: (value: Json[]) => void
}) {
  const [open, setOpen] = useState<number | null>(null)
  const titleKey = useMemo(() => field.itemTitle ?? field.fields[0]?.key, [field])
  const patch = (i: number, item: Json) => onChange(value.map((row, j) => (j === i ? item : row)))
  const move = (i: number, dir: -1 | 1) => {
    const j = i + dir
    if (j < 0 || j >= value.length) return
    const next = value.slice()
    ;[next[i], next[j]] = [next[j], next[i]]
    onChange(next)
    setOpen(j)
  }
  return (
    <fieldset className="grid gap-2 rounded-xl border border-dashed p-3">
      <legend className="px-1 text-xs font-semibold">{field.label} ({value.length.toLocaleString("fa-IR")})</legend>
      {value.map((item, i) => (
        <div key={i} className="rounded-lg border">
          <div className="flex items-center gap-1 px-2 py-1.5">
            <button type="button" className="flex flex-1 items-center gap-2 truncate text-start text-sm" onClick={() => setOpen(open === i ? null : i)}>
              <ChevronDown className={`size-3.5 shrink-0 transition ${open === i ? "rotate-180" : ""}`} aria-hidden />
              <span className="truncate">{(titleKey && typeof item[titleKey] === "string" && (item[titleKey] as string)) || `مورد ${(i + 1).toLocaleString("fa-IR")}`}</span>
            </button>
            <IconBtn label="بالا" onClick={() => move(i, -1)}><ArrowUp className="size-3.5" /></IconBtn>
            <IconBtn label="پایین" onClick={() => move(i, 1)}><ArrowDown className="size-3.5" /></IconBtn>
            <IconBtn label="تکثیر" onClick={() => onChange([...value.slice(0, i + 1), structuredClone(item), ...value.slice(i + 1)])}><Copy className="size-3.5" /></IconBtn>
            <IconBtn label="حذف" onClick={() => onChange(value.filter((_, j) => j !== i))}><Trash2 className="size-3.5" /></IconBtn>
          </div>
          {open === i ? (
            <div className="border-t p-3">
              <SchemaForm fields={field.fields} value={item} onChange={(v) => patch(i, v)} />
            </div>
          ) : null}
        </div>
      ))}
      <Button type="button" variant="outline" size="sm" className="justify-self-start" onClick={() => { onChange([...value, emptyItem(field.fields)]); setOpen(value.length) }}>
        <Plus className="size-4" aria-hidden />
        افزودن
      </Button>
    </fieldset>
  )
}
