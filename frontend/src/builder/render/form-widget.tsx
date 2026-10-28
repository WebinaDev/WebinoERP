"use client"

import { useEffect, useRef, useState } from "react"

import dynamic from "next/dynamic"

// Heavy (react-day-picker + jalali picker): load only when a form actually has a date field.
const LocaleDatePicker = dynamic(() => import("@/components/ui/locale-date-picker").then((m) => m.LocaleDatePicker), {
  ssr: false,
  loading: () => <input className="wb-field" disabled aria-busy="true" />,
})

import { checkFormUpload, FORM_UPLOAD_ACCEPT, publicFormEndpoints } from "@webina/ui/builder"
import { fieldsFromDefinition, isVisible, missingOnStep, withComputed, parseFormFields, stepCount, visiblePayload, type FormField } from "../forms"
import { parseRows, propStr } from "../props"
import type { WidgetNode } from "../types"

const API_BASE = process.env.NEXT_PUBLIC_API_URL?.trim() || "/api"

const FILE_ACCEPT = FORM_UPLOAD_ACCEPT
const FORM_API = publicFormEndpoints(API_BASE)

/** File field: uploads to private storage right away and keeps only the opaque token as the value. */
function FileField({ field, formKey, onChange }: { field: FormField; formKey: string; onChange: (value: string, name: string) => void }) {
  const [state, setState] = useState<{ name: string; status: "idle" | "uploading" | "done" | "error"; message?: string }>({ name: "", status: "idle" })
  return (
    <div className="grid gap-1">
      <input
        id={`wbf-${field.name}`}
        type="file"
        name={field.name}
        accept={FILE_ACCEPT}
        aria-required={field.required || undefined}
        className="wb-field"
        onChange={(event) => {
          const file = event.target.files?.[0]
          onChange("", "")
          if (!file) return setState({ name: "", status: "idle" })
          const problem = checkFormUpload(file)
          if (problem === "size") return setState({ name: file.name, status: "error", message: "حداکثر حجم فایل ۱۰ مگابایت است" })
          if (problem === "type") return setState({ name: file.name, status: "error", message: "نوع فایل مجاز نیست (jpg، png، webp، pdf، doc، zip)" })
          setState({ name: file.name, status: "uploading" })
          const body = new FormData()
          body.append("file", file)
          void fetch(FORM_API.upload(formKey), { method: "POST", body, headers: { Accept: "application/json" } })
            .then(async (res) => {
              const json = (await res.json().catch(() => null)) as { data?: { token?: string } } | null
              if (!res.ok || !json?.data?.token) throw new Error("upload_failed")
              onChange(json.data.token, file.name)
              setState({ name: file.name, status: "done" })
            })
            .catch(() => setState({ name: file.name, status: "error", message: "بارگذاری فایل ناموفق بود (jpg، png، webp، pdf، doc، zip)" }))
        }}
      />
      <span className="text-xs" aria-live="polite">
        {state.status === "uploading" ? "در حال بارگذاری…" : state.status === "done" ? `✓ ${state.name}` : state.status === "error" ? <span className="wb-form-error">{state.message}</span> : null}
      </span>
    </div>
  )
}

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
  if (field.type === "calc") return <output {...common} htmlFor={undefined} className="wb-field block bg-muted font-bold tabular-nums" aria-live="polite">{value ? Number(value).toLocaleString("fa-IR") : "—"}</output>
  if (field.type === "acceptance")
    return (
      <span className="flex items-center gap-2 text-sm">
        <input {...common} type="checkbox" checked={value === "1"} onChange={(event) => onChange(event.target.checked ? "1" : "")} />
        <span>می‌پذیرم</span>
      </span>
    )
  if (field.type === "rating") {
    const current = Number(value) || 0
    return (
      <div className="flex gap-1" role="radiogroup" aria-label={field.label} dir="ltr">
        {[1, 2, 3, 4, 5].map((n) => (
          <button key={n} type="button" role="radio" aria-checked={current === n} aria-label={`${n.toLocaleString("fa-IR")} ستاره`} className={`text-2xl leading-none ${n <= current ? "text-amber-400" : "text-muted-foreground/40"}`} onClick={() => onChange(current === n ? "" : String(n))}>
            ★
          </button>
        ))}
      </div>
    )
  }
  if (field.type === "time" || field.type === "url" || field.type === "password")
    return <input {...common} type={field.type} dir="ltr" autoComplete={field.type === "password" ? "new-password" : undefined} className="wb-field" value={value} onChange={(event) => onChange(event.target.value)} />
  if (field.type === "date") return <LocaleDatePicker value={value || null} onChange={(next) => onChange(next ?? "")} placeholder={field.label} />
  const type = field.type === "tel" || field.type === "email" || field.type === "number" ? field.type : "text"
  return <input {...common} type={type} dir={type === "tel" || type === "email" ? "ltr" : undefined} className="wb-field" value={value} onChange={(event) => onChange(event.target.value)} />
}

/**
 * Builder form (ported from WebinoDashboard): typed fields, optional/conditional fields, multi-step with
 * per-step required validation, honeypot + timing anti-spam. ERP additions: group headings, private file
 * uploads, step titles, optional review step (editable summary), required confirm checkboxes and prefill
 * (`?prefill_<name>=` or a `wb:form-prefill` event from icon cards).
 * Submits into the Marketing forms system: POST /v1/public/forms/{formKey}.
 */
/** Fields of the Marketing form `formKey` when the widget has no own field list. */
function useDefinition(formKey: string, enabled: boolean): FormField[] | null {
  const [fields, setFields] = useState<FormField[] | null>(null)
  useEffect(() => {
    if (!enabled) return
    let cancel = false
    void fetch(FORM_API.submit(formKey), { headers: { Accept: "application/json" } })
      .then((res) => (res.ok ? res.json() : null))
      .then((json: { data?: { fields?: unknown } } | null) => {
        if (!cancel) setFields(fieldsFromDefinition(json?.data?.fields))
      })
      .catch(() => !cancel && setFields([]))
    return () => {
      cancel = true
    }
  }, [formKey, enabled])
  return fields
}

export function FormWidget({ widget }: { widget: WidgetNode }) {
  const own = typeof widget.props.fields === "string" && widget.props.fields.trim() !== ""
  const definition = useDefinition(propStr(widget.props, "formKey", "") || "contact", !own)
  const fields = own ? parseFormFields(widget.props.fields) : definition ?? []
  const showPhone = widget.props.showPhone === true && !fields.some((f) => f.name === "phone")
  const showEmail = widget.props.showEmail === true && !fields.some((f) => f.name === "email")
  const columns = propStr(widget.props, "columns", "1") === "2"
  const review = widget.props.review === true
  const fieldSteps = stepCount(fields)
  const steps = fieldSteps + (review ? 1 : 0)
  const stepTitles = propStr(widget.props, "stepTitles", "").split("\n").map((line) => line.trim())
  const confirms = parseRows(widget.props.confirms, ["text"]).map((row) => row.text).filter(Boolean)
  const [step, setStep] = useState(1)
  const [values, setValues] = useState<Record<string, string>>({})
  const [fileNames, setFileNames] = useState<Record<string, string>>({})
  const [agreed, setAgreed] = useState<boolean[]>([])
  const [done, setDone] = useState(false)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState("")
  const [hp, setHp] = useState("")
  const [human, setHuman] = useState(false)
  // Captcha is enforced server-side from the published widget; CRM action + webhook run only on the server.
  const captcha = widget.props.captcha === true
  const started = useRef<number | null>(null)
  const success = propStr(widget.props, "success", "پیام شما ثبت شد. به‌زودی پاسخ می‌دهیم.")
  const formKey = propStr(widget.props, "formKey", "") || "contact"
  const title = propStr(widget.props, "title", "")
  const intro = propStr(widget.props, "intro", "")
  const note = propStr(widget.props, "note", "")
  const anchor = propStr(widget.props, "anchor", "").replace(/[^\w-]/g, "")

  useEffect(() => {
    const names = new Set(fields.map((field) => field.name))
    const apply = (name: string, value: string) => {
      if (!names.has(name)) return false
      setValues((current) => ({ ...current, [name]: value }))
      setStep(1)
      setDone(false)
      return true
    }
    const query = new URLSearchParams(window.location.search)
    query.forEach((value, key) => {
      if (key.startsWith("prefill_")) apply(key.slice(8), value)
    })
    const onPrefill = (event: Event) => {
      const detail = (event as CustomEvent<{ anchor?: string; name?: string; value?: string }>).detail
      if (!detail?.name || (detail.anchor && anchor && detail.anchor !== anchor)) return
      apply(detail.name, detail.value ?? "")
    }
    window.addEventListener("wb:form-prefill", onPrefill)
    return () => window.removeEventListener("wb:form-prefill", onPrefill)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [anchor, fields.length])

  if (!own && definition === null) return <div id={anchor || undefined} className="wb-form rounded-3xl border border-border bg-card p-5 text-sm text-foreground/60">در حال بارگذاری فرم…</div>
  if (!fields.length) return <p className="wb-form-error">فرم «{formKey}» پیدا نشد یا فیلدی ندارد (بازاریابی ← فرم‌ها).</p>
  if (done) return <p id={anchor || undefined} className="wb-form-success wb-type-body rounded-2xl bg-muted p-5 text-foreground" role="status">{success}</p>
  const set = (name: string, value: string) => {
    if (started.current === null) started.current = Date.now()
    setValues((current) => ({ ...current, [name]: value }))
  }
  const last = step >= steps
  const reviewing = review && step === steps
  const wide = (field: FormField) => field.type === "textarea" || field.type === "radio" || field.type === "checkbox" || field.type === "heading" || field.type === "file"
  const computed = withComputed(fields, values)
  const shown = (field: FormField) => field.type !== "hidden" && isVisible(field, computed)
  const display = (field: FormField) => (field.type === "file" ? fileNames[field.name] || "" : field.type === "password" ? "••••" : field.type === "acceptance" ? (values[field.name] === "1" ? "پذیرفته شد" : "") : computed[field.name] ?? "")
  return (
    <form
      id={anchor || undefined}
      className="wb-form relative grid gap-3 rounded-3xl border border-border bg-card p-5 shadow-sm"
      noValidate
      onSubmit={(event) => {
        event.preventDefault()
        if (pending) return
        if (!reviewing && missingOnStep(fields, values, step).length) {
          setError("فیلدهای الزامی این مرحله را کامل کنید.")
          return
        }
        if (last) {
          for (let s = 1; s <= fieldSteps; s += 1) {
            if (missingOnStep(fields, values, s).length) {
              setStep(s)
              setError("فیلدهای الزامی این مرحله را کامل کنید.")
              return
            }
          }
          if (confirms.some((_, index) => !agreed[index])) {
            setError("لطفاً موارد تأیید را علامت بزنید.")
            return
          }
        }
        setError("")
        if (!last) {
          setStep(step + 1)
          return
        }
        setPending(true)
        void fetch(FORM_API.submit(formKey), {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json" },
          body: JSON.stringify({
            payload: visiblePayload(fields, values),
            page_uri: typeof window !== "undefined" ? window.location.pathname : "",
            widget_id: widget.id,
            _captcha: captcha && human ? "1" : undefined,
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
        <div className="grid gap-2">
          <div className="flex gap-1" aria-hidden="true">
            {Array.from({ length: steps }, (_, index) => <span key={index} className={`h-1.5 flex-1 rounded-full ${index < step ? "bg-primary" : "bg-muted"}`} />)}
          </div>
          <p className="wb-form-step text-xs font-bold text-foreground/70" aria-live="polite">
            مرحله {step} از {steps}
            {stepTitles[step - 1] ? `: ${stepTitles[step - 1]}` : reviewing ? ": بررسی نهایی" : ""}
          </p>
        </div>
      ) : null}
      {reviewing ? (
        <div className="wb-form-review grid gap-3">
          {Array.from({ length: fieldSteps }, (_, index) => index + 1).map((s) => (
            <div key={s} className="rounded-2xl border border-border p-3">
              <div className="mb-2 flex items-center justify-between gap-2">
                <strong className="text-sm">{stepTitles[s - 1] || `مرحله ${s}`}</strong>
                <button type="button" className="text-xs font-bold text-primary underline" onClick={() => { setError(""); setStep(s) }}>ویرایش</button>
              </div>
              <dl className="grid gap-1 text-sm">
                {fields.filter((field) => field.step === s && field.type !== "heading" && shown(field)).map((field) => (
                  <div key={field.name} className="flex flex-wrap gap-2">
                    <dt className="text-foreground/60">{field.label}:</dt>
                    <dd className="font-semibold">{display(field) || "—"}</dd>
                  </div>
                ))}
              </dl>
            </div>
          ))}
        </div>
      ) : (
        <div className={`grid gap-3 ${columns ? "md:grid-cols-2" : ""}`}>
          {fields.filter((field) => field.step === step && shown(field)).map((field) =>
            field.type === "heading" ? (
              <h4 key={field.name} className={`wb-form-group mt-2 border-b border-border pb-2 text-base font-extrabold ${columns ? "md:col-span-2" : ""}`}>{field.label}</h4>
            ) : (
              <label key={field.name} htmlFor={`wbf-${field.name}`} className={`grid gap-1 ${columns && wide(field) ? "md:col-span-2" : ""}`}>
                <span className="wb-label">{field.label}{field.required ? <span aria-hidden className="text-red-500"> *</span> : null}</span>
                {field.type === "file" ? (
                  <FileField field={field} formKey={formKey} onChange={(token, name) => { set(field.name, token); setFileNames((current) => ({ ...current, [field.name]: name })) }} />
                ) : (
                  <Field field={field} value={computed[field.name] ?? ""} onChange={(value) => set(field.name, value)} />
                )}
              </label>
            ),
          )}
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
      )}
      {last && confirms.length ? (
        <div className="grid gap-2">
          {confirms.map((text, index) => (
            <label key={index} className="flex items-start gap-2 text-sm">
              <input type="checkbox" required checked={agreed[index] === true} onChange={(event) => setAgreed((current) => { const next = [...current]; next[index] = event.target.checked; return next })} />
              <span>{text}<span aria-hidden className="text-red-500"> *</span></span>
            </label>
          ))}
        </div>
      ) : null}
      {/* Honeypot: hidden from people and assistive tech; bots fill it. */}
      <input type="text" name="website" tabIndex={-1} autoComplete="off" aria-hidden="true" className="absolute -start-[9999px] h-0 w-0 opacity-0" value={hp} onChange={(event) => setHp(event.target.value)} />
      {error ? <p className="wb-form-error" role="alert">{error}</p> : null}
      <div className="flex gap-2">
        {step > 1 ? <button type="button" className="wb-btn wb-btn-outline" onClick={() => { setError(""); setStep(step - 1) }}>قبلی</button> : null}
        {captcha && last ? (
          <label className="flex items-center gap-2 rounded-2xl border border-border bg-muted/40 px-3 py-2 text-sm">
            <input type="checkbox" name="_captcha" checked={human} onChange={(e) => setHuman(e.target.checked)} required />
            <span>من ربات نیستم</span>
          </label>
        ) : null}
        <button type="submit" className="wb-btn wb-btn-primary flex-1" disabled={pending}>
          {pending ? "…" : last ? propStr(widget.props, "submit", "ارسال") : "بعدی"}
        </button>
      </div>
      {note ? <p className="whitespace-pre-line text-xs leading-6 text-foreground/60">{note}</p> : null}
    </form>
  )
}
