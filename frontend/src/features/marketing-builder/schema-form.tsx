"use client"

import { useMemo, useState } from "react"
import { ArrowDown, ArrowUp, ChevronDown, Copy, Plus, Trash2 } from "lucide-react"

import { Button } from "@/components/ui/button"
import type { FieldSpec } from "@/themes/webina-corporate-v1/site-content"

export type Json = Record<string, unknown>

export function isObj(v: unknown): v is Json {
  return Boolean(v) && typeof v === "object" && !Array.isArray(v)
}

export function emptyItem(fields: FieldSpec[]): Json {
  const out: Json = {}
  for (const f of fields) out[f.key] = f.kind === "list" ? [] : f.kind === "switch" ? false : f.kind === "number" ? 0 : ""
  return out
}

export function IconBtn({ label, onClick, children }: { label: string; onClick: () => void; children: React.ReactNode }) {
  return (
    <button type="button" aria-label={label} title={label} onClick={onClick} className="hover:bg-muted rounded-md p-1.5">
      {children}
    </button>
  )
}

/** Generic schema-driven editor (text, textarea, url, icon, number, switch, select, nested lists). */
export function SchemaForm({ fields, value, onChange }: { fields: FieldSpec[]; value: Json; onChange: (value: Json) => void }) {
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

export function ListField({
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
