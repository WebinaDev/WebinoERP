"use client"

import { useRef, useState } from "react"

import { LocaleDatePicker } from "@/components/ui/locale-date-picker"

import { isVisible, missingOnStep, parseFormFields, stepCount, visiblePayload, type FormField } from "../forms"
import { propStr } from "../props"
import type { WidgetNode } from "../types"

const API_BASE = process.env.NEXT_PUBLIC_API_URL?.trim() || "/api"

function Field({ field, value, onChange }: { field: FormField; value: string; onChange: (value: string) => void }) {
  const common = { id: `wbf-${field.name}`, name: field.name, required: field.required, "aria-required": field.required || undefined }
  if (field.type === "textarea") return <textarea {...common} className="wb-field min-h-24" value={value} onChange={(event) => onChange(event.target.value)} />
  if (field.type === "select") {
    return (
      <select {...common} className="wb-field" value={value} onChange={(event) => onChange(event.target.value)}>
        <option value="">انتخاب کنید</option>
        {field.options.map((option) => <option key={option} value={option}>{option}</option>)}
      </select>
    )
  }
  if (field.type === "radio" || field.type === "checkbox") {
    const selected = value ? value.split("، ") : []
    return (
      <div className="flex flex-wrap gap-3" role={field.type === "radio" ? "radiogroup" : "group"}>
        {field.options.map((option) => (
          <label key={option} className="flex items-center gap-1.5 text-sm">
            <input
              type={field.type}
              name={field.name}
              value={option}
              checked={selected.includes(option)}
              onChange={(event) => {
                if (field.type === "radio") onChange(option)
                else onChange((event.target.checked ? [...selected, option] : selected.filter((item) => item !== option)).join("، "))
              }}
            />
            {option}
          </label>
        ))}
      </div>
    )
  }
  if (field.type === "date") return <LocaleDatePicker value={value || null} onChange={(next) => onChange(next ?? "")} placeholder={field.label} />
  const type = field.type === "tel" || field.type === "email" || field.type === "number" ? field.type : "text"
  return <input {...common} type={type} dir={type === "tel" || type === "email" ? "ltr" : undefined} className="wb-field" value={value} onChange={(event) => onChange(event.target.value)} />
}

/**
 * Builder form (ported from WebinoDashboard): typed fields, optional/conditional fields, multi-step,
 * honeypot + timing anti-spam. Submits into the Marketing forms system: POST /v1/public/forms/{formKey}
 * (the form with that slug in داشبورد → بازاریابی → فرم‌ها stores the submission).
 */
export function FormWidget({ widget }: { widget: WidgetNode }) {
  const fields = parseFormFields(widget.props.fields)
  const showPhone = widget.props.showPhone === true && !fields.some((f) => f.name === "phone")
  const showEmail = widget.props.showEmail === true && !fields.some((f) => f.name === "email")
  const columns = propStr(widget.props, "columns", "1") === "2"
  const steps = stepCount(fields)
  const [step, setStep] = useState(1)
  const [values, setValues] = useState<Record<string, string>>({})
  const [done, setDone] = useState(false)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState("")
  const [hp, setHp] = useState("")
  const started = useRef<number | null>(null)
  const success = propStr(widget.props, "success", "پیام شما ثبت شد. به‌زودی پاسخ می‌دهیم.")
  const formKey = propStr(widget.props, "formKey", "") || "contact"
  const title = propStr(widget.props, "title", "")
  const intro = propStr(widget.props, "intro", "")
  const note = propStr(widget.props, "note", "")
  const anchor = propStr(widget.props, "anchor", "").replace(/[^\w-]/g, "")
  if (done) return <p id={anchor || undefined} className="wb-type-body rounded-2xl bg-muted p-4 text-foreground" role="status">{success}</p>
  const set = (name: string, value: string) => {
    if (started.current === null) started.current = Date.now()
    setValues((current) => ({ ...current, [name]: value }))
  }
  const last = step >= steps
  const wide = (field: FormField) => field.type === "textarea" || field.type === "radio" || field.type === "checkbox"
  return (
    <form
      id={anchor || undefined}
      className="wb-form relative grid gap-3 rounded-3xl border border-border bg-card p-5 shadow-sm"
      noValidate
      onSubmit={(event) => {
        event.preventDefault()
        if (pending) return
        if (missingOnStep(fields, values, step).length) {
          setError("فیلدهای الزامی را کامل کنید.")
          return
        }
        setError("")
        if (!last) {
          setStep(step + 1)
          return
        }
        setPending(true)
        void fetch(`${API_BASE}/v1/public/forms/${encodeURIComponent(formKey)}`, {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({
            payload: visiblePayload(fields, values),
            page_uri: typeof window !== "undefined" ? window.location.pathname : "",
            _hp: hp,
            _t: started.current ? Date.now() - started.current : 0,
          }),
        })
          .then(async (res) => {
            const json = (await res.json().catch(() => null)) as { message?: string } | null
            if (!res.ok) throw new Error(json?.message || "submit_failed")
            window.dispatchEvent(new CustomEvent("wb:form-success", { detail: { formKey } }))
            setDone(true)
          })
          .catch(() => setError("ارسال ناموفق بود. دوباره تلاش کنید."))
          .finally(() => setPending(false))
      }}
    >
      {title ? <h3 className="wb-type-h3">{title}</h3> : null}
      {intro ? <p className="text-sm leading-7 text-foreground/70">{intro}</p> : null}
      {steps > 1 ? (
        <div className="flex gap-1" aria-label={`مرحله ${step} از ${steps}`}>
          {Array.from({ length: steps }, (_, index) => <span key={index} className={`h-1.5 flex-1 rounded-full ${index < step ? "bg-primary" : "bg-muted"}`} />)}
        </div>
      ) : null}
      <div className={`grid gap-3 ${columns ? "md:grid-cols-2" : ""}`}>
        {fields.filter((field) => field.step === step && isVisible(field, values)).map((field) => (
          <label key={field.name} htmlFor={`wbf-${field.name}`} className={`grid gap-1 ${columns && wide(field) ? "md:col-span-2" : ""}`}>
            <span className="wb-label">{field.label}{field.required ? <span aria-hidden className="text-red-500"> *</span> : null}</span>
            <Field field={field} value={values[field.name] ?? ""} onChange={(value) => set(field.name, value)} />
          </label>
        ))}
        {last && showEmail ? (
          <label className="grid gap-1">
            <span className="wb-label">ایمیل</span>
            <input name="email" type="email" dir="ltr" className="wb-field" value={values.email ?? ""} onChange={(event) => set("email", event.target.value)} />
          </label>
        ) : null}
        {last && showPhone ? (
          <label className="grid gap-1">
            <span className="wb-label">موبایل</span>
            <input name="phone" type="tel" dir="ltr" className="wb-field" value={values.phone ?? ""} onChange={(event) => set("phone", event.target.value)} />
          </label>
        ) : null}
      </div>
      {/* Honeypot: hidden from people and assistive tech; bots fill it. */}
      <input type="text" name="website" tabIndex={-1} autoComplete="off" aria-hidden="true" className="absolute -start-[9999px] h-0 w-0 opacity-0" value={hp} onChange={(event) => setHp(event.target.value)} />
      {error ? <p className="wb-form-error" role="alert">{error}</p> : null}
      <div className="flex gap-2">
        {step > 1 ? <button type="button" className="wb-btn wb-btn-outline" onClick={() => setStep(step - 1)}>قبلی</button> : null}
        <button type="submit" className="wb-btn wb-btn-primary flex-1" disabled={pending}>
          {pending ? "…" : last ? propStr(widget.props, "submit", "ارسال") : "بعدی"}
        </button>
      </div>
      {note ? <p className="text-xs leading-6 text-foreground/60">{note}</p> : null}
    </form>
  )
}
